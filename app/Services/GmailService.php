<?php

namespace App\Services;

//
use Google\Client as Google_Client;
use Google\Service\Gmail as Google_Service_Gmail;
use Google\Service\Gmail\Message as Google_Service_Gmail_Message;
use Illuminate\Support\Facades\Log;

class GmailService
{
    protected $client;
    protected $service;
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;

        if (!$user->google_access_token) {
            throw new \Exception('Missing Google access token for user.');
        }

        // Initialize Google Client
        $this->client = new Google_Client();
        $this->client->setClientId(env('GOOGLE_CLIENT_ID'));
        $this->client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $this->client->setAccessType('offline');
        $this->client->setApprovalPrompt('force');


        $this->client->addScope('https://www.googleapis.com/auth/gmail.send');

        // Calculate expiration
        $expiresAt = $user->google_token_expires_at->timestamp ?? 0;
        $expiresIn = max(0, $expiresAt - time());

        $this->client->setAccessToken([
            'access_token' => $user->google_access_token,
            'refresh_token' => $user->google_refresh_token,
            'expires_in' => $expiresIn,
        ]);

        // Refresh if expired
        if ($this->client->isAccessTokenExpired()) {
            if (!$user->google_refresh_token) {
                Log::error('No refresh token available', ['user_id' => $user->id]);
                throw new \Exception('No refresh token available. Please log in again.');
            }

            try {
                $newToken = $this->client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);

                if (isset($newToken['access_token'])) {
                    $user->update([
                        'google_access_token' => $newToken['access_token'],
                        'google_token_expires_at' => now()->addSeconds($newToken['expires_in']),
                    ]);
                    $this->client->setAccessToken($newToken);
                } else {
                    Log::error('Failed to refresh token', ['user_id' => $user->id, 'response' => $newToken]);
                    throw new \Exception('Invalid response when refreshing token.');
                }
            } catch (\Exception $e) {
                Log::error('Token refresh failed: ' . $e->getMessage(), [
                    'user_id' => $user->id,
                    'trace' => $e->getTraceAsString()
                ]);
                throw new \Exception('Could not refresh Google token.', 0, $e);
            }
        }

        $this->service = new Google_Service_Gmail($this->client);
    }

    public function sendEmail($to, $subject, $htmlBody, $textBody = null)
    {
        $message = new Google_Service_Gmail_Message();

        $rawMessage = "From: {$this->user->email}\r\n";
        $rawMessage .= "To: {$to}\r\n";
        $rawMessage .= "Subject: {$subject}\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";
        $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
        $rawMessage .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $rawMessage .= quoted_printable_encode($htmlBody);

        $encoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($rawMessage));
        $message->setRaw($encoded);

        try {
            return $this->service->users_messages->send('me', $message);
        } catch (\Exception $e) {
            Log::error('Gmail send error: ' . $e->getMessage(), [
                'user_id' => $this->user->id,
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    public function sendEmailWithAttachment(
        array $toEmails,
        string $subject,
        string $htmlBody,
        ?string $pdfBinary = null,
        ?string $filename = 'document.pdf'
    ) {
        $message = new Google_Service_Gmail_Message();

        // Build headers
        $toHeader = implode(', ', $toEmails);
        $rawMessage = "From: {$this->user->email}\r\n";
        $rawMessage .= "To: {$toHeader}\r\n";
        $rawMessage .= "Subject: {$subject}\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";

        if ($pdfBinary) {
            // Create multipart email with attachment
            $boundary = 'boundary_' . md5(time());
            $rawMessage .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";

            // HTML part
            $rawMessage .= "--{$boundary}\r\n";
            $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
            $rawMessage .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $rawMessage .= quoted_printable_encode($htmlBody) . "\r\n\r\n";

            // PDF attachment
            $rawMessage .= "--{$boundary}\r\n";
            $rawMessage .= "Content-Type: application/pdf; name=\"{$filename}\"\r\n";
            $rawMessage .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n";
            $rawMessage .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $rawMessage .= chunk_split(base64_encode($pdfBinary)) . "\r\n";
            $rawMessage .= "--{$boundary}--\r\n";
        } else {
            // Plain HTML email (no attachment)
            $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
            $rawMessage .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $rawMessage .= quoted_printable_encode($htmlBody);
        }

        $encoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($rawMessage));
        $message->setRaw($encoded);

        return $this->service->users_messages->send('me', $message);
    }
}

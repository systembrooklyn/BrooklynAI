<?php

namespace App\Modules\Integrations\Infrastructure\Google\Gmail;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use Google\Client as GoogleClient;
use Google\Service\Gmail as GoogleGmailService;
use Google\Service\Gmail\Message as GoogleGmailMessage;

class GmailEmailSender
{
    public function send(
        ResolvedGoogleCredentials $credentials,
        string $fromEmail,
        string $to,
        string $subject,
        string $htmlBody,
    ): ?string {
        $service = $this->createService($credentials);
        $message = $this->buildMessage($fromEmail, $to, $subject, $htmlBody);

        $sent = $this->dispatch($service, $message);

        return $sent instanceof GoogleGmailMessage ? $sent->getId() : null;
    }

    /**
     * Send a plain-text email preserving the legacy calendar-invitation MIME format:
     * - From header includes display name: "Name <email>"
     * - Content-Type: text/plain; charset=UTF-8
     */
    public function sendPlainText(
        ResolvedGoogleCredentials $credentials,
        string $fromName,
        string $fromEmail,
        string $to,
        string $subject,
        string $body,
    ): ?string {
        $service = $this->createService($credentials);
        $message = $this->buildPlainTextMessage($fromName, $fromEmail, $to, $subject, $body);

        $sent = $this->dispatch($service, $message);

        return $sent instanceof GoogleGmailMessage ? $sent->getId() : null;
    }

    protected function createService(ResolvedGoogleCredentials $credentials): GoogleGmailService
    {
        $client = new GoogleClient;
        $client->setClientId((string) env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret((string) env('GOOGLE_CLIENT_SECRET'));
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');
        $client->addScope('https://www.googleapis.com/auth/gmail.send');

        $expiresIn = 3600;
        if ($credentials->expiresAt !== null) {
            $expiresIn = max(0, $credentials->expiresAt->getTimestamp() - time());
        }

        $client->setAccessToken([
            'access_token' => $credentials->accessToken,
            'refresh_token' => $credentials->refreshToken,
            'expires_in' => $expiresIn,
        ]);

        return new GoogleGmailService($client);
    }

    protected function buildMessage(
        string $fromEmail,
        string $to,
        string $subject,
        string $htmlBody,
    ): GoogleGmailMessage {
        $rawMessage = "From: {$fromEmail}\r\n";
        $rawMessage .= "To: {$to}\r\n";
        $rawMessage .= "Subject: {$subject}\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";
        $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
        $rawMessage .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $rawMessage .= quoted_printable_encode($htmlBody);

        $encoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($rawMessage));

        $message = new GoogleGmailMessage;
        $message->setRaw($encoded);

        return $message;
    }

    protected function buildPlainTextMessage(
        string $fromName,
        string $fromEmail,
        string $to,
        string $subject,
        string $body,
    ): GoogleGmailMessage {
        $sender = "{$fromName} <{$fromEmail}>";

        $headers = [
            'From' => $sender,
            'To' => $to,
            'Subject' => $subject,
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => 'quoted-printable',
        ];

        $headerLines = '';
        foreach ($headers as $key => $value) {
            $headerLines .= "{$key}: {$value}\r\n";
        }
        $headerLines .= "\r\n";

        $encodedBody = quoted_printable_encode($body);
        $rawMessage = $headerLines.$encodedBody;

        $encoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($rawMessage));

        $message = new GoogleGmailMessage;
        $message->setRaw($encoded);

        return $message;
    }

    protected function dispatch(
        GoogleGmailService $service,
        GoogleGmailMessage $message,
    ): ?GoogleGmailMessage {
        $result = $service->users_messages->send('me', $message);

        return $result instanceof GoogleGmailMessage ? $result : null;
    }

    /**
     * Send an HTML email with an optional PDF attachment.
     * Preserves the legacy calendar-multipart MIME format used by
     * GoogleDocsController::generateAndEmailPdf.
     *
     * @param  array<int, string>  $toEmails
     */
    public function sendWithAttachment(
        ResolvedGoogleCredentials $credentials,
        string $fromEmail,
        array $toEmails,
        string $subject,
        string $htmlBody,
        ?string $pdfBinary = null,
        ?string $filename = 'document.pdf',
    ): ?string {
        $service = $this->createService($credentials);
        $message = $this->buildMessageWithAttachment($fromEmail, $toEmails, $subject, $htmlBody, $pdfBinary, $filename);

        $sent = $this->dispatch($service, $message);

        return $sent instanceof GoogleGmailMessage ? $sent->getId() : null;
    }

    /**
     * @param  array<int, string>  $toEmails
     */
    protected function buildMessageWithAttachment(
        string $fromEmail,
        array $toEmails,
        string $subject,
        string $htmlBody,
        ?string $pdfBinary,
        ?string $filename,
    ): GoogleGmailMessage {
        $toHeader = implode(', ', $toEmails);

        $rawMessage = "From: {$fromEmail}\r\n";
        $rawMessage .= "To: {$toHeader}\r\n";
        $rawMessage .= "Subject: {$subject}\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";

        if ($pdfBinary) {
            $boundary = 'boundary_'.md5((string) time());
            $rawMessage .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";

            $rawMessage .= "--{$boundary}\r\n";
            $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
            $rawMessage .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $rawMessage .= quoted_printable_encode($htmlBody)."\r\n\r\n";

            $rawMessage .= "--{$boundary}\r\n";
            $rawMessage .= "Content-Type: application/pdf; name=\"{$filename}\"\r\n";
            $rawMessage .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n";
            $rawMessage .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $rawMessage .= chunk_split(base64_encode($pdfBinary))."\r\n";
            $rawMessage .= "--{$boundary}--\r\n";
        } else {
            $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
            $rawMessage .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $rawMessage .= quoted_printable_encode($htmlBody);
        }

        $encoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($rawMessage));

        $message = new GoogleGmailMessage;
        $message->setRaw($encoded);

        return $message;
    }
}

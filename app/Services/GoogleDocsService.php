<?php

namespace App\Services;

use Google\Client as Google_Client;
use Google\Service\Docs as Google_Service_Docs;
use Google\Service\Drive as Google_Service_Drive;
use Illuminate\Support\Facades\Log;

class GoogleDocsService
{
    protected $client;
    protected $docsService;
    protected $driveService;
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;

        if (!$user->google_access_token) {
            throw new \Exception('Missing Google access token.');
        }

        $this->client = new Google_Client();
        $this->client->setClientId(env('GOOGLE_CLIENT_ID'));
        $this->client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $this->client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));
        $this->client->addScope('https://www.googleapis.com/auth/documents');
        $this->client->addScope('https://www.googleapis.com/auth/drive');

        $expiresAt = strtotime($user->google_token_expires_at);
        $expiresIn = max(0, $expiresAt - time());

        $this->client->setAccessToken([
            'access_token' => $user->google_access_token,
            'refresh_token' => $user->google_refresh_token,
            'expires_in' => $expiresIn,
            'created' => time(),
        ]);

        if ($this->client->isAccessTokenExpired()) {
            if (!$user->google_refresh_token) {
                Log::error('No refresh token for Google Docs', ['user_id' => $user->id]);
                throw new \Exception('Token expired. Re-login required.');
            }

            try {
                $newToken = $this->client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);
                $user->update([
                    'google_access_token' => $newToken['access_token'],
                    'google_token_expires_at' => now()->addSeconds($newToken['expires_in']),
                ]);
                $this->client->setAccessToken($newToken);
            } catch (\Exception $e) {
                Log::error('Google Docs token refresh failed', ['exception' => $e->getMessage()]);
                throw new \Exception('Authentication failed.');
            }
        }

        $this->docsService = new Google_Service_Docs($this->client);
        $this->driveService = new Google_Service_Drive($this->client);
    }

    /**
     * Create a new Google Doc
     */

    public function listAllDocuments()
    {
        try {
            $optParams = [
                'q' => "mimeType='application/vnd.google-apps.document' and trashed=false",
                'fields' => 'files(id, name, modifiedTime, owners, webViewLink)',
                'orderBy' => 'modifiedTime desc',
                'pageSize' => 100,
            ];

            $response = $this->driveService->files->listFiles($optParams);
            $files = $response->getFiles();

            $documents = [];
            foreach ($files as $file) {
                $owners = $file->getOwners();
                $documents[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'lastModified' => $file->getModifiedTime(),
                    'ownerEmail' => $owners ? $owners[0]->getEmailAddress() : null,
                    'url' => $file->getWebViewLink(),
                ];
            }

            return $documents;
        } catch (\Exception $e) {
            Log::error('Google Docs List Error: ' . $e->getMessage(), [
                'user_id' => $this->user->id,
            ]);
            throw $e;
        }
    }

    public function createDocument(string $title = 'Untitled Document')
    {
        try {
            $document = new \Google\Service\Docs\Document([
                'title' => $title
            ]);

            $doc = $this->docsService->documents->create($document);

            return [
                'id' => $doc->getDocumentId(),
                'title' => $doc->getTitle(),
                'url' => "https://docs.google.com/document/d/{$doc->getDocumentId()}/edit",
            ];
        } catch (\Exception $e) {
            Log::error('Google Docs Create Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get document content and metadata
     */
    public function getDocument(string $documentId)
    {
        try {
            // Get full document (content + structure)
            $doc = $this->docsService->documents->get($documentId);

            // Also get Drive metadata (owners, modified time, etc.)
            $driveFile = $this->driveService->files->get($documentId, [
                'fields' => 'id,name,owners,modifiedTime,webViewLink'
            ]);

            $owners = collect($driveFile->getOwners())->map(function ($owner) {
                return [
                    'email' => $owner->getEmailAddress(),
                    'name' => $owner->getDisplayName(),
                ];
            });

            return [
                'id' => $doc->getDocumentId(),
                'title' => $doc->getTitle(),
                'body' => $this->extractText($doc->getBody()),
                'owners' => $owners,
                'lastModified' => $driveFile->getModifiedTime(),
                'url' => $driveFile->getWebViewLink(),
            ];
        } catch (\Exception $e) {
            Log::error('Google Docs Get Error: ' . $e->getMessage(), [
                'document_id' => $documentId,
                'user_id' => $this->user->id,
            ]);
            throw $e;
        }
    }

    /**
     * Append text to the end of the document
     */
    public function appendText(string $documentId, string $text)
    {
        try {
            $requests = [
                new \Google\Service\Docs\Request([
                    'insertText' => [
                        'location' => [
                            'segmentId' => '',
                            'index' => 1, // Start at end; we'll adjust below
                        ],
                        'text' => "\n\n" . $text,
                    ]
                ])
            ];

            // First, get document to find end index
            $doc = $this->docsService->documents->get($documentId);
            $endIndex = $doc->getBody()->getContent()[count($doc->getBody()->getContent()) - 1]->getEndIndex();

            // Update index to end of doc
            $requests[0]->getInsertText()->getLocation()->setIndex($endIndex - 1);

            $batchUpdate = new \Google\Service\Docs\BatchUpdateDocumentRequest([
                'requests' => $requests
            ]);

            $this->docsService->documents->batchUpdate($documentId, $batchUpdate);

            return ['message' => 'Text appended successfully.'];
        } catch (\Exception $e) {
            Log::error('Google Docs Append Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Helper: Extract plain text from document body
     */
    private function extractText($body)
    {
        $text = '';
        foreach ($body->getContent() as $element) {
            if ($element->getParagraph()) {
                foreach ($element->getParagraph()->getElements() as $elem) {
                    if ($elem->getTextRun()) {
                        $text .= $elem->getTextRun()->getContent();
                    }
                }
            }
        }
        return trim($text);
    }

    /**
     * Replace the entire content of a Google Doc
     */
    public function updateDocument(string $documentId, string $newContent)
    {
        try {
            // Get current document
            $doc = $this->docsService->documents->get($documentId);
            $body = $doc->getBody();
            $content = $body->getContent();

            // Find the last valid index BEFORE the final newline
            $endIndex = $content[count($content) - 1]->getEndIndex(); // e.g., 100
            $safeEndIndex = $endIndex - 1; // e.g., 99

            // If document is empty or only has newline, start from index 1
            if ($safeEndIndex < 1) {
                $safeEndIndex = 1;
            }

            $requests = [];

            // Only delete if there's actual content (not just the newline)
            if ($safeEndIndex > 1) {
                $requests[] = new \Google\Service\Docs\Request([
                    'deleteContentRange' => [
                        'range' => [
                            'startIndex' => 1,
                            'endIndex' => $safeEndIndex,
                        ]
                    ]
                ]);
            }

            // Insert new content
            $requests[] = new \Google\Service\Docs\Request([
                'insertText' => [
                    'location' => ['index' => 1],
                    'text' => $newContent,
                ]
            ]);

            $batchUpdate = new \Google\Service\Docs\BatchUpdateDocumentRequest([
                'requests' => $requests
            ]);

            $this->docsService->documents->batchUpdate($documentId, $batchUpdate);

            return ['message' => 'Document updated successfully.'];
        } catch (\Exception $e) {
            Log::error('Google Docs Update Error: ' . $e->getMessage(), [
                'document_id' => $documentId,
                'user_id' => $this->user->id,
            ]);
            throw $e;
        }
    }

    /**
     * Move a Google Doc to trash (soft delete)
     */
    public function deleteDocument(string $documentId)
    {
        try {
            //     for delete permenantly  :   $this->driveService->files->delete($documentId);
            // return ['message' => 'Document permanently deleted.'];
            // Use Drive API to trash the file
            $this->driveService->files->update($documentId, new \Google\Service\Drive\DriveFile([
                'trashed' => true
            ]));



            return ['message' => 'Document moved to trash.'];
        } catch (\Exception $e) {
            Log::error('Google Docs Delete Error: ' . $e->getMessage(), [
                'document_id' => $documentId,
                'user_id' => $this->user->id,
            ]);
            throw $e;
        }
    }


    /**
     * Export a Google Doc as PDF and return the binary content or download URL
     */
    public function exportDocAsPdf(string $documentId, bool $returnBinary = false)
    {
        try {
            // Use Drive API to export the document as PDF
            $response = $this->driveService->files->export($documentId, 'application/pdf', [
                'alt' => 'media'
            ]);

            if ($returnBinary) {
                // Return raw PDF binary (for saving to disk or streaming)
                return (string) $response->getBody();
            } else {
                // Return a temporary download URL (if you store PDF in your own storage)
                // Or just return metadata
                return [
                    'documentId' => $documentId,
                    'mimeType' => 'application/pdf',
                    'exportedAt' => now()->toIso8601String(),
                ];
            }
        } catch (\Exception $e) {
            Log::error('Google Docs PDF Export Error: ' . $e->getMessage(), [
                'document_id' => $documentId,
                'user_id' => $this->user->id,
            ]);
            throw $e;
        }
    }
    /**
     * Create a personalized copy of a template doc by replacing placeholders
     */
    public function createPersonalizedDoc(array $data, string $title = 'Personalized Document')
    {
        try {
            $templateId = env('GOOGLE_DOCS_TEMPLATE_ID');
            if (!$templateId) {
                throw new \Exception('GOOGLE_DOCS_TEMPLATE_ID not set in .env');
            }

            // Step 1: Make a copy of the template
            $driveFile = new \Google\Service\Drive\DriveFile([
                'name' => $title,
            ]);
            $copiedFile = $this->driveService->files->copy($templateId, $driveFile);
            $newDocId = $copiedFile->getId();

            // Step 2: Build replace requests for each placeholder
            $requests = [];
            foreach ($data as $key => $value) {
                $placeholder = "{{{$key}}}";
                $requests[] = new \Google\Service\Docs\Request([
                    'replaceAllText' => [
                        'containsText' => [
                            'text' => $placeholder,
                            'matchCase' => true,
                        ],
                        'replaceText' => $value,
                    ]
                ]);
            }

            // Step 3: Apply replacements
            if (!empty($requests)) {
                $batchUpdate = new \Google\Service\Docs\BatchUpdateDocumentRequest([
                    'requests' => $requests
                ]);
                $this->docsService->documents->batchUpdate($newDocId, $batchUpdate);
            }

            return [
                'id' => $newDocId,
                'title' => $title,
                'url' => "https://docs.google.com/document/d/{$newDocId}/edit",
            ];
        } catch (\Exception $e) {
            Log::error('Personalized Doc Creation Error: ' . $e->getMessage(), [
                'user_id' => $this->user->id,
                'data' => $data,
            ]);
            throw $e;
        }
    }

    public function getDocAsPdfBinary(string $documentId): string
    {
        $response = $this->driveService->files->export($documentId, 'application/pdf', ['alt' => 'media']);
        return (string) $response->getBody();
    }
}

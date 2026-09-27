<?php

namespace App\Modules\Integrations\Infrastructure\Google\Docs;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use Google\Client as GoogleClient;
use Google\Service\Docs as GoogleDocsService;
use Google\Service\Docs\BatchUpdateDocumentRequest as GoogleDocsBatchUpdateRequest;
use Google\Service\Docs\Document as GoogleDocsDocument;
use Google\Service\Docs\Request as GoogleDocsRequest;
use Google\Service\Drive as GoogleDriveService;
use Google\Service\Drive\DriveFile as GoogleDriveFile;

class GoogleDocsClient
{
    public function listAllDocuments(ResolvedGoogleCredentials $credentials): array
    {
        $drive = $this->createDriveService($credentials);

        $optParams = [
            'q' => "mimeType='application/vnd.google-apps.document' and trashed=false",
            'fields' => 'files(id, name, modifiedTime, owners, webViewLink)',
            'orderBy' => 'modifiedTime desc',
            'pageSize' => 100,
        ];

        $response = $this->dispatchDriveListFiles($drive, $optParams);
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
    }

    public function createDocument(ResolvedGoogleCredentials $credentials, string $title): array
    {
        $docs = $this->createDocsService($credentials);

        $document = new GoogleDocsDocument(['title' => $title]);

        $created = $this->dispatchDocsCreate($docs, $document);

        return [
            'id' => $created->getDocumentId(),
            'title' => $created->getTitle(),
            'url' => "https://docs.google.com/document/d/{$created->getDocumentId()}/edit",
        ];
    }

    public function getDocument(ResolvedGoogleCredentials $credentials, string $documentId): array
    {
        $docs = $this->createDocsService($credentials);
        $drive = $this->createDriveService($credentials);

        $doc = $this->dispatchDocsGet($docs, $documentId);
        $driveFile = $this->dispatchDriveGetFile($drive, $documentId, [
            'fields' => 'id,name,owners,modifiedTime,webViewLink',
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
    }

    public function appendText(ResolvedGoogleCredentials $credentials, string $documentId, string $text): array
    {
        $docs = $this->createDocsService($credentials);

        $doc = $this->dispatchDocsGet($docs, $documentId);
        $content = $doc->getBody()->getContent();
        $endIndex = $content[count($content) - 1]->getEndIndex();

        $requests = [
            new GoogleDocsRequest([
                'insertText' => [
                    'location' => [
                        'segmentId' => '',
                        'index' => $endIndex - 1,
                    ],
                    'text' => "\n\n".$text,
                ],
            ]),
        ];

        $batchUpdate = new GoogleDocsBatchUpdateRequest(['requests' => $requests]);
        $this->dispatchDocsBatchUpdate($docs, $documentId, $batchUpdate);

        return ['message' => 'Text appended successfully.'];
    }

    public function updateDocument(ResolvedGoogleCredentials $credentials, string $documentId, string $newContent): array
    {
        $docs = $this->createDocsService($credentials);

        $doc = $this->dispatchDocsGet($docs, $documentId);
        $content = $doc->getBody()->getContent();
        $endIndex = $content[count($content) - 1]->getEndIndex();
        $safeEndIndex = $endIndex - 1;

        if ($safeEndIndex < 1) {
            $safeEndIndex = 1;
        }

        $requests = [];
        if ($safeEndIndex > 1) {
            $requests[] = new GoogleDocsRequest([
                'deleteContentRange' => [
                    'range' => [
                        'startIndex' => 1,
                        'endIndex' => $safeEndIndex,
                    ],
                ],
            ]);
        }

        $requests[] = new GoogleDocsRequest([
            'insertText' => [
                'location' => ['index' => 1],
                'text' => $newContent,
            ],
        ]);

        $batchUpdate = new GoogleDocsBatchUpdateRequest(['requests' => $requests]);
        $this->dispatchDocsBatchUpdate($docs, $documentId, $batchUpdate);

        return ['message' => 'Document updated successfully.'];
    }

    public function deleteDocument(ResolvedGoogleCredentials $credentials, string $documentId): array
    {
        $drive = $this->createDriveService($credentials);

        $this->dispatchDriveUpdateFile($drive, $documentId, new GoogleDriveFile([
            'trashed' => true,
        ]));

        return ['message' => 'Document moved to trash.'];
    }

    public function getDocAsPdfBinary(ResolvedGoogleCredentials $credentials, string $documentId): string
    {
        $drive = $this->createDriveService($credentials);

        $response = $this->dispatchDriveExport($drive, $documentId, 'application/pdf', ['alt' => 'media']);

        return (string) $response->getBody();
    }

    public function exportDocAsPdf(ResolvedGoogleCredentials $credentials, string $documentId, bool $returnBinary = false)
    {
        $response = $this->dispatchDriveExport(
            $this->createDriveService($credentials),
            $documentId,
            'application/pdf',
            ['alt' => 'media'],
        );

        if ($returnBinary) {
            return (string) $response->getBody();
        }

        return [
            'documentId' => $documentId,
            'mimeType' => 'application/pdf',
            'exportedAt' => now()->toIso8601String(),
        ];
    }

    public function createPersonalizedDoc(ResolvedGoogleCredentials $credentials, array $data, string $title = 'Personalized Document'): array
    {
        $templateId = env('GOOGLE_DOCS_TEMPLATE_ID');
        if (! $templateId) {
            throw new \Exception('GOOGLE_DOCS_TEMPLATE_ID not set in .env');
        }

        $docs = $this->createDocsService($credentials);
        $drive = $this->createDriveService($credentials);

        $driveFile = new GoogleDriveFile(['name' => $title]);
        $copiedFile = $this->dispatchDriveCopy($drive, $templateId, $driveFile);
        $newDocId = $copiedFile->getId();

        $requests = [];
        foreach ($data as $key => $value) {
            $placeholder = "{{{$key}}}";
            $requests[] = new GoogleDocsRequest([
                'replaceAllText' => [
                    'containsText' => [
                        'text' => $placeholder,
                        'matchCase' => true,
                    ],
                    'replaceText' => $value,
                ],
            ]);
        }

        if (! empty($requests)) {
            $batchUpdate = new GoogleDocsBatchUpdateRequest(['requests' => $requests]);
            $this->dispatchDocsBatchUpdate($docs, $newDocId, $batchUpdate);
        }

        return [
            'id' => $newDocId,
            'title' => $title,
            'url' => "https://docs.google.com/document/d/{$newDocId}/edit",
        ];
    }

    protected function createClient(ResolvedGoogleCredentials $credentials): GoogleClient
    {
        $client = new GoogleClient;
        $client->setClientId((string) env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret((string) env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri((string) env('GOOGLE_REDIRECT_URI'));
        $client->addScope('https://www.googleapis.com/auth/documents');
        $client->addScope('https://www.googleapis.com/auth/drive');

        $expiresAt = $credentials->expiresAt !== null ? $credentials->expiresAt->getTimestamp() : 0;
        $expiresIn = max(0, $expiresAt - time());

        $client->setAccessToken([
            'access_token' => $credentials->accessToken,
            'refresh_token' => $credentials->refreshToken,
            'expires_in' => $expiresIn,
            'created' => time(),
        ]);

        return $client;
    }

    protected function createDocsService(ResolvedGoogleCredentials $credentials): GoogleDocsService
    {
        return new GoogleDocsService($this->createClient($credentials));
    }

    protected function createDriveService(ResolvedGoogleCredentials $credentials): GoogleDriveService
    {
        return new GoogleDriveService($this->createClient($credentials));
    }

    private function extractText($body): string
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

    protected function dispatchDocsCreate(GoogleDocsService $service, GoogleDocsDocument $document): GoogleDocsDocument
    {
        return $service->documents->create($document);
    }

    protected function dispatchDocsGet(GoogleDocsService $service, string $documentId): GoogleDocsDocument
    {
        return $service->documents->get($documentId);
    }

    protected function dispatchDocsBatchUpdate(GoogleDocsService $service, string $documentId, GoogleDocsBatchUpdateRequest $request): void
    {
        $service->documents->batchUpdate($documentId, $request);
    }

    protected function dispatchDriveListFiles(GoogleDriveService $service, array $optParams): mixed
    {
        return $service->files->listFiles($optParams);
    }

    protected function dispatchDriveGetFile(GoogleDriveService $service, string $fileId, array $optParams): mixed
    {
        return $service->files->get($fileId, $optParams);
    }

    protected function dispatchDriveUpdateFile(GoogleDriveService $service, string $fileId, GoogleDriveFile $metadata): mixed
    {
        return $service->files->update($fileId, $metadata);
    }

    protected function dispatchDriveCopy(GoogleDriveService $service, string $fileId, GoogleDriveFile $metadata): mixed
    {
        return $service->files->copy($fileId, $metadata);
    }

    protected function dispatchDriveExport(GoogleDriveService $service, string $fileId, string $mimeType, array $optParams): mixed
    {
        return $service->files->export($fileId, $mimeType, $optParams);
    }
}

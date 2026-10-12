<?php

namespace App\Modules\Integrations\Infrastructure\Google\Gmail;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Integrations\Core\Exceptions\DriveProviderException;
use App\Modules\Integrations\Core\Exceptions\GmailProviderException;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveFileUploader;

/**
 * Materializes Gmail attachments into Google Drive references.
 *
 * For each attachment part:
 *   1. Downloads the bytes via GmailAttachmentReader.
 *   2. Uploads the bytes to the user's Drive via GoogleDriveFileUploader.
 *   3. Returns a normalized result entry.
 *
 * Failure policy: partial success per attachment. A failure in one
 * attachment does not prevent subsequent attachments from being
 * processed. Failed entries carry a stable machine-readable error code,
 * a null drive_file_id, and a null web_view_link.
 *
 * Never exposes tokens, credentials, provider exception messages, or
 * stack traces in the returned array.
 */
class GmailAttachmentMaterializer
{
    public const ERROR_GMAIL_DOWNLOAD_FAILED = 'gmail_download_failed';

    public const ERROR_DRIVE_UPLOAD_FAILED = 'drive_upload_failed';

    public function __construct(
        private readonly GmailAttachmentReader $reader,
        private readonly GoogleDriveFileUploader $uploader,
    ) {}

    /**
     * @param  array<int, array{attachment_id: string, filename: string, mime_type: string, size: int|null}>  $parts
     * @return array<int, array<string, mixed>>
     */
    public function materialize(
        ResolvedGoogleCredentials $credentials,
        string $messageId,
        array $parts,
    ): array {
        $results = [];

        foreach ($parts as $part) {
            $results[] = $this->materializeOne($credentials, $messageId, $part);
        }

        return $results;
    }

    /**
     * @param  array{attachment_id: string, filename: string, mime_type: string, size: int|null}  $part
     * @return array<string, mixed>
     */
    private function materializeOne(
        ResolvedGoogleCredentials $credentials,
        string $messageId,
        array $part,
    ): array {
        $attachmentId = (string) $part['attachment_id'];
        $filename = (string) ($part['filename'] ?? '');
        $mimeType = (string) ($part['mime_type'] ?? 'application/octet-stream');
        $reportedSize = $part['size'] ?? null;

        $base = [
            'gmail_attachment_id' => $attachmentId,
            'drive_file_id' => null,
            'web_view_link' => null,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size' => $reportedSize,
        ];

        // Step 1 — fetch bytes from Gmail.
        try {
            $bytes = $this->reader->read($credentials, $messageId, $attachmentId);
        } catch (GmailProviderException) {
            return [...$base, 'error' => self::ERROR_GMAIL_DOWNLOAD_FAILED];
        }

        // Bytes are now known; the actual size replaces the reported size.
        $base['size'] = strlen($bytes);

        // Step 2 — upload bytes to Drive.
        try {
            $uploaded = $this->uploader->uploadWithMetadata($credentials, $bytes, $filename, $mimeType);
        } catch (DriveProviderException) {
            return [...$base, 'error' => self::ERROR_DRIVE_UPLOAD_FAILED];
        }

        $base['drive_file_id'] = $uploaded['id'];
        $base['web_view_link'] = $uploaded['web_view_link'];

        return $base;
    }
}

<?php

namespace Tests\Unit\Integrations\Drive;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveClient;
use Google\Service\Drive\DriveFile as GoogleDriveFile;
use PHPUnit\Framework\TestCase;

final class GoogleDriveClientTest extends TestCase
{
    public function test_scope_constant_matches_drive_file(): void
    {
        $reflection = new \ReflectionClass(GoogleDriveClient::class);
        $constant = $reflection->getConstant('SCOPE');

        $this->assertSame('https://www.googleapis.com/auth/drive.file', $constant);
    }

    public function test_create_permission_rejects_anyone_type(): void
    {
        $client = new GoogleDriveClient;
        $credentials = $this->credentials();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('type:anyone is not permitted');

        $client->createPermission($credentials, 'file-1', 'anyone', 'reader');
    }

    public function test_map_file_returns_canonical_shape(): void
    {
        $file = new GoogleDriveFile([
            'id' => 'abc123',
            'name' => 'report.pdf',
            'mimeType' => 'application/pdf',
            'size' => '4096',
            'webViewLink' => 'https://drive.google.com/file/d/abc123/view',
            'parents' => ['folder-1'],
            'description' => 'Q4 report',
            'properties' => ['dept' => 'finance'],
            'createdTime' => '2026-01-01T00:00:00Z',
            'modifiedTime' => '2026-01-02T00:00:00Z',
        ]);

        $method = new \ReflectionMethod(GoogleDriveClient::class, 'mapFile');
        $method->setAccessible(true);

        $result = $method->invoke(new GoogleDriveClient, $file);

        $this->assertSame('abc123', $result['id']);
        $this->assertSame('report.pdf', $result['name']);
        $this->assertSame('application/pdf', $result['mime_type']);
        $this->assertSame('4096', $result['size']);
        $this->assertSame('https://drive.google.com/file/d/abc123/view', $result['web_view_link']);
        $this->assertSame(['folder-1'], $result['parents']);
        $this->assertSame('Q4 report', $result['description']);
        $this->assertSame(['dept' => 'finance'], $result['properties']);
        $this->assertSame('2026-01-01T00:00:00Z', $result['created_time']);
        $this->assertSame('2026-01-02T00:00:00Z', $result['modified_time']);
    }

    public function test_map_file_handles_empty_file(): void
    {
        $file = new GoogleDriveFile;

        $method = new \ReflectionMethod(GoogleDriveClient::class, 'mapFile');
        $method->setAccessible(true);

        $result = $method->invoke(new GoogleDriveClient, $file);

        $this->assertNull($result['id']);
        $this->assertNull($result['name']);
        $this->assertSame([], $result['parents']);
        $this->assertSame([], $result['properties']);
    }

    public function test_download_file_rejects_workspace_file(): void
    {
        $client = new class extends GoogleDriveClient {
            public function downloadFile(ResolvedGoogleCredentials $credentials, string $fileId): string
            {
                $response = new GoogleDriveFile([
                    'id' => $fileId,
                    'mimeType' => 'application/vnd.google-apps.document',
                ]);

                if ($response instanceof \Psr\Http\Message\ResponseInterface) {
                    return (string) $response->getBody();
                }

                if ($response instanceof GoogleDriveFile) {
                    $mime = $response->getMimeType();
                    if (is_string($mime) && str_starts_with($mime, 'application/vnd.google-apps.')) {
                        throw new \RuntimeException('Drive file is a Google Workspace document');
                    }
                }

                throw new \RuntimeException('Drive download did not return binary content');
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Google Workspace document');

        $client->downloadFile($this->credentials(), 'doc-id');
    }

    private function credentials(): ResolvedGoogleCredentials
    {
        return new ResolvedGoogleCredentials('access', 'refresh', null);
    }
}

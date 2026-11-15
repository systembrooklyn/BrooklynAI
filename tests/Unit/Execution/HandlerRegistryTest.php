<?php

namespace Tests\Unit\Execution;

use App\Modules\Execution\Infrastructure\Invokers\HandlerRegistry;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailSendEmailHandler;
use PHPUnit\Framework\TestCase;

class HandlerRegistryTest extends TestCase
{
    public function test_resolves_google_gmail_send_email(): void
    {
        $registry = new HandlerRegistry;

        $this->assertSame(
            GmailSendEmailHandler::class,
            $registry->resolve('google.gmail', 'send_email'),
        );
    }

    /**
     * Phase 1 — all non-send_email Gmail handlers are disabled.
     * See GOOGLE_INTEGRATIONS_MASTER_PLAN §3.
     */
    public function test_does_not_resolve_disabled_gmail_reply_to_email(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'reply_to_email'));
    }

    public function test_does_not_resolve_disabled_gmail_create_draft(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'create_draft'));
    }

    public function test_does_not_resolve_disabled_gmail_mark_as_read(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'mark_as_read'));
    }

    public function test_does_not_resolve_disabled_gmail_mark_as_unread(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'mark_as_unread'));
    }

    public function test_does_not_resolve_disabled_gmail_archive(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'archive'));
    }

    public function test_does_not_resolve_disabled_gmail_trash(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'trash'));
    }

    public function test_does_not_resolve_disabled_gmail_add_label(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'add_label'));
    }

    public function test_does_not_resolve_disabled_gmail_remove_label(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'remove_label'));
    }

    public function test_does_not_resolve_disabled_gmail_create_label(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'create_label'));
    }

    public function test_does_not_resolve_disabled_gmail_send_attachment_links_email(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'send_attachment_links_email'));
    }

    public function test_returns_null_for_actions_without_a_handler(): void
    {
        $registry = new HandlerRegistry;

        // Catalog-declared but no runtime handler wired yet.
        $this->assertNull($registry->resolve('google.calendar', 'create_event'));

        // Non-existent integration or action.
        $this->assertNull($registry->resolve('unknown.integration', 'x'));
    }
    public function test_resolves_all_fourteen_drive_actions(): void
{
    $registry = new HandlerRegistry;

    $expected = [
        'create_file' => \App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveCreateFileHandler::class,
        'upload_file' => \App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveUploadFileHandler::class,
        'get_file_details' => \App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveGetFileDetailsHandler::class,
        'download_file' => \App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveDownloadFileHandler::class,
        'update_file_metadata' => \App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveUpdateFileMetadataHandler::class,
        'copy_file' => \App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveCopyFileHandler::class,
        'move_file' => \App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveMoveFileHandler::class,
        'rename_file' => \App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveRenameFileHandler::class,
        'delete_file' => \App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveDeleteFileHandler::class,
        'create_folder' => \App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveCreateFolderHandler::class,
        'list_accessible_files' => \App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveListAccessibleFilesHandler::class,
        'search_accessible_files' => \App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveSearchAccessibleFilesHandler::class,
        'share_file' => \App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveShareFileHandler::class,
        'export_workspace_file' => \App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveExportWorkspaceFileHandler::class,
    ];

    foreach ($expected as $actionKey => $handlerClass) {
        $this->assertSame($handlerClass, $registry->resolve('google.drive', $actionKey), "Failed for: {$actionKey}");
    }
}
}

<?php

namespace App\Modules\Execution\Infrastructure\Invokers;

use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveCopyFileHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveCreateFileHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveCreateFolderHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveDeleteFileHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveDownloadFileHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveExportWorkspaceFileHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveGetFileDetailsHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveListAccessibleFilesHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveMoveFileHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveRenameFileHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveSearchAccessibleFilesHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveShareFileHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveUpdateFileMetadataHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveUploadFileHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailSendEmailHandler;

final class HandlerRegistry
{
    private const HANDLERS = [
        'google.gmail.send_email' => GmailSendEmailHandler::class,

        // Phase 3: Google Drive actions
        'google.drive.create_file' => DriveCreateFileHandler::class,
        'google.drive.upload_file' => DriveUploadFileHandler::class,
        'google.drive.get_file_details' => DriveGetFileDetailsHandler::class,
        'google.drive.download_file' => DriveDownloadFileHandler::class,
        'google.drive.update_file_metadata' => DriveUpdateFileMetadataHandler::class,
        'google.drive.copy_file' => DriveCopyFileHandler::class,
        'google.drive.move_file' => DriveMoveFileHandler::class,
        'google.drive.rename_file' => DriveRenameFileHandler::class,
        'google.drive.delete_file' => DriveDeleteFileHandler::class,
        'google.drive.create_folder' => DriveCreateFolderHandler::class,
        'google.drive.list_accessible_files' => DriveListAccessibleFilesHandler::class,
        'google.drive.search_accessible_files' => DriveSearchAccessibleFilesHandler::class,
        'google.drive.share_file' => DriveShareFileHandler::class,
        'google.drive.export_workspace_file' => DriveExportWorkspaceFileHandler::class,
    ];

    /**
     * @return class-string<ActionHandler>|null
     */
    public function resolve(string $integrationKey, string $actionKey): ?string
    {
        return self::HANDLERS[$integrationKey.'.'.$actionKey] ?? null;
    }
}

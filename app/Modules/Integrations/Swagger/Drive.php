<?php

namespace App\Modules\Integrations\Swagger;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'DriveFile',
    required: ['id', 'name', 'mime_type'],
    properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'name', type: 'string', nullable: true),
        new OA\Property(property: 'mime_type', type: 'string', nullable: true),
        new OA\Property(property: 'size', type: 'string', nullable: true, description: 'Byte size as string (Drive API v3 convention)'),
        new OA\Property(property: 'web_view_link', type: 'string', nullable: true, format: 'uri'),
        new OA\Property(property: 'parents', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'properties', type: 'object', additionalProperties: true, nullable: true),
        new OA\Property(property: 'created_time', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'modified_time', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'DrivePermission',
    required: ['permission_id', 'type', 'role'],
    properties: [
        new OA\Property(property: 'permission_id', type: 'string'),
        new OA\Property(property: 'type', type: 'string', enum: ['user', 'group', 'domain']),
        new OA\Property(property: 'role', type: 'string', enum: ['reader', 'commenter', 'writer']),
        new OA\Property(property: 'email_address', type: 'string', nullable: true),
        new OA\Property(property: 'domain', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'DriveDownloadOutput',
    required: ['file_id', 'storage_path', 'size'],
    properties: [
        new OA\Property(property: 'file_id', type: 'string'),
        new OA\Property(property: 'storage_path', type: 'string', description: 'BrooklynAI internal storage path; pass to a subsequent Upload File step as storage_path config'),
        new OA\Property(property: 'size', type: 'integer'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'DriveExportOutput',
    required: ['file_id', 'storage_path', 'mime_type', 'size'],
    properties: [
        new OA\Property(property: 'file_id', type: 'string'),
        new OA\Property(property: 'storage_path', type: 'string'),
        new OA\Property(property: 'mime_type', type: 'string'),
        new OA\Property(property: 'size', type: 'integer', description: 'Must not exceed 10 MB (Google Drive export limit)'),
    ],
    type: 'object',
)]
/**
 * Google Drive integration for BrooklynAI.
 *
 * SCOPE: https://www.googleapis.com/auth/drive.file
 *
 * ACCESS LIMITATION:
 *   drive.file grants access ONLY to files created by BrooklynAI or
 *   selected by the user via Google Picker. Actions here do NOT have
 *   access to the user's entire Drive. List Accessible Files and
 *   Search Accessible Files return only the app-visible subset.
 *
 * CATALOG SURFACE:
 *   All 14 Drive actions and 3 Drive triggers are exposed through the
 *   existing GET /api/catalog endpoint (see Catalog.php, operationId
 *   "catalog.show"). This Swagger class documents Drive-specific schemas
 *   and constraints; it does not add any new HTTP route.
 *
 * 14 ACTIONS:
 *   create_file, upload_file, get_file_details, download_file,
 *   update_file_metadata, copy_file, move_file, rename_file,
 *   delete_file, create_folder, list_accessible_files,
 *   search_accessible_files, share_file, export_workspace_file
 *
 * 3 TRIGGERS:
 *   file_created, file_updated, file_removed_from_access
 *
 * TRIGGER STRATEGY:
 *   All 3 triggers use TriggerStrategy::Poll (GoogleDriveChangePollStrategy)
 *   with Drive Changes API v3 and durable page_token persistence in
 *   workflow_triggers.runtime_state JSON.
 *
 * PICKER FLOW:
 *   Web: Google Picker JS API with drive.file scope. Selected file IDs
 *        are validated server-side via files.get before use.
 *   Mobile/Desktop: OAuth redirect flow with trigger_onepick=true.
 *        Only drive.file scope is permitted in this flow.
 *
 * LIMITATIONS:
 *   - drive.file is per-file, not Drive-wide.
 *   - "File Removed from Access" fires on deletion OR loss of access.
 *   - export_workspace_file enforces a 10 MB limit (Drive API limit).
 *   - share_file disallows type:anyone in Phase 3 (product restriction).
 *   - download_file and export_workspace_file write to internal storage;
 *     the storage_path output is not a public URL.
 */
class Drive
{
}

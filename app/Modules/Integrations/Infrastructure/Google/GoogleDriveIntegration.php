<?php

namespace App\Modules\Integrations\Infrastructure\Google;

use App\Modules\Integrations\Core\Contracts\IntegrationProvider;
use App\Modules\Integrations\Core\Entities\ActionDefinition;
use App\Modules\Integrations\Core\Entities\AuthDefinition;
use App\Modules\Integrations\Core\Entities\IntegrationDefinition;
use App\Modules\Integrations\Core\Entities\TriggerDefinition;
use App\Modules\Integrations\Core\ValueObjects\FieldDefinition;
use App\Modules\Integrations\Core\ValueObjects\IntegrationKey;
use App\Modules\Integrations\Core\ValueObjects\ProviderKey;
use App\Modules\Integrations\Core\ValueObjects\ScopeIdentifier;
use App\Modules\Integrations\Core\ValueObjects\TriggerStrategy;

final class GoogleDriveIntegration implements IntegrationProvider
{
    private const NS = 'integrations::catalog.google_drive';
    private const SCOPE_DRIVE_FILE = 'https://www.googleapis.com/auth/drive.file';

    public function definition(): IntegrationDefinition
    {
        $scope = ScopeIdentifier::fromString(self::SCOPE_DRIVE_FILE);

        return new IntegrationDefinition(
            key: IntegrationKey::fromString('google.drive'),
            providerKey: ProviderKey::fromString('google'),
            name: 'Google Drive',
            description: 'Manage files and folders in Google Drive.',
            category: 'storage',
            auth: new AuthDefinition(
                type: 'oauth2',
                providerKey: ProviderKey::fromString('google'),
            ),
            nameKey: self::NS . '.name',
            descriptionKey: self::NS . '.description',
            actions: [
                new ActionDefinition(
                    key: 'create_file',
                    label: 'Create File',
                    description: 'Create a new file in Drive with provided content.',
                    labelKey: self::NS . '.actions.create_file.label',
                    descriptionKey: self::NS . '.actions.create_file.description',
                    requiredScopes: [$scope],
                    capability: 'drive',
                    fields: [
                        new FieldDefinition(key: 'filename', label: 'Filename', type: 'string', required: true),
                        new FieldDefinition(key: 'content', label: 'Content', type: 'text', required: true),
                        new FieldDefinition(key: 'mime_type', label: 'MIME Type', type: 'string', required: true, default: 'text/plain'),
                        new FieldDefinition(key: 'folder_id', label: 'Folder ID', type: 'string', required: false),
                    ],
                ),
                new ActionDefinition(
                    key: 'upload_file',
                    label: 'Upload File',
                    description: 'Upload a file from BrooklynAI storage to Google Drive.',
                    labelKey: self::NS . '.actions.upload_file.label',
                    descriptionKey: self::NS . '.actions.upload_file.description',
                    requiredScopes: [$scope],
                    capability: 'drive',
                    fields: [
                        new FieldDefinition(key: 'storage_path', label: 'Storage Path', type: 'string', required: true, description: 'Internal storage reference, typically {{ steps.N.output.storage_path }}'),
                        new FieldDefinition(key: 'filename', label: 'Filename', type: 'string', required: true),
                        new FieldDefinition(key: 'mime_type', label: 'MIME Type', type: 'string', required: true, default: 'application/octet-stream'),
                        new FieldDefinition(key: 'folder_id', label: 'Folder ID', type: 'string', required: false),
                    ],
                ),
                new ActionDefinition(
                    key: 'get_file_details',
                    label: 'Get File Details',
                    description: 'Retrieve metadata for a Drive file.',
                    labelKey: self::NS . '.actions.get_file_details.label',
                    descriptionKey: self::NS . '.actions.get_file_details.description',
                    requiredScopes: [$scope],
                    capability: 'drive',
                    fields: [
                        new FieldDefinition(key: 'file_id', label: 'File ID', type: 'string', required: true),
                    ],
                ),
                new ActionDefinition(
                    key: 'download_file',
                    label: 'Download File',
                    description: 'Download a Drive file to BrooklynAI internal storage.',
                    labelKey: self::NS . '.actions.download_file.label',
                    descriptionKey: self::NS . '.actions.download_file.description',
                    requiredScopes: [$scope],
                    capability: 'drive',
                    fields: [
                        new FieldDefinition(key: 'file_id', label: 'File ID', type: 'string', required: true),
                    ],
                ),
                new ActionDefinition(
                    key: 'update_file_metadata',
                    label: 'Update File Metadata',
                    description: 'Update description or properties of a Drive file. Does not rename or move.',
                    labelKey: self::NS . '.actions.update_file_metadata.label',
                    descriptionKey: self::NS . '.actions.update_file_metadata.description',
                    requiredScopes: [$scope],
                    capability: 'drive',
                    fields: [
                        new FieldDefinition(key: 'file_id', label: 'File ID', type: 'string', required: true),
                        new FieldDefinition(key: 'description', label: 'Description', type: 'text', required: false),
                        new FieldDefinition(key: 'properties', label: 'Properties (JSON)', type: 'string', required: false),
                    ],
                ),
                new ActionDefinition(
                    key: 'copy_file',
                    label: 'Copy File',
                    description: 'Copy a Drive file to a new file.',
                    labelKey: self::NS . '.actions.copy_file.label',
                    descriptionKey: self::NS . '.actions.copy_file.description',
                    requiredScopes: [$scope],
                    capability: 'drive',
                    fields: [
                        new FieldDefinition(key: 'file_id', label: 'File ID', type: 'string', required: true),
                        new FieldDefinition(key: 'new_name', label: 'New Name', type: 'string', required: true),
                        new FieldDefinition(key: 'folder_id', label: 'Target Folder ID', type: 'string', required: false),
                    ],
                ),
                new ActionDefinition(
                    key: 'move_file',
                    label: 'Move File',
                    description: 'Move a Drive file to a different folder.',
                    labelKey: self::NS . '.actions.move_file.label',
                    descriptionKey: self::NS . '.actions.move_file.description',
                    requiredScopes: [$scope],
                    capability: 'drive',
                    fields: [
                        new FieldDefinition(key: 'file_id', label: 'File ID', type: 'string', required: true),
                        new FieldDefinition(key: 'new_parent_id', label: 'New Parent Folder ID', type: 'string', required: true),
                    ],
                ),
                new ActionDefinition(
                    key: 'rename_file',
                    label: 'Rename File',
                    description: 'Rename a Drive file.',
                    labelKey: self::NS . '.actions.rename_file.label',
                    descriptionKey: self::NS . '.actions.rename_file.description',
                    requiredScopes: [$scope],
                    capability: 'drive',
                    fields: [
                        new FieldDefinition(key: 'file_id', label: 'File ID', type: 'string', required: true),
                        new FieldDefinition(key: 'new_name', label: 'New Name', type: 'string', required: true),
                    ],
                ),
                new ActionDefinition(
                    key: 'delete_file',
                    label: 'Delete File',
                    description: 'Permanently delete a Drive file.',
                    labelKey: self::NS . '.actions.delete_file.label',
                    descriptionKey: self::NS . '.actions.delete_file.description',
                    requiredScopes: [$scope],
                    capability: 'drive',
                    fields: [
                        new FieldDefinition(key: 'file_id', label: 'File ID', type: 'string', required: true),
                    ],
                ),
                new ActionDefinition(
                    key: 'create_folder',
                    label: 'Create Folder',
                    description: 'Create a new folder in Drive.',
                    labelKey: self::NS . '.actions.create_folder.label',
                    descriptionKey: self::NS . '.actions.create_folder.description',
                    requiredScopes: [$scope],
                    capability: 'drive',
                    fields: [
                        new FieldDefinition(key: 'name', label: 'Folder Name', type: 'string', required: true),
                        new FieldDefinition(key: 'parent_folder_id', label: 'Parent Folder ID', type: 'string', required: false),
                    ],
                ),
                new ActionDefinition(
                    key: 'list_accessible_files',
                    label: 'List Accessible Files',
                    description: 'List files accessible to BrooklynAI under drive.file scope. Not the entire Drive.',
                    labelKey: self::NS . '.actions.list_accessible_files.label',
                    descriptionKey: self::NS . '.actions.list_accessible_files.description',
                    requiredScopes: [$scope],
                    capability: 'drive',
                    fields: [
                        new FieldDefinition(key: 'folder_id', label: 'Folder ID', type: 'string', required: false),
                        new FieldDefinition(key: 'page_size', label: 'Page Size', type: 'integer', required: false, default: 100),
                    ],
                ),
                new ActionDefinition(
                    key: 'search_accessible_files',
                    label: 'Search Accessible Files',
                    description: 'Search files accessible to BrooklynAI under drive.file scope.',
                    labelKey: self::NS . '.actions.search_accessible_files.label',
                    descriptionKey: self::NS . '.actions.search_accessible_files.description',
                    requiredScopes: [$scope],
                    capability: 'drive',
                    fields: [
                        new FieldDefinition(key: 'query', label: 'Search Query', type: 'string', required: true),
                        new FieldDefinition(key: 'page_size', label: 'Page Size', type: 'integer', required: false, default: 100),
                    ],
                ),
                new ActionDefinition(
                    key: 'share_file',
                    label: 'Share File',
                    description: 'Share a Drive file with a user, group, or domain. Public sharing (type:anyone) is not permitted in this phase.',
                    labelKey: self::NS . '.actions.share_file.label',
                    descriptionKey: self::NS . '.actions.share_file.description',
                    requiredScopes: [$scope],
                    capability: 'drive',
                    fields: [
                        new FieldDefinition(key: 'file_id', label: 'File ID', type: 'string', required: true),
                        new FieldDefinition(key: 'type', label: 'Grantee Type', type: 'select', required: true, options: [
                            ['value' => 'user', 'label' => 'User'],
                            ['value' => 'group', 'label' => 'Group'],
                            ['value' => 'domain', 'label' => 'Domain'],
                        ]),
                        new FieldDefinition(key: 'role', label: 'Role', type: 'select', required: true, options: [
                            ['value' => 'reader', 'label' => 'Reader'],
                            ['value' => 'commenter', 'label' => 'Commenter'],
                            ['value' => 'writer', 'label' => 'Writer'],
                        ]),
                        new FieldDefinition(key: 'email_address', label: 'Email Address', type: 'email', required: false, description: 'Required for type=user or type=group'),
                        new FieldDefinition(key: 'domain', label: 'Domain', type: 'string', required: false, description: 'Required for type=domain'),
                    ],
                ),
                new ActionDefinition(
                    key: 'export_workspace_file',
                    label: 'Export Google Workspace Files',
                    description: 'Export a Google Workspace file (Docs/Sheets/Slides) to a downloadable format. 10 MB export limit applies.',
                    labelKey: self::NS . '.actions.export_workspace_file.label',
                    descriptionKey: self::NS . '.actions.export_workspace_file.description',
                    requiredScopes: [$scope],
                    capability: 'drive',
                    fields: [
                        new FieldDefinition(key: 'file_id', label: 'File ID', type: 'string', required: true),
                        new FieldDefinition(key: 'mime_type', label: 'Target MIME Type', type: 'string', required: true, default: 'application/pdf'),
                    ],
                ),
            ],
            triggers: [
                new TriggerDefinition(
                    key: 'file_created',
                    label: 'File Created',
                    description: 'Trigger when a new file is created in the accessible change scope.',
                    labelKey: self::NS . '.triggers.file_created.label',
                    descriptionKey: self::NS . '.triggers.file_created.description',
                    strategy: TriggerStrategy::Poll,
                    requiredScopes: [$scope],
                    capability: 'drive',
                ),
                new TriggerDefinition(
                    key: 'file_updated',
                    label: 'File Updated',
                    description: 'Trigger when an accessible file is updated.',
                    labelKey: self::NS . '.triggers.file_updated.label',
                    descriptionKey: self::NS . '.triggers.file_updated.description',
                    strategy: TriggerStrategy::Poll,
                    requiredScopes: [$scope],
                    capability: 'drive',
                ),
                new TriggerDefinition(
                    key: 'file_removed_from_access',
                    label: 'File Removed from Access',
                    description: 'Trigger when a file is removed from the change list (deletion or loss of access).',
                    labelKey: self::NS . '.triggers.file_removed_from_access.label',
                    descriptionKey: self::NS . '.triggers.file_removed_from_access.description',
                    strategy: TriggerStrategy::Poll,
                    requiredScopes: [$scope],
                    capability: 'drive',
                ),
            ],
        );
    }
}

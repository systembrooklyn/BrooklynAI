<?php

namespace App\Modules\Integrations\Infrastructure\Google;

use App\Modules\Integrations\Core\Contracts\IntegrationProvider;
use App\Modules\Integrations\Core\Entities\ActionDefinition;
use App\Modules\Integrations\Core\Entities\AuthDefinition;
use App\Modules\Integrations\Core\Entities\IntegrationDefinition;
use App\Modules\Integrations\Core\ValueObjects\IntegrationKey;
use App\Modules\Integrations\Core\ValueObjects\ProviderKey;
use App\Modules\Integrations\Core\ValueObjects\ScopeIdentifier;

final class GoogleDocsIntegration implements IntegrationProvider
{
    public function definition(): IntegrationDefinition
    {
        $documents = ScopeIdentifier::fromString('https://www.googleapis.com/auth/documents');
        $documentsReadonly = ScopeIdentifier::fromString('https://www.googleapis.com/auth/documents.readonly');
        $drive = ScopeIdentifier::fromString('https://www.googleapis.com/auth/drive');
        $driveReadonly = ScopeIdentifier::fromString('https://www.googleapis.com/auth/drive.readonly');
        $driveFile = ScopeIdentifier::fromString('https://www.googleapis.com/auth/drive.file');

        return new IntegrationDefinition(
            key: IntegrationKey::fromString('google.docs'),
            providerKey: ProviderKey::fromString('google'),
            name: 'Google Docs',
            description: 'Create and edit documents through Google Docs.',
            category: 'documents',
            auth: new AuthDefinition(
                type: 'oauth2',
                providerKey: ProviderKey::fromString('google'),
            ),
            actions: [
                new ActionDefinition(
                    key: 'create_document',
                    label: 'Create Document',
                    description: 'Create a new Google Doc.',
                    requiredScopes: [$documents, $driveFile],
                ),
                new ActionDefinition(
                    key: 'get_document',
                    label: 'Get Document',
                    description: 'Retrieve the content and metadata of a Google Doc.',
                    requiredScopes: [$documentsReadonly, $driveReadonly],
                ),
                new ActionDefinition(
                    key: 'append_text',
                    label: 'Append Text',
                    description: 'Append text to the end of a Google Doc.',
                    requiredScopes: [$documents],
                ),
                new ActionDefinition(
                    key: 'update_document',
                    label: 'Update Document',
                    description: 'Replace the entire content of a Google Doc.',
                    requiredScopes: [$documents],
                ),
                new ActionDefinition(
                    key: 'delete_document',
                    label: 'Delete Document',
                    description: 'Move a Google Doc to trash.',
                    requiredScopes: [$drive],
                ),
                new ActionDefinition(
                    key: 'download_pdf',
                    label: 'Download As PDF',
                    description: 'Export a Google Doc as a PDF.',
                    requiredScopes: [$driveReadonly],
                ),
                new ActionDefinition(
                    key: 'generate_from_template',
                    label: 'Generate From Template',
                    description: 'Create a new document by copying a template and replacing placeholders.',
                    requiredScopes: [$documents, $drive],
                ),
            ],
            triggers: [],
        );
    }
}

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

final class GmailIntegration implements IntegrationProvider
{
    public function definition(): IntegrationDefinition
    {
        return new IntegrationDefinition(
            key: IntegrationKey::fromString('google.gmail'),
            providerKey: ProviderKey::fromString('google'),
            name: 'Gmail',
            description: 'Send, receive, and manage emails through Google Gmail.',
            category: 'email',
            auth: new AuthDefinition(
                type: 'oauth2',
                providerKey: ProviderKey::fromString('google'),
            ),
            actions: [
                new ActionDefinition(
                    key: 'send_email',
                    label: 'Send Email',
                    description: 'Send a new email message.',
                    requiredScopes: [
                        ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.send'),
                    ],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(
                            key: 'to',
                            label: 'To',
                            type: 'email',
                            required: true,
                        ),
                        new FieldDefinition(
                            key: 'subject',
                            label: 'Subject',
                            type: 'string',
                            required: true,
                        ),
                        new FieldDefinition(
                            key: 'body',
                            label: 'Body',
                            type: 'text',
                            required: true,
                        ),
                    ],
                ),
                new ActionDefinition(
                    key: 'reply_to_email',
                    label: 'Reply to Email',
                    description: 'Reply to an existing email thread.',
                    requiredScopes: [
                        ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.send'),
                    ],
                    capability: 'gmail',
                ),
                new ActionDefinition(
                    key: 'create_draft',
                    label: 'Create Draft',
                    description: 'Create a new email draft without sending it.',
                    requiredScopes: [
                        ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.compose'),
                    ],
                    capability: 'gmail',
                ),
            ],
            triggers: [
                new TriggerDefinition(
                    key: 'new_email_received',
                    label: 'New Email Received',
                    description: 'Trigger when a new email is received.',
                    strategy: TriggerStrategy::Poll,
                    requiredScopes: [
                        ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.readonly'),
                    ],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(
                            key: 'label_id',
                            label: 'Label',
                            type: 'select',
                            required: false,
                            options_source: [
                                'operation_id' => 'gmail.labels',
                                'params' => ['connection_id' => '{{connection_id}}'],
                            ],
                        ),
                    ],
                ),
            ],
        );
    }
}

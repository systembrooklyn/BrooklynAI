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
                    fields: [
                        new FieldDefinition(
                            key: 'to',
                            label: 'To',
                            type: 'email',
                            required: true,
                            description: 'Recipient email address. Typically the sender of the original email: {{ trigger.from }}.',
                        ),
                        new FieldDefinition(
                            key: 'subject',
                            label: 'Subject',
                            type: 'string',
                            required: true,
                            description: 'Reply subject. Typically "Re: " followed by the original subject: Re: {{ trigger.subject }}.',
                        ),
                        new FieldDefinition(
                            key: 'body',
                            label: 'Body',
                            type: 'text',
                            required: true,
                            description: 'Reply body.',
                        ),
                        new FieldDefinition(
                            key: 'thread_id',
                            label: 'Thread ID',
                            type: 'string',
                            required: true,
                            description: 'Gmail thread ID. Typically {{ trigger.thread_id }}.',
                        ),
                    ],
                ),
                new ActionDefinition(
                    key: 'create_draft',
                    label: 'Create Draft',
                    description: 'Create a new email draft without sending it.',
                    requiredScopes: [
                        ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.compose'),
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
                    key: 'mark_as_read',
                    label: 'Mark as Read',
                    description: 'Mark an email message as read.',
                    requiredScopes: [
                        ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.modify'),
                    ],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(
                            key: 'message_id',
                            label: 'Message ID',
                            type: 'string',
                            required: true,
                            description: 'Gmail message ID. Typically {{ trigger.message_id }}.',
                        ),
                    ],
                ),
                new ActionDefinition(
                    key: 'mark_as_unread',
                    label: 'Mark as Unread',
                    description: 'Mark an email message as unread.',
                    requiredScopes: [
                        ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.modify'),
                    ],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(
                            key: 'message_id',
                            label: 'Message ID',
                            type: 'string',
                            required: true,
                            description: 'Gmail message ID. Typically {{ trigger.message_id }}.',
                        ),
                    ],
                ),
                new ActionDefinition(
                    key: 'archive',
                    label: 'Archive Email',
                    description: 'Remove an email message from the inbox.',
                    requiredScopes: [
                        ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.modify'),
                    ],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(
                            key: 'message_id',
                            label: 'Message ID',
                            type: 'string',
                            required: true,
                            description: 'Gmail message ID. Typically {{ trigger.message_id }}.',
                        ),
                    ],
                ),
                new ActionDefinition(
                    key: 'trash',
                    label: 'Move to Trash',
                    description: 'Move an email message to the trash.',
                    requiredScopes: [
                        ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.modify'),
                    ],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(
                            key: 'message_id',
                            label: 'Message ID',
                            type: 'string',
                            required: true,
                            description: 'Gmail message ID. Typically {{ trigger.message_id }}.',
                        ),
                    ],
                ),
                new ActionDefinition(
                    key: 'add_label',
                    label: 'Add Label',
                    description: 'Add a Gmail label to an email message.',
                    requiredScopes: [
                        ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.modify'),
                    ],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(
                            key: 'message_id',
                            label: 'Message ID',
                            type: 'string',
                            required: true,
                            description: 'Gmail message ID. Typically {{ trigger.message_id }}.',
                        ),
                        new FieldDefinition(
                            key: 'label_id',
                            label: 'Label',
                            type: 'select',
                            required: true,
                            description: 'Gmail label ID to add.',
                            options_source: [
                                'operation_id' => 'gmail.labels',
                                'params' => ['connection_id' => '{{connection_id}}'],
                            ],
                        ),
                    ],
                ),
                new ActionDefinition(
                    key: 'remove_label',
                    label: 'Remove Label',
                    description: 'Remove a Gmail label from an email message.',
                    requiredScopes: [
                        ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.modify'),
                    ],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(
                            key: 'message_id',
                            label: 'Message ID',
                            type: 'string',
                            required: true,
                            description: 'Gmail message ID. Typically {{ trigger.message_id }}.',
                        ),
                        new FieldDefinition(
                            key: 'label_id',
                            label: 'Label',
                            type: 'select',
                            required: true,
                            description: 'Gmail label ID to remove.',
                            options_source: [
                                'operation_id' => 'gmail.labels',
                                'params' => ['connection_id' => '{{connection_id}}'],
                            ],
                        ),
                    ],
                ),
                new ActionDefinition(
                    key: 'create_label',
                    label: 'Create Label',
                    description: 'Create a new Gmail label.',
                    requiredScopes: [
                        ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.labels'),
                    ],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(
                            key: 'name',
                            label: 'Label Name',
                            type: 'string',
                            required: true,
                            description: 'The name of the new Gmail label.',
                        ),
                    ],
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
                            description: 'Only trigger for emails with this Gmail label. Leave empty to match all mail.',
                            options_source: [
                                'operation_id' => 'gmail.labels',
                                'params' => ['connection_id' => '{{connection_id}}'],
                            ],
                        ),
                        new FieldDefinition(
                            key: 'from',
                            label: 'From',
                            type: 'string',
                            required: false,
                            description: 'Only trigger for emails whose sender matches this filter. Uses Gmail search syntax (for example user@example.com).',
                        ),
                        new FieldDefinition(
                            key: 'subject',
                            label: 'Subject',
                            type: 'string',
                            required: false,
                            description: 'Only trigger for emails whose subject matches this filter. Multi-word values are matched as a phrase.',
                        ),
                        new FieldDefinition(
                            key: 'has_attachment',
                            label: 'Has Attachment',
                            type: 'boolean',
                            required: false,
                            description: 'Only trigger for emails that carry an attachment.',
                        ),
                        new FieldDefinition(
                            key: 'query',
                            label: 'Advanced Query',
                            type: 'string',
                            required: false,
                            description: 'Optional raw Gmail search query, for example "is:unread larger:5M". Advanced users only.',
                        ),
                    ],
                ),
            ],
        );
    }
}

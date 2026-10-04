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
    private const NS = 'integrations::catalog.google_gmail';

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
            nameKey: self::NS.'.name',
            descriptionKey: self::NS.'.description',
            actions: [
                new ActionDefinition(
                    key: 'send_email',
                    label: 'Send Email',
                    description: 'Send a new email message.',
                    labelKey: self::NS.'.actions.send_email.label',
                    descriptionKey: self::NS.'.actions.send_email.description',
                    requiredScopes: [ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.send')],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(key: 'to', label: 'To', labelKey: self::NS.'.actions.send_email.fields.to.label', type: 'email', required: true),
                        new FieldDefinition(key: 'subject', label: 'Subject', labelKey: self::NS.'.actions.send_email.fields.subject.label', type: 'string', required: true),
                        new FieldDefinition(key: 'body', label: 'Body', labelKey: self::NS.'.actions.send_email.fields.body.label', type: 'text', required: true),
                    ],
                ),
                new ActionDefinition(
                    key: 'reply_to_email',
                    label: 'Reply to Email',
                    description: 'Reply to an existing email thread.',
                    labelKey: self::NS.'.actions.reply_to_email.label',
                    descriptionKey: self::NS.'.actions.reply_to_email.description',
                    requiredScopes: [ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.send')],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(
                            key: 'to', label: 'To',
                            labelKey: self::NS.'.actions.reply_to_email.fields.to.label',
                            type: 'email', required: true,
                            description: 'Recipient email address. Typically the sender of the original email: {{ trigger.from }}.',
                            descriptionKey: self::NS.'.actions.reply_to_email.fields.to.description',
                        ),
                        new FieldDefinition(
                            key: 'subject', label: 'Subject',
                            labelKey: self::NS.'.actions.reply_to_email.fields.subject.label',
                            type: 'string', required: true,
                            description: 'Reply subject. Typically "Re: " followed by the original subject: Re: {{ trigger.subject }}.',
                            descriptionKey: self::NS.'.actions.reply_to_email.fields.subject.description',
                        ),
                        new FieldDefinition(
                            key: 'body', label: 'Body',
                            labelKey: self::NS.'.actions.reply_to_email.fields.body.label',
                            type: 'text', required: true,
                            description: 'Reply body.',
                            descriptionKey: self::NS.'.actions.reply_to_email.fields.body.description',
                        ),
                        new FieldDefinition(
                            key: 'thread_id', label: 'Thread ID',
                            labelKey: self::NS.'.actions.reply_to_email.fields.thread_id.label',
                            type: 'string', required: true,
                            description: 'Gmail thread ID. Typically {{ trigger.thread_id }}.',
                            descriptionKey: self::NS.'.actions.reply_to_email.fields.thread_id.description',
                        ),
                    ],
                ),
                new ActionDefinition(
                    key: 'create_draft',
                    label: 'Create Draft',
                    description: 'Create a new email draft without sending it.',
                    labelKey: self::NS.'.actions.create_draft.label',
                    descriptionKey: self::NS.'.actions.create_draft.description',
                    requiredScopes: [ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.compose')],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(key: 'to', label: 'To', labelKey: self::NS.'.actions.create_draft.fields.to.label', type: 'email', required: true),
                        new FieldDefinition(key: 'subject', label: 'Subject', labelKey: self::NS.'.actions.create_draft.fields.subject.label', type: 'string', required: true),
                        new FieldDefinition(key: 'body', label: 'Body', labelKey: self::NS.'.actions.create_draft.fields.body.label', type: 'text', required: true),
                    ],
                ),
                new ActionDefinition(
                    key: 'mark_as_read',
                    label: 'Mark as Read',
                    description: 'Mark an email message as read.',
                    labelKey: self::NS.'.actions.mark_as_read.label',
                    descriptionKey: self::NS.'.actions.mark_as_read.description',
                    requiredScopes: [ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.modify')],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(
                            key: 'message_id', label: 'Message ID',
                            labelKey: self::NS.'.actions.mark_as_read.fields.message_id.label',
                            type: 'string', required: true,
                            description: 'Gmail message ID. Typically {{ trigger.message_id }}.',
                            descriptionKey: self::NS.'.actions.mark_as_read.fields.message_id.description',
                        ),
                    ],
                ),
                new ActionDefinition(
                    key: 'mark_as_unread',
                    label: 'Mark as Unread',
                    description: 'Mark an email message as unread.',
                    labelKey: self::NS.'.actions.mark_as_unread.label',
                    descriptionKey: self::NS.'.actions.mark_as_unread.description',
                    requiredScopes: [ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.modify')],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(
                            key: 'message_id', label: 'Message ID',
                            labelKey: self::NS.'.actions.mark_as_unread.fields.message_id.label',
                            type: 'string', required: true,
                            description: 'Gmail message ID. Typically {{ trigger.message_id }}.',
                            descriptionKey: self::NS.'.actions.mark_as_unread.fields.message_id.description',
                        ),
                    ],
                ),
                new ActionDefinition(
                    key: 'archive',
                    label: 'Archive Email',
                    description: 'Remove an email message from the inbox.',
                    labelKey: self::NS.'.actions.archive.label',
                    descriptionKey: self::NS.'.actions.archive.description',
                    requiredScopes: [ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.modify')],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(
                            key: 'message_id', label: 'Message ID',
                            labelKey: self::NS.'.actions.archive.fields.message_id.label',
                            type: 'string', required: true,
                            description: 'Gmail message ID. Typically {{ trigger.message_id }}.',
                            descriptionKey: self::NS.'.actions.archive.fields.message_id.description',
                        ),
                    ],
                ),
                new ActionDefinition(
                    key: 'trash',
                    label: 'Move to Trash',
                    description: 'Move an email message to the trash.',
                    labelKey: self::NS.'.actions.trash.label',
                    descriptionKey: self::NS.'.actions.trash.description',
                    requiredScopes: [ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.modify')],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(
                            key: 'message_id', label: 'Message ID',
                            labelKey: self::NS.'.actions.trash.fields.message_id.label',
                            type: 'string', required: true,
                            description: 'Gmail message ID. Typically {{ trigger.message_id }}.',
                            descriptionKey: self::NS.'.actions.trash.fields.message_id.description',
                        ),
                    ],
                ),
                new ActionDefinition(
                    key: 'add_label',
                    label: 'Add Label',
                    description: 'Add a Gmail label to an email message.',
                    labelKey: self::NS.'.actions.add_label.label',
                    descriptionKey: self::NS.'.actions.add_label.description',
                    requiredScopes: [ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.modify')],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(
                            key: 'message_id', label: 'Message ID',
                            labelKey: self::NS.'.actions.add_label.fields.message_id.label',
                            type: 'string', required: true,
                            description: 'Gmail message ID. Typically {{ trigger.message_id }}.',
                            descriptionKey: self::NS.'.actions.add_label.fields.message_id.description',
                        ),
                        new FieldDefinition(
                            key: 'label_id', label: 'Label',
                            labelKey: self::NS.'.actions.add_label.fields.label_id.label',
                            type: 'select', required: true,
                            description: 'Gmail label ID to add.',
                            descriptionKey: self::NS.'.actions.add_label.fields.label_id.description',
                            options_source: ['operation_id' => 'gmail.labels', 'params' => ['connection_id' => '{{connection_id}}']],
                        ),
                    ],
                ),
                new ActionDefinition(
                    key: 'remove_label',
                    label: 'Remove Label',
                    description: 'Remove a Gmail label from an email message.',
                    labelKey: self::NS.'.actions.remove_label.label',
                    descriptionKey: self::NS.'.actions.remove_label.description',
                    requiredScopes: [ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.modify')],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(
                            key: 'message_id', label: 'Message ID',
                            labelKey: self::NS.'.actions.remove_label.fields.message_id.label',
                            type: 'string', required: true,
                            description: 'Gmail message ID. Typically {{ trigger.message_id }}.',
                            descriptionKey: self::NS.'.actions.remove_label.fields.message_id.description',
                        ),
                        new FieldDefinition(
                            key: 'label_id', label: 'Label',
                            labelKey: self::NS.'.actions.remove_label.fields.label_id.label',
                            type: 'select', required: true,
                            description: 'Gmail label ID to remove.',
                            descriptionKey: self::NS.'.actions.remove_label.fields.label_id.description',
                            options_source: ['operation_id' => 'gmail.labels', 'params' => ['connection_id' => '{{connection_id}}']],
                        ),
                    ],
                ),
                new ActionDefinition(
                    key: 'create_label',
                    label: 'Create Label',
                    description: 'Create a new Gmail label.',
                    labelKey: self::NS.'.actions.create_label.label',
                    descriptionKey: self::NS.'.actions.create_label.description',
                    requiredScopes: [ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.labels')],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(
                            key: 'name', label: 'Label Name',
                            labelKey: self::NS.'.actions.create_label.fields.name.label',
                            type: 'string', required: true,
                            description: 'The name of the new Gmail label.',
                            descriptionKey: self::NS.'.actions.create_label.fields.name.description',
                        ),
                    ],
                ),
            ],
            triggers: [
                new TriggerDefinition(
                    key: 'new_email_received',
                    label: 'New Email Received',
                    description: 'Trigger when a new email is received.',
                    labelKey: self::NS.'.triggers.new_email_received.label',
                    descriptionKey: self::NS.'.triggers.new_email_received.description',
                    strategy: TriggerStrategy::Poll,
                    requiredScopes: [ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.readonly')],
                    capability: 'gmail',
                    fields: [
                        new FieldDefinition(
                            key: 'label_id', label: 'Label',
                            labelKey: self::NS.'.triggers.new_email_received.fields.label_id.label',
                            type: 'select', required: false,
                            description: 'Only trigger for emails with this Gmail label. Leave empty to match all mail.',
                            descriptionKey: self::NS.'.triggers.new_email_received.fields.label_id.description',
                            options_source: ['operation_id' => 'gmail.labels', 'params' => ['connection_id' => '{{connection_id}}']],
                        ),
                        new FieldDefinition(
                            key: 'from', label: 'From',
                            labelKey: self::NS.'.triggers.new_email_received.fields.from.label',
                            type: 'string', required: false,
                            description: 'Only trigger for emails whose sender matches this filter. Uses Gmail search syntax (for example user@example.com).',
                            descriptionKey: self::NS.'.triggers.new_email_received.fields.from.description',
                        ),
                        new FieldDefinition(
                            key: 'subject', label: 'Subject',
                            labelKey: self::NS.'.triggers.new_email_received.fields.subject.label',
                            type: 'string', required: false,
                            description: 'Only trigger for emails whose subject matches this filter. Multi-word values are matched as a phrase.',
                            descriptionKey: self::NS.'.triggers.new_email_received.fields.subject.description',
                        ),
                        new FieldDefinition(
                            key: 'has_attachment', label: 'Has Attachment',
                            labelKey: self::NS.'.triggers.new_email_received.fields.has_attachment.label',
                            type: 'boolean', required: false,
                            description: 'Only trigger for emails that carry an attachment.',
                            descriptionKey: self::NS.'.triggers.new_email_received.fields.has_attachment.description',
                        ),
                        new FieldDefinition(
                            key: 'query', label: 'Advanced Query',
                            labelKey: self::NS.'.triggers.new_email_received.fields.query.label',
                            type: 'string', required: false,
                            description: 'Optional raw Gmail search query, for example "is:unread larger:5M". Advanced users only.',
                            descriptionKey: self::NS.'.triggers.new_email_received.fields.query.description',
                        ),
                    ],
                ),
            ],
        );
    }
}

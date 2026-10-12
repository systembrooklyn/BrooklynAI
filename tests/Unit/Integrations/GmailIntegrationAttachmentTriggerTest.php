<?php

namespace Tests\Unit\Integrations;

use App\Modules\Integrations\Infrastructure\Google\GmailIntegration;
use PHPUnit\Framework\TestCase;

final class GmailIntegrationAttachmentTriggerTest extends TestCase
{
    public function test_gmail_integration_exposes_two_triggers(): void
    {
        $definition = (new GmailIntegration)->definition();

        $keys = array_map(fn ($t) => $t->key, $definition->triggers);

        $this->assertContains('new_email_received', $keys);
        $this->assertContains('new_email_received_with_attachment', $keys);
        $this->assertCount(2, $keys);
    }

    public function test_existing_trigger_is_unchanged(): void
    {
        $definition = (new GmailIntegration)->definition();

        $existing = $this->findTrigger($definition->triggers, 'new_email_received');

        $this->assertNotNull($existing);
        $this->assertSame('poll', $existing->strategy->value);
        $this->assertSame('gmail', $existing->capability);

        $fieldKeys = array_map(fn ($f) => $f->key, $existing->fields);
        $this->assertContains('has_attachment', $fieldKeys);
        $this->assertContains('label_id', $fieldKeys);
        $this->assertContains('from', $fieldKeys);
        $this->assertContains('subject', $fieldKeys);
        $this->assertContains('query', $fieldKeys);
    }

    public function test_new_trigger_has_correct_key_and_capability(): void
    {
        $definition = (new GmailIntegration)->definition();

        $new = $this->findTrigger($definition->triggers, 'new_email_received_with_attachment');

        $this->assertNotNull($new);
        $this->assertSame('new_email_received_with_attachment', $new->key);
        $this->assertSame('poll', $new->strategy->value);
        $this->assertSame('gmail', $new->capability);
    }

    public function test_new_trigger_requires_drive_file_scope(): void
    {
        $definition = (new GmailIntegration)->definition();

        $new = $this->findTrigger($definition->triggers, 'new_email_received_with_attachment');

        $scopes = array_map(fn ($s) => $s->value, $new->requiredScopes);

        $this->assertContains('https://www.googleapis.com/auth/gmail.readonly', $scopes);
        $this->assertContains('https://www.googleapis.com/auth/drive.file', $scopes);
    }

    public function test_new_trigger_does_not_expose_has_attachment_field(): void
    {
        $definition = (new GmailIntegration)->definition();

        $new = $this->findTrigger($definition->triggers, 'new_email_received_with_attachment');

        $fieldKeys = array_map(fn ($f) => $f->key, $new->fields);

        $this->assertNotContains('has_attachment', $fieldKeys);
        $this->assertContains('label_id', $fieldKeys);
        $this->assertContains('from', $fieldKeys);
        $this->assertContains('subject', $fieldKeys);
        $this->assertContains('query', $fieldKeys);
    }

    /**
     * @param  array<int, \App\Modules\Integrations\Core\Entities\TriggerDefinition>  $triggers
     */
    private function findTrigger(array $triggers, string $key): ?object
    {
        foreach ($triggers as $t) {
            if ($t->key === $key) {
                return $t;
            }
        }

        return null;
    }
}

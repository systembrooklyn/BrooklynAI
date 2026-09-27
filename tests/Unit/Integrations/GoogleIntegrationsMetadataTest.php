<?php

namespace Tests\Unit\Integrations;

use App\Modules\Integrations\Application\Services\IntegrationCatalog;
use App\Modules\Integrations\Core\Entities\ActionDefinition;
use App\Modules\Integrations\Core\Entities\TriggerDefinition;
use App\Modules\Integrations\Infrastructure\Google\GmailIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleAnalyticsIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleCalendarIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleDocsIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleDriveIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleSheetsIntegration;
use PHPUnit\Framework\TestCase;

class GoogleIntegrationsMetadataTest extends TestCase
{
    private function catalog(): IntegrationCatalog
    {
        return new IntegrationCatalog([
            new GmailIntegration,
            new GoogleCalendarIntegration,
            new GoogleSheetsIntegration,
            new GoogleDriveIntegration,
            new GoogleDocsIntegration,
            new GoogleAnalyticsIntegration,
        ]);
    }

    public function test_all_google_integrations_belong_to_the_google_provider(): void
    {
        foreach ($this->catalog()->all() as $definition) {
            $this->assertSame('google', $definition->providerKey->value);
            $this->assertSame('oauth2', $definition->auth->type);
        }
    }

    public function test_every_action_and_trigger_has_non_empty_label_and_description(): void
    {
        foreach ($this->catalog()->all() as $definition) {
            foreach ($definition->actions as $action) {
                $this->assertInstanceOf(ActionDefinition::class, $action);
                $this->assertNotSame('', trim($action->label));
                $this->assertNotSame('', trim($action->description));
                $this->assertNotSame('', trim($action->key));
            }

            foreach ($definition->triggers as $trigger) {
                $this->assertInstanceOf(TriggerDefinition::class, $trigger);
                $this->assertNotSame('', trim($trigger->label));
                $this->assertNotSame('', trim($trigger->description));
                $this->assertNotSame('', trim($trigger->key));
            }
        }
    }

    public function test_every_declared_scope_uses_full_uri_form(): void
    {
        foreach ($this->catalog()->all() as $definition) {
            $allScopes = [];
            foreach ($definition->actions as $action) {
                foreach ($action->requiredScopes as $scope) {
                    $allScopes[] = $scope->value;
                }
            }
            foreach ($definition->triggers as $trigger) {
                foreach ($trigger->requiredScopes as $scope) {
                    $allScopes[] = $scope->value;
                }
            }

            foreach ($allScopes as $scope) {
                $this->assertStringStartsWith(
                    'https://www.googleapis.com/auth/',
                    $scope,
                    "Scope {$scope} does not use full URI form."
                );
            }
        }
    }

    public function test_gmail_declares_documented_actions_and_trigger(): void
    {
        $gmail = $this->catalog()->require('google.gmail');

        $actionKeys = array_map(static fn ($a) => $a->key, $gmail->actions);
        sort($actionKeys);
        $this->assertSame(['create_draft', 'reply_to_email', 'send_email'], $actionKeys);

        $triggerKeys = array_map(static fn ($t) => $t->key, $gmail->triggers);
        $this->assertSame(['new_email_received'], $triggerKeys);
    }

    public function test_calendar_declares_expected_actions(): void
    {
        $calendar = $this->catalog()->require('google.calendar');

        $actionKeys = array_map(static fn ($a) => $a->key, $calendar->actions);
        sort($actionKeys);

        $this->assertSame(
            ['create_event', 'delete_event', 'get_event', 'list_events', 'update_event'],
            $actionKeys
        );
    }

    public function test_sheets_declares_expected_actions(): void
    {
        $sheets = $this->catalog()->require('google.sheets');

        $actionKeys = array_map(static fn ($a) => $a->key, $sheets->actions);
        sort($actionKeys);

        $this->assertSame(
            [
                'add_sheet',
                'append_data',
                'append_row_by_headers',
                'clear_data',
                'delete_sheet',
                'get_data',
                'get_spreadsheet',
                'list_spreadsheets',
                'update_data',
            ],
            $actionKeys
        );
    }

    public function test_docs_declares_expected_actions(): void
    {
        $docs = $this->catalog()->require('google.docs');

        $actionKeys = array_map(static fn ($a) => $a->key, $docs->actions);
        sort($actionKeys);

        $this->assertSame(
            [
                'append_text',
                'create_document',
                'delete_document',
                'download_pdf',
                'generate_from_template',
                'get_document',
                'update_document',
            ],
            $actionKeys
        );
    }

    public function test_analytics_declares_expected_actions(): void
    {
        $analytics = $this->catalog()->require('google.analytics');

        $actionKeys = array_map(static fn ($a) => $a->key, $analytics->actions);
        sort($actionKeys);

        $this->assertSame(
            [
                'get_home_screen_metrics',
                'get_realtime_overview',
                'get_report',
                'get_top_pages_by_views',
                'list_properties',
            ],
            $actionKeys
        );
    }

    public function test_drive_remains_metadata_only(): void
    {
        $drive = $this->catalog()->require('google.drive');

        $this->assertSame([], $drive->actions);
        $this->assertSame([], $drive->triggers);
        $this->assertNotSame('', trim($drive->description));
    }

    public function test_every_capability_declares_at_least_one_required_scope(): void
    {
        foreach ($this->catalog()->all() as $definition) {
            foreach ($definition->actions as $action) {
                $this->assertNotEmpty(
                    $action->requiredScopes,
                    "Action {$definition->key->value}.{$action->key} has no required scopes."
                );
            }
            foreach ($definition->triggers as $trigger) {
                $this->assertNotEmpty(
                    $trigger->requiredScopes,
                    "Trigger {$definition->key->value}.{$trigger->key} has no required scopes."
                );
            }
        }
    }
}

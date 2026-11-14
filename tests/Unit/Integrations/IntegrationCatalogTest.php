<?php

namespace Tests\Unit\Integrations;

use App\Modules\Integrations\Application\Services\IntegrationCatalog;
use App\Modules\Integrations\Core\Contracts\IntegrationProvider;
use App\Modules\Integrations\Core\Entities\AuthDefinition;
use App\Modules\Integrations\Core\Entities\IntegrationDefinition;
use App\Modules\Integrations\Core\Exceptions\DuplicateIntegrationKey;
use App\Modules\Integrations\Core\ValueObjects\IntegrationKey;
use App\Modules\Integrations\Core\ValueObjects\ProviderKey;
use App\Modules\Integrations\Infrastructure\Google\GmailIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleAnalyticsIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleCalendarIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleDocsIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleDriveIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleSheetsIntegration;
use PHPUnit\Framework\TestCase;

class IntegrationCatalogTest extends TestCase
{
    public function test_empty_catalog_has_no_definitions(): void
    {
        $catalog = new IntegrationCatalog([]);

        $this->assertSame([], $catalog->all());
    }

    public function test_catalog_exposes_a_registered_definition(): void
    {
        $catalog = new IntegrationCatalog([new GmailIntegration]);

        $this->assertTrue($catalog->has('google.gmail'));

        $definition = $catalog->find('google.gmail');
        $this->assertNotNull($definition);
        $this->assertSame('Gmail', $definition->name);
    }

    public function test_catalog_returns_null_for_unknown_key(): void
    {
        $catalog = new IntegrationCatalog([new GmailIntegration]);

        $this->assertNull($catalog->find('google.unknown'));
        $this->assertFalse($catalog->has('google.unknown'));
    }

    public function test_catalog_rejects_duplicate_keys(): void
    {
        $this->expectException(DuplicateIntegrationKey::class);

        new IntegrationCatalog([
            $this->fakeProvider('google.gmail'),
            $this->fakeProvider('google.gmail'),
        ]);
    }

    public function test_standard_google_integrations_are_registered(): void
    {
        $catalog = new IntegrationCatalog([
            new GmailIntegration,
            new GoogleCalendarIntegration,
            new GoogleSheetsIntegration,
            new GoogleDriveIntegration,
            new GoogleDocsIntegration,
            new GoogleAnalyticsIntegration,
        ]);

        $expectedKeys = [
            'google.gmail',
            'google.calendar',
            'google.sheets',
            'google.drive',
            'google.docs',
            'google.analytics',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertTrue($catalog->has($key), "Expected {$key} to be registered.");
        }

        $this->assertCount(6, $catalog->all());
    }

    /**
     * Phase 1 — Gmail exposes only send_email, and no triggers.
     * See GOOGLE_INTEGRATIONS_MASTER_PLAN §3.
     */
    public function test_gmail_exposes_only_send_email_action(): void
    {
        $catalog = new IntegrationCatalog([new GmailIntegration]);
        $gmail = $catalog->find('google.gmail');

        $this->assertNotNull($gmail);
        $this->assertSame(['send_email'], array_map(static fn ($a) => $a->key, $gmail->actions));
    }

    public function test_gmail_has_zero_triggers(): void
    {
        $catalog = new IntegrationCatalog([new GmailIntegration]);
        $gmail = $catalog->find('google.gmail');

        $this->assertNotNull($gmail);
        $this->assertSame([], $gmail->triggers);
    }

    public function test_require_returns_definition_for_registered_key(): void
    {
        $catalog = new IntegrationCatalog([new GmailIntegration]);

        $definition = $catalog->require('google.gmail');

        $this->assertSame('Gmail', $definition->name);
    }

    public function test_require_throws_for_unknown_key(): void
    {
        $catalog = new IntegrationCatalog([new GmailIntegration]);

        $this->expectException(\App\Modules\Integrations\Core\Exceptions\IntegrationNotFound::class);

        $catalog->require('google.unknown');
    }

    private function fakeProvider(string $integrationKey): IntegrationProvider
    {
        return new class($integrationKey) implements IntegrationProvider
        {
            public function __construct(private readonly string $key) {}

            public function definition(): IntegrationDefinition
            {
                return new IntegrationDefinition(
                    key: IntegrationKey::fromString($this->key),
                    providerKey: ProviderKey::fromString('test'),
                    name: 'Test',
                    description: 'Test integration.',
                    category: 'test',
                    auth: new AuthDefinition(
                        type: 'none',
                        providerKey: ProviderKey::fromString('test'),
                    ),
                );
            }
        };
    }
}

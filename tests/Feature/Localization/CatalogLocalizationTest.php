<?php

namespace Tests\Feature\Localization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CatalogLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_human_readable_labels_localize_to_arabic(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->withHeader('Accept-Language', 'ar')->getJson('/api/catalog');
        $response->assertOk();

        // Phase 1: using Gmail's send_email action (still active) to verify
        // the Integrations module localization remains functional.
        $gmail = collect($response->json('data.integrations'))
            ->firstWhere('integration_key', 'google.gmail');

        $this->assertNotNull($gmail);
        $this->assertSame('إرسال بريد إلكتروني', collect($gmail['actions'])
            ->firstWhere('action_key', 'send_email')['label']);
    }

    public function test_catalog_human_readable_labels_are_english_by_default(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->withHeader('Accept-Language', 'en')->getJson('/api/catalog');
        $response->assertOk();

        $gmail = collect($response->json('data.integrations'))
            ->firstWhere('integration_key', 'google.gmail');

        $this->assertSame('Gmail', $gmail['name']);
        $this->assertSame('Send Email', collect($gmail['actions'])
            ->firstWhere('action_key', 'send_email')['label']);
    }

    public function test_machine_readable_catalog_identifiers_are_stable(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $en = $this->withHeader('Accept-Language', 'en')->getJson('/api/catalog')->json('data.integrations');
        $ar = $this->withHeader('Accept-Language', 'ar')->getJson('/api/catalog')->json('data.integrations');

        $enKeys = array_map(fn ($i) => $i['integration_key'], $en);
        $arKeys = array_map(fn ($i) => $i['integration_key'], $ar);
        $this->assertSame($enKeys, $arKeys);

        $enGmail = collect($en)->firstWhere('integration_key', 'google.gmail');
        $arGmail = collect($ar)->firstWhere('integration_key', 'google.gmail');

        // Phase 1: only send_email remains; verify its identifier and
        // capability are byte-identical across locales.
        $this->assertSame(
            array_column($enGmail['actions'], 'action_key'),
            array_column($arGmail['actions'], 'action_key'),
        );
        $this->assertSame(
            array_column($enGmail['actions'], 'capability'),
            array_column($arGmail['actions'], 'capability'),
        );

        // Phase 1: verify Gmail has no triggers in either locale.
        $this->assertSame([], $enGmail['triggers']);
        $this->assertSame([], $arGmail['triggers']);
    }

    public function test_catalog_field_keys_are_stable_across_locales(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $en = $this->withHeader('Accept-Language', 'en')->getJson('/api/catalog')->json('data.integrations');
        $ar = $this->withHeader('Accept-Language', 'ar')->getJson('/api/catalog')->json('data.integrations');

        $enGmail = collect($en)->firstWhere('integration_key', 'google.gmail');
        $arGmail = collect($ar)->firstWhere('integration_key', 'google.gmail');

        // Phase 1: only send_email exists; verify its config field keys
        // are byte-identical across locales.
        $enSend = collect($enGmail['actions'])->firstWhere('action_key', 'send_email');
        $arSend = collect($arGmail['actions'])->firstWhere('action_key', 'send_email');

        $this->assertSame(
            array_keys((array) $enSend['config']),
            array_keys((array) $arSend['config']),
        );
    }
}

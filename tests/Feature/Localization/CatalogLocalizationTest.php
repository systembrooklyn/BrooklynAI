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

        $gmail = collect($response->json('data.integrations'))
            ->firstWhere('integration_key', 'google.gmail');

        $this->assertNotNull($gmail);
        $this->assertSame('إرسال بريد إلكتروني', collect($gmail['actions'])
            ->firstWhere('action_key', 'send_email')['label']);

        $this->assertSame('استلام بريد إلكتروني جديد', collect($gmail['triggers'])
            ->firstWhere('trigger_key', 'new_email_received')['label']);
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
        $this->assertSame('New Email Received', collect($gmail['triggers'])
            ->firstWhere('trigger_key', 'new_email_received')['label']);
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

        $this->assertSame(
            array_column($enGmail['actions'], 'action_key'),
            array_column($arGmail['actions'], 'action_key'),
        );
        $this->assertSame(
            array_column($enGmail['actions'], 'capability'),
            array_column($arGmail['actions'], 'capability'),
        );
        $this->assertSame(
            array_column($enGmail['triggers'], 'trigger_key'),
            array_column($arGmail['triggers'], 'trigger_key'),
        );
    }

    public function test_catalog_field_keys_are_stable_across_locales(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $en = $this->withHeader('Accept-Language', 'en')->getJson('/api/catalog')->json('data.integrations');
        $ar = $this->withHeader('Accept-Language', 'ar')->getJson('/api/catalog')->json('data.integrations');

        $enGmail = collect($en)->firstWhere('integration_key', 'google.gmail');
        $arGmail = collect($ar)->firstWhere('integration_key', 'google.gmail');

        $enSend = collect($enGmail['actions'])->firstWhere('action_key', 'send_email');
        $arSend = collect($arGmail['actions'])->firstWhere('action_key', 'send_email');

        $this->assertSame(
            array_keys((array) $enSend['config']),
            array_keys((array) $arSend['config']),
        );

        $enTrigger = collect($enGmail['triggers'])->firstWhere('trigger_key', 'new_email_received');
        $arTrigger = collect($arGmail['triggers'])->firstWhere('trigger_key', 'new_email_received');

        $this->assertSame(
            array_keys((array) $enTrigger['config']),
            array_keys((array) $arTrigger['config']),
        );
    }
}

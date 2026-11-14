<?php

namespace Tests\Feature\Localization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class IntegrationsLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_message_is_localized_to_arabic(): void
    {
        // Phase 1: /api/gmail/labels is disabled. The catalog endpoint remains
        // the canonical test of Integrations module localization because its
        // message field is driven by __(integrations::messages.catalog_retrieved).
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->withHeader('Accept-Language', 'ar')
            ->getJson('/api/catalog');

        $response->assertOk();
        $response->assertJson(['message' => 'تم استرجاع الكتالوج بنجاح']);
    }

    public function test_catalog_message_is_english_by_default(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/catalog');

        $response->assertOk();
        $response->assertJson(['message' => 'Catalog retrieved successfully']);
    }

    public function test_response_structure_is_identical_across_locales(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $en = $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/catalog');
        $ar = $this->withHeader('Accept-Language', 'ar')
            ->getJson('/api/catalog');

        $this->assertSame($en->getStatusCode(), $ar->getStatusCode());
        $this->assertSame(array_keys($en->json()), array_keys($ar->json()));
    }
}

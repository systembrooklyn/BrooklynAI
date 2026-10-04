<?php

namespace Tests\Feature\Localization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class IntegrationsLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_controller_level_error_is_localized_to_arabic(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // GET /api/gmail/labels with a non-existent connection_id triggers
        // the shared ConnectionNotFoundException branch of ListGmailLabelsController.
        $response = $this->withHeader('Accept-Language', 'ar')
            ->getJson('/api/gmail/labels?connection_id=999999');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'الحساب غير موجود']);
    }

    public function test_controller_level_error_is_english_by_default(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/gmail/labels?connection_id=999999');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Connection not found']);
    }

    public function test_response_structure_is_identical_across_locales(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $en = $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/gmail/labels?connection_id=999999');
        $ar = $this->withHeader('Accept-Language', 'ar')
            ->getJson('/api/gmail/labels?connection_id=999999');

        $this->assertSame($en->getStatusCode(), $ar->getStatusCode());
        $this->assertSame(array_keys($en->json()), array_keys($ar->json()));
    }
}

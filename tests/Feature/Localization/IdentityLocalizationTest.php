<?php

namespace Tests\Feature\Localization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class IdentityLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_success_message_is_english_under_accept_language_en(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('secret'),
        ]);

        $response = $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'secret',
            ]);

        $response->assertOk();
        $response->assertJson([
            'message' => 'Login successful.',
        ]);
        $response->assertJsonStructure([
            'message',
            'data' => ['token', 'user' => ['id', 'name', 'email']],
        ]);
    }

    public function test_login_success_message_is_arabic_under_accept_language_ar(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('secret'),
        ]);

        $response = $this->withHeader('Accept-Language', 'ar')
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'secret',
            ]);

        $response->assertOk();
        $response->assertJson([
            'message' => 'تم تسجيل الدخول بنجاح.',
        ]);
        $response->assertJsonStructure([
            'message',
            'data' => ['token', 'user' => ['id', 'name', 'email']],
        ]);
    }

    public function test_invalid_credentials_message_is_english_under_accept_language_en(): void
    {
        $response = $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong',
            ]);

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Invalid credentials.',
        ]);
        $response->assertJsonMissingPath('data');
    }

    public function test_invalid_credentials_message_is_arabic_under_accept_language_ar(): void
    {
        $response = $this->withHeader('Accept-Language', 'ar')
            ->postJson('/api/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong',
            ]);

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'بيانات الاعتماد غير صالحة.',
        ]);
        $response->assertJsonMissingPath('data');
    }

    public function test_response_status_and_structure_are_identical_across_locales(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('secret'),
        ]);

        $en = $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'secret']);

        // Refresh the user's tokens so the second call issues a fresh one.
        $user->tokens()->delete();

        $ar = $this->withHeader('Accept-Language', 'ar')
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'secret']);

        $this->assertSame($en->getStatusCode(), $ar->getStatusCode());
        $this->assertSame(
            array_keys($en->json()),
            array_keys($ar->json()),
        );
        $this->assertSame(
            array_keys($en->json('data')),
            array_keys($ar->json('data')),
        );
        $this->assertSame(
            array_keys($en->json('data.user')),
            array_keys($ar->json('data.user')),
        );
    }

    public function test_machine_readable_user_id_is_identical_across_locales(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('secret'),
        ]);

        $en = $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'secret']);

        $user->tokens()->delete();

        $ar = $this->withHeader('Accept-Language', 'ar')
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'secret']);

        $this->assertSame($en->json('data.user.id'), $ar->json('data.user.id'));
        $this->assertSame($en->json('data.user.email'), $ar->json('data.user.email'));
    }
}

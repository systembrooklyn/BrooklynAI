<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\Fakes\MocksSocialiteGoogleUser;
use Tests\TestCase;

class GoogleAuthCallbackTest extends TestCase
{
    use MocksSocialiteGoogleUser;
    use RefreshDatabase;

    public function test_callback_redirects_trashed_user_to_account_deleted_page(): void
    {
        $user = User::factory()->create([
            'email' => 'trashed@example.com',
            'has_bot_access' => true,
            'access_expiry' => now()->addMonth(),
        ]);
        $user->delete();

        $this->mockSocialiteGoogleUser(['email' => 'trashed@example.com']);

        $response = $this->get('/api/auth/google/callback?code=fake-code');

        $response->assertStatus(302);
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringEndsWith('/account-deleted.html', $location);
    }

    public function test_callback_redirects_user_without_bot_access_to_null_token(): void
    {
        User::factory()->create([
            'email' => 'nobot@example.com',
            'has_bot_access' => false,
            'access_expiry' => now()->subMonth(),
        ]);

        $this->mockSocialiteGoogleUser(['email' => 'nobot@example.com']);

        $response = $this->get('/api/auth/google/callback?code=fake-code');

        $response->assertRedirect('https://www.aibrooklyn.net?token=null');
    }

    public function test_callback_redirects_nonexistent_user_to_null_token(): void
    {
        $this->mockSocialiteGoogleUser(['email' => 'nobody@example.com']);

        $response = $this->get('/api/auth/google/callback?code=fake-code');

        $response->assertRedirect('https://www.aibrooklyn.net?token=null');
    }

    public function test_callback_updates_existing_user_and_redirects_with_token(): void
    {
        $user = User::factory()->create([
            'email' => 'authorized@example.com',
            'has_bot_access' => true,
            'access_expiry' => now()->addMonth(),
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);

        $this->mockSocialiteGoogleUser([
            'email' => 'authorized@example.com',
            'id' => 'google-id-999',
            'avatar' => 'https://example.com/new-avatar.png',
            'token' => 'new-access-token',
            'refreshToken' => 'new-refresh-token',
        ]);

        $response = $this->get('/api/auth/google/callback?code=fake-code');

        $response->assertStatus(302);
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringStartsWith('https://www.aibrooklyn.net?token=', $location);
        $this->assertNotSame('https://www.aibrooklyn.net?token=null', $location);

        $user->refresh();
        $this->assertSame('new-access-token', $user->google_access_token);
        $this->assertSame('new-refresh-token', $user->google_refresh_token);
        $this->assertSame('google-id-999', $user->google_id);
    }

    public function test_callback_returns_500_on_socialite_exception(): void
    {
        $this->mockSocialiteGoogleUserException(new RuntimeException('Simulated OAuth failure'));

        $response = $this->get('/api/auth/google/callback?code=fake-code');

        $response->assertStatus(500);
        $response->assertJson([
            'error' => 'Login failed',
        ]);
    }
}

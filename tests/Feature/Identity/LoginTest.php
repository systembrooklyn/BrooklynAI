<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Fakes\MocksSocialiteGoogleUser;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use MocksSocialiteGoogleUser;
    use RefreshDatabase;

    public function test_valid_credentials_return_token_and_user(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Login successful.');
        $response->assertJsonPath('data.user.id', $user->id);
        $response->assertJsonPath('data.user.email', 'user@example.com');
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_wrong_password_returns_generic_401(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(401);
        $response->assertExactJson(['message' => 'Invalid credentials.']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_nonexistent_email_returns_same_generic_401(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ]);

        $response->assertStatus(401);
        $response->assertExactJson(['message' => 'Invalid credentials.']);
    }

    public function test_missing_fields_return_422(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_invalid_email_format_returns_422(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'not-an-email',
            'password' => 'whatever',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_soft_deleted_user_returns_generic_401(): void
    {
        $user = User::factory()->create([
            'email' => 'gone@example.com',
            'password' => Hash::make('secret123'),
        ]);
        $user->delete();

        $response = $this->postJson('/api/login', [
            'email' => 'gone@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(401);
        $response->assertExactJson(['message' => 'Invalid credentials.']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // public function test_user_without_password_returns_generic_401(): void
    // {
    //     User::factory()->create([
    //         'email' => 'nopass@example.com',
    //         'password' => null,
    //     ]);

    //     $response = $this->postJson('/api/login', [
    //         'email' => 'nopass@example.com',
    //         'password' => 'anything',
    //     ]);

    //     $response->assertStatus(401);
    //     $response->assertExactJson(['message' => 'Invalid credentials.']);
    // }

    public function test_register_then_login_resolves_same_user(): void
    {
        $register = $this->postJson('/api/register', [
            'name' => 'Provisioned User',
            'email' => 'provisioned@example.com',
            'password' => 'secret123',
            'access_expiry' => now()->addYear()->toDateString(),
        ]);

        $register->assertStatus(201);
        $createdId = (int) $register->json('data.id');

        $login = $this->postJson('/api/login', [
            'email' => 'provisioned@example.com',
            'password' => 'secret123',
        ]);

        $login->assertStatus(200);
        $this->assertSame($createdId, (int) $login->json('data.user.id'));
        $this->assertSame(1, User::where('email', 'provisioned@example.com')->count());
    }

    public function test_register_response_still_has_no_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Provisioned User',
            'email' => 'provisioned2@example.com',
            'password' => 'secret123',
            'access_expiry' => now()->addYear()->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertNull($response->json('token'));
        $this->assertNull($response->json('data.token'));
    }

    public function test_login_authenticates_pre_provisioned_user_with_google_identity(): void
    {
        $user = User::factory()->create([
            'email' => 'linked@example.com',
            'password' => Hash::make('secret123'),
            'google_id' => null,
        ]);

        $this->mockSocialiteGoogleUser(['email' => 'linked@example.com']);
        $this->get('/api/auth/google/callback?code=fake-code')->assertStatus(302);

        $login = $this->postJson('/api/login', [
            'email' => 'linked@example.com',
            'password' => 'secret123',
        ]);

        $login->assertStatus(200);
        $this->assertSame((int) $user->id, (int) $login->json('data.user.id'));
        $this->assertSame(1, User::where('email', 'linked@example.com')->count());
    }

    public function test_login_does_not_modify_has_bot_access(): void
    {
        $user = User::factory()->create([
            'email' => 'access@example.com',
            'password' => Hash::make('secret123'),
            'has_bot_access' => false,
        ]);

        $this->postJson('/api/login', [
            'email' => 'access@example.com',
            'password' => 'secret123',
        ])->assertStatus(200);

        $this->assertFalse((bool) $user->fresh()->has_bot_access);
    }

    public function test_login_does_not_modify_access_expiry(): void
    {
        $originalExpiry = now()->subDay()->toDateString();

        $user = User::factory()->create([
            'email' => 'expiry@example.com',
            'password' => Hash::make('secret123'),
            'access_expiry' => $originalExpiry,
        ]);

        $this->postJson('/api/login', [
            'email' => 'expiry@example.com',
            'password' => 'secret123',
        ])->assertStatus(200);

        $this->assertSame(
            $originalExpiry,
            (string) $user->fresh()->access_expiry,
        );
    }

    public function test_login_succeeds_with_master_password_for_user_without_stored_password(): void
    {
        config(['auth.master_password' => 'the-master-password']);

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => '',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'the-master-password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Login successful.');
        $response->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_login_rejects_user_without_stored_password_and_wrong_password(): void
    {
        config(['auth.master_password' => 'the-master-password']);

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => '',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'not-the-master-password',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Invalid credentials.');
        $response->assertJsonMissing(['token']);
    }

    public function test_login_rejects_master_password_when_config_is_null(): void
    {
        config(['auth.master_password' => null]);

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => '',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'anything-goes-here',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_login_response_does_not_contain_master_password(): void
    {
        config(['auth.master_password' => 'the-master-password']);

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => '',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'the-master-password',
        ]);

        $response->assertOk();

        $body = $response->getContent();

        $this->assertStringNotContainsString('the-master-password', $body);
        $this->assertStringNotContainsString('master_password', $body);
        $this->assertStringNotContainsString('LOGIN_MASTER_PASSWORD', $body);
    }

    public function test_login_succeeds_with_user_password_when_master_password_also_configured(): void
    {
        config(['auth.master_password' => 'master-secret-that-is-not-used']);

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Login successful.');
        $response->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_login_succeeds_with_correct_master_password(): void
    {
        config(['auth.master_password' => 'the-master-password']);

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'the-master-password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Login successful.');
        $response->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_login_rejects_wrong_password_and_wrong_master_password(): void
    {
        config(['auth.master_password' => 'the-master-password']);

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'neither-correct-nor-master',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Invalid credentials.');
        $response->assertJsonMissing(['token']);
    }

    public function test_login_rejects_nonexistent_email_with_correct_master_password(): void
    {
        config(['auth.master_password' => 'the-master-password']);

        $response = $this->postJson('/api/login', [
            'email' => 'does-not-exist@example.com',
            'password' => 'the-master-password',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Invalid credentials.');
        $response->assertJsonMissing(['token']);
    }

    public function test_login_rejects_soft_deleted_user_with_correct_master_password(): void
    {
        config(['auth.master_password' => 'the-master-password']);

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('correct-password'),
        ]);
        $user->delete();

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'the-master-password',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Invalid credentials.');
        $response->assertJsonMissing(['token']);
    }

    public function test_login_rejects_master_password_when_config_is_empty_string(): void
    {
        config(['auth.master_password' => '']);

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => '',
        ]);

        $response->assertStatus(422);
    }
}

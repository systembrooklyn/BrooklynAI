<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Notifications\PasswordResetOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_returns_generic_message_for_existing_user(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/password/forgot', [
            'email' => 'user@example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'message' => "If that email exists, we've sent a reset code.",
        ]);

        Notification::assertSentTo($user, PasswordResetOtpNotification::class);
    }

    public function test_forgot_returns_generic_message_for_unknown_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/password/forgot', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'message' => "If that email exists, we've sent a reset code.",
        ]);

        Notification::assertNothingSent();
    }

    public function test_reset_with_valid_code_changes_password_and_revokes_tokens(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $user->createToken('test-device');
        $this->assertSame(1, $user->tokens()->count());

        $this->postJson('/api/password/forgot', ['email' => 'user@example.com'])
            ->assertOk();

        $code = null;
        Notification::assertSentTo($user, PasswordResetOtpNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;
            return true;
        });

        $this->assertNotNull($code);

        $this->postJson('/api/password/reset', [
            'email' => 'user@example.com',
            'code' => $code,
            'password' => 'new-password',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_reset_with_wrong_code_fails(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->postJson('/api/password/forgot', ['email' => 'user@example.com']);

        $response = $this->postJson('/api/password/reset', [
            'email' => 'user@example.com',
            'code' => '000000',
            'password' => 'new-password',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'The reset code is invalid or has expired.',
        ]);
    }

    public function test_reset_with_expired_code_fails(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->postJson('/api/password/forgot', ['email' => 'user@example.com']);

        \App\Modules\Identity\Infrastructure\Eloquent\PasswordResetOtpModel::query()
            ->where('email', 'user@example.com')
            ->update(['expires_at' => now()->subMinutes(1)]);

        $response = $this->postJson('/api/password/reset', [
            'email' => 'user@example.com',
            'code' => '123456',
            'password' => 'new-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_reset_after_five_wrong_attempts_rejects_correct_code(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->postJson('/api/password/forgot', ['email' => 'user@example.com']);

        $code = null;
        Notification::assertSentTo($user, PasswordResetOtpNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;
            return true;
        });

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/password/reset', [
                'email' => 'user@example.com',
                'code' => '000000',
                'password' => 'new-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/password/reset', [
            'email' => 'user@example.com',
            'code' => $code,
            'password' => 'new-password',
        ])->assertStatus(422);
    }
}

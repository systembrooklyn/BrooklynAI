<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_new_user_with_bot_access_true(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'access_expiry' => now()->addYear()->toDateString(),
        ]);

        $response->assertStatus(201);
        $response->assertJson(['message' => 'User registered successfully']);

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'has_bot_access' => true,
        ]);
    }

    public function test_register_creates_new_user_with_bot_access_false(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Expired User',
            'email' => 'expired@example.com',
            'password' => 'password123',
            'access_expiry' => now()->subYear()->toDateString(),
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'expired@example.com',
            'has_bot_access' => false,
        ]);
    }

    public function test_register_updates_existing_user_and_returns_200(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Ignored',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'access_expiry' => now()->addYear()->toDateString(),
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'User updated Successfully']);
    }

    public function test_register_requires_name(): void
    {
        $response = $this->postJson('/api/register', [
            'email' => 'x@example.com',
            'password' => 'password123',
            'access_expiry' => now()->addMonth()->toDateString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_register_requires_password_min_6(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'abc',
            'access_expiry' => now()->addMonth()->toDateString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_register_requires_valid_email(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'X',
            'email' => 'not-an-email',
            'password' => 'password123',
            'access_expiry' => now()->addMonth()->toDateString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_register_allows_null_st_num(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'X',
            'email' => 'nostnum@example.com',
            'password' => 'password123',
            'access_expiry' => now()->addMonth()->toDateString(),
        ]);

        $response->assertStatus(201);
    }

    public function test_register_requires_access_expiry(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['access_expiry']);
    }
}

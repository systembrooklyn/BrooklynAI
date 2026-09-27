<?php

namespace Tests\Support\Fakes;

use Laravel\Socialite\Two\User as SocialiteUser;

class FakeGoogleUser
{
    public static function make(array $attributes = []): SocialiteUser
    {
        $user = new SocialiteUser();

        $user->id = $attributes['id'] ?? 'google-user-id-123';
        $user->name = $attributes['name'] ?? 'Test Google User';
        $user->email = $attributes['email'] ?? 'test-google@example.com';
        $user->avatar = $attributes['avatar'] ?? 'https://example.com/avatar.png';
        $user->token = $attributes['token'] ?? 'fake-google-access-token';
        $user->refreshToken = $attributes['refreshToken'] ?? 'fake-google-refresh-token';
        $user->expiresIn = $attributes['expiresIn'] ?? 3600;

        return $user;
    }
}

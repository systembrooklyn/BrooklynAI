<?php

namespace Tests\Support\Fakes;

use Laravel\Socialite\Facades\Socialite;
use Mockery;

trait MocksSocialiteGoogleUser
{
    protected function mockSocialiteGoogleUser(array $userAttributes = []): \Laravel\Socialite\Two\User
    {
        $googleUser = FakeGoogleUser::make($userAttributes);

        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        return $googleUser;
    }

    protected function mockSocialiteGoogleUserException(\Throwable $exception): void
    {
        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andThrow($exception);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}

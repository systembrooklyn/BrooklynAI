<?php

namespace Tests\Feature\Connections;

use App\Models\User;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Connections\Core\Exceptions\GoogleCredentialsUnavailableException;
use App\Modules\Connections\Core\Repositories\ConnectionRepository;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Connections\Infrastructure\Google\GoogleCredentialResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleCredentialsResolverTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, array<string, mixed>>  $queuedResponses
     */
    private function resolver(array $queuedResponses = []): GoogleCredentialResolver
    {
        $repository = $this->app->make(ConnectionRepository::class);

        return new class($repository, $queuedResponses) extends GoogleCredentialResolver
        {
            /** @var array<int, array<string, mixed>> */
            public array $queued;

            /** @var array<int, string> */
            public array $seenRefreshTokens = [];

            public function __construct(ConnectionRepository $repository, array $queued)
            {
                parent::__construct($repository);
                $this->queued = $queued;
            }

            protected function refreshWithGoogle(string $refreshToken): array
            {
                $this->seenRefreshTokens[] = $refreshToken;

                if (empty($this->queued)) {
                    return [];
                }

                return array_shift($this->queued);
            }
        };
    }

    // ---------- MODE A : legacy-only ----------

    public function test_mode_a_returns_existing_non_expired_legacy_credentials(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);

        $credentials = $this->resolver()->resolve($user->id, null);

        $this->assertSame('legacy-access', $credentials->accessToken);
        $this->assertSame('legacy-refresh', $credentials->refreshToken);
        $this->assertNotNull($credentials->expiresAt);
    }

    public function test_mode_a_refreshes_expired_legacy_credentials_and_updates_user(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-old',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->subMinute(),
        ]);

        $resolver = $this->resolver([
            ['access_token' => 'legacy-new', 'expires_in' => 3600],
        ]);

        $credentials = $resolver->resolve($user->id, null);

        $this->assertSame('legacy-new', $credentials->accessToken);
        $this->assertSame('legacy-refresh', $credentials->refreshToken);
        $this->assertSame(['legacy-refresh'], $resolver->seenRefreshTokens);

        $user->refresh();
        $this->assertSame('legacy-new', $user->google_access_token);
    }

    public function test_mode_a_throws_when_no_legacy_credentials_exist(): void
    {
        $user = User::factory()->create([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);

        $this->expectException(GoogleCredentialsUnavailableException::class);

        $this->resolver()->resolve($user->id, null);
    }

    public function test_mode_a_throws_when_expired_and_refresh_returns_no_token(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-old',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->subMinute(),
        ]);

        $this->expectException(GoogleCredentialsUnavailableException::class);

        $this->resolver([[]])->resolve($user->id, null);
    }

    // ---------- MODE B : explicit connection ----------

    public function test_mode_b_returns_connection_credentials_when_valid(): void
    {
        $user = User::factory()->create();
        $connection = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'sub-a',
            'access_token' => 'conn-access',
            'refresh_token' => 'conn-refresh',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['openid'],
            'status' => 'active',
        ]);

        $credentials = $this->resolver()->resolve($user->id, $connection->id);

        $this->assertSame('conn-access', $credentials->accessToken);
        $this->assertSame('conn-refresh', $credentials->refreshToken);
    }

    public function test_mode_b_refreshes_expired_connection_and_updates_connection_row(): void
    {
        $user = User::factory()->create();
        $connection = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'sub-a',
            'access_token' => 'conn-old',
            'refresh_token' => 'conn-refresh',
            'token_expires_at' => now()->subMinute(),
            'scopes' => ['openid'],
            'status' => 'active',
        ]);

        $resolver = $this->resolver([
            ['access_token' => 'conn-new', 'expires_in' => 3600],
        ]);

        $credentials = $resolver->resolve($user->id, $connection->id);

        $this->assertSame('conn-new', $credentials->accessToken);
        $this->assertSame(['conn-refresh'], $resolver->seenRefreshTokens);

        $connection->refresh();
        $this->assertSame('conn-new', $connection->access_token);
        $this->assertSame('conn-refresh', $connection->refresh_token);
    }

    public function test_mode_b_throws_when_connection_not_found(): void
    {
        $user = User::factory()->create();

        $this->expectException(ConnectionNotFoundException::class);

        $this->resolver()->resolve($user->id, 999999);
    }

    public function test_mode_b_throws_when_connection_belongs_to_another_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $connection = ConnectionModel::create([
            'user_id' => $owner->id,
            'provider' => 'google',
            'external_account_id' => 'sub-owner',
            'access_token' => 'owner-access',
            'refresh_token' => 'owner-refresh',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['openid'],
            'status' => 'active',
        ]);

        $this->expectException(ConnectionNotFoundException::class);

        $this->resolver()->resolve($other->id, $connection->id);
    }

    public function test_mode_b_throws_when_connection_has_no_usable_credentials(): void
    {
        $user = User::factory()->create();
        $connection = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'sub-empty',
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'scopes' => ['openid'],
            'status' => 'active',
        ]);

        $this->expectException(GoogleCredentialsUnavailableException::class);

        $this->resolver()->resolve($user->id, $connection->id);
    }

    /**
     * The critical multi-account rule:
     * an explicit connectionId must NEVER fall back to users.google_*.
     */
    public function test_mode_b_never_falls_back_to_legacy_user_credentials(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);

        $connection = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'sub-empty',
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'scopes' => ['openid'],
            'status' => 'active',
        ]);

        $this->expectException(GoogleCredentialsUnavailableException::class);

        $this->resolver()->resolve($user->id, $connection->id);

        // If execution reaches here, the resolver incorrectly used legacy credentials.
        $this->fail('Resolver must not fall back to legacy credentials when connectionId is provided.');
    }

    public function test_mode_b_refresh_failure_does_not_fall_back_to_legacy_user_credentials(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);

        $connection = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'sub-expired',
            'access_token' => 'conn-old',
            'refresh_token' => 'conn-refresh',
            'token_expires_at' => now()->subMinute(),
            'scopes' => ['openid'],
            'status' => 'active',
        ]);

        $this->expectException(GoogleCredentialsUnavailableException::class);

        $this->resolver([[]])->resolve($user->id, $connection->id);
    }
}

<?php

namespace App\Modules\Connections\Infrastructure\Google;

use App\Models\User;
use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Connections\Core\Entities\Connection;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Connections\Core\Exceptions\GoogleCredentialsUnavailableException;
use App\Modules\Connections\Core\Repositories\ConnectionRepository;
use App\Modules\Connections\Core\ValueObjects\ConnectionCredentials;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use DateTimeImmutable;
use DateTimeInterface;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleCredentialResolver implements GoogleCredentialsResolver
{
    public function __construct(
        private readonly ConnectionRepository $connections,
    ) {}

    public function resolve(int $userId, ?int $connectionId = null): ResolvedGoogleCredentials
    {
        if ($connectionId === null) {
            return $this->resolveFromLegacyUser($userId);
        }

        return $this->resolveFromConnection($userId, $connectionId);
    }

    private function resolveFromLegacyUser(int $userId): ResolvedGoogleCredentials
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            throw GoogleCredentialsUnavailableException::forLegacyUser($userId);
        }

        $accessToken = $this->normalizeToken($user->google_access_token);
        $refreshToken = $this->normalizeToken($user->google_refresh_token);
        $expiresAt = $this->normalizeExpiresAt($user->google_token_expires_at);

        if ($accessToken === null && $refreshToken === null) {
            throw GoogleCredentialsUnavailableException::forLegacyUser($userId);
        }

        if (! $this->needsRefresh($accessToken, $expiresAt)) {
            return new ResolvedGoogleCredentials(
                accessToken: $accessToken,
                refreshToken: $refreshToken,
                expiresAt: $expiresAt,
            );
        }

        if ($refreshToken === null) {
            throw GoogleCredentialsUnavailableException::refreshFailed($userId, null);
        }

        $newToken = $this->exchangeRefreshToken($refreshToken, $userId, null);
        $newExpiresAt = $this->expiresAtFromTokenResponse($newToken);

        $user->update([
            'google_access_token' => $newToken['access_token'],
            'google_token_expires_at' => $newExpiresAt,
        ]);

        return new ResolvedGoogleCredentials(
            accessToken: (string) $newToken['access_token'],
            refreshToken: $refreshToken,
            expiresAt: $newExpiresAt,
        );
    }

    private function resolveFromConnection(int $userId, int $connectionId): ResolvedGoogleCredentials
    {
        $connection = $this->connections->findForUser($userId, $connectionId);

        if ($connection === null) {
            throw ConnectionNotFoundException::forUser($userId, $connectionId);
        }

        $accessToken = $this->normalizeToken($connection->credentials->accessToken);
        $refreshToken = $this->normalizeToken($connection->credentials->refreshToken);
        $expiresAt = $connection->credentials->expiresAt;

        if ($accessToken === null && $refreshToken === null) {
            throw GoogleCredentialsUnavailableException::forConnection($userId, $connectionId);
        }

        if (! $this->needsRefresh($accessToken, $expiresAt)) {
            return new ResolvedGoogleCredentials(
                accessToken: $accessToken,
                refreshToken: $refreshToken,
                expiresAt: $expiresAt,
            );
        }

        if ($refreshToken === null) {
            throw GoogleCredentialsUnavailableException::refreshFailed($userId, $connectionId);
        }

        $newToken = $this->exchangeRefreshToken($refreshToken, $userId, $connectionId);
        $newExpiresAt = $this->expiresAtFromTokenResponse($newToken);

        $updated = new Connection(
            id: $connection->id,
            userId: $connection->userId,
            provider: $connection->provider,
            externalAccountId: $connection->externalAccountId,
            email: $connection->email,
            displayName: $connection->displayName,
            credentials: new ConnectionCredentials(
                accessToken: (string) $newToken['access_token'],
                refreshToken: $refreshToken,
                expiresAt: $newExpiresAt,
            ),
            scopes: $connection->scopes,
            status: $connection->status,
            createdAt: $connection->createdAt,
            updatedAt: new DateTimeImmutable,
        );

        $this->connections->save($updated);

        return new ResolvedGoogleCredentials(
            accessToken: (string) $newToken['access_token'],
            refreshToken: $refreshToken,
            expiresAt: $newExpiresAt,
        );
    }

    private function needsRefresh(?string $accessToken, ?DateTimeImmutable $expiresAt): bool
    {
        if ($accessToken === null) {
            return true;
        }

        if ($expiresAt === null) {
            return false;
        }

        return $expiresAt <= new DateTimeImmutable;
    }

    /**
     * @return array{access_token: string, expires_in?: int, refresh_token?: string}
     */
    private function exchangeRefreshToken(string $refreshToken, int $userId, ?int $connectionId): array
    {
        try {
            $response = $this->refreshWithGoogle($refreshToken);
        } catch (Throwable $e) {
            Log::error('Google token refresh failed', [
                'user_id' => $userId,
                'connection_id' => $connectionId,
                'error' => $e->getMessage(),
            ]);

            throw GoogleCredentialsUnavailableException::refreshFailed($userId, $connectionId, $e);
        }

        if (! isset($response['access_token']) || ! is_string($response['access_token']) || $response['access_token'] === '') {
            Log::error('Google token refresh returned no access_token', [
                'user_id' => $userId,
                'connection_id' => $connectionId,
            ]);

            throw GoogleCredentialsUnavailableException::refreshFailed($userId, $connectionId);
        }

        return $response;
    }

    /**
     * @return array{access_token: string, expires_in?: int, refresh_token?: string}
     */
    protected function refreshWithGoogle(string $refreshToken): array
    {
        $client = new GoogleClient;
        $client->setClientId((string) env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret((string) env('GOOGLE_CLIENT_SECRET'));
        $client->setAccessType('offline');

        $response = $client->fetchAccessTokenWithRefreshToken($refreshToken);

        return is_array($response) ? $response : [];
    }

    /**
     * @param  array{access_token: string, expires_in?: int}  $token
     */
    private function expiresAtFromTokenResponse(array $token): DateTimeImmutable
    {
        $expiresIn = (int) ($token['expires_in'] ?? 3600);

        return (new DateTimeImmutable)->modify('+'.$expiresIn.' seconds');
    }

    private function normalizeToken(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    private function normalizeExpiresAt(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value) && $value !== '') {
            try {
                return new DateTimeImmutable($value);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }
}

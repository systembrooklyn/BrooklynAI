<?php

namespace App\Modules\Connections\Core\Contracts;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;

interface GoogleCredentialsResolver
{
    /**
     * Resolve usable Google credentials for a user.
     *
     * When $connectionId is null, resolves legacy user.google_* credentials
     * and refreshes them in place if expired, exactly as the existing
     * legacy Google services do.
     *
     * When $connectionId is provided, resolves ONLY that Connection owned
     * by the authenticated user. It never falls back to users.google_*.
     */
    public function resolve(int $userId, ?int $connectionId = null): ResolvedGoogleCredentials;
}

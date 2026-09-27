<?php

namespace App\Modules\Connections\Core\Repositories;

use App\Modules\Connections\Core\Entities\OAuthState;
use DateTimeImmutable;

interface OAuthStateRepository
{
    public function save(OAuthState $state): OAuthState;

    public function findByState(string $state): ?OAuthState;

    /**
     * Atomically set consumed_at only if it is currently null.
     * Returns true if the row was updated by this call.
     */
    public function markConsumedIfNotYet(int $stateId, DateTimeImmutable $now): bool;
}

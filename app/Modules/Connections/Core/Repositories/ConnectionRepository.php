<?php

namespace App\Modules\Connections\Core\Repositories;

use App\Modules\Connections\Core\Entities\Connection;

interface ConnectionRepository
{
    public function findByUserAndExternalAccount(int $userId, string $provider, string $externalAccountId): ?Connection;

    public function findForUser(int $userId, int $connectionId): ?Connection;

    /**
     * @return array<int, Connection>
     */
    public function listForUser(int $userId): array;

    public function save(Connection $connection): Connection;

    public function delete(Connection $connection): void;
}

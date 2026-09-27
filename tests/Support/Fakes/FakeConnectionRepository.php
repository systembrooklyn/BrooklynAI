<?php

namespace Tests\Support\Fakes;

use App\Modules\Connections\Core\Entities\Connection;
use App\Modules\Connections\Core\Repositories\ConnectionRepository;

final class FakeConnectionRepository implements ConnectionRepository
{
    /** @var array<int, Connection> */
    private array $byId = [];

    public function addConnection(Connection $connection): void
    {
        if ($connection->id === null) {
            throw new \InvalidArgumentException('Fake connections must have an id.');
        }

        $this->byId[(int) $connection->id] = $connection;
    }

    public function findByUserAndExternalAccount(
        int $userId,
        string $provider,
        string $externalAccountId,
    ): ?Connection {
        return null;
    }

    public function findForUser(int $userId, int $connectionId): ?Connection
    {
        $connection = $this->byId[$connectionId] ?? null;

        if ($connection === null) {
            return null;
        }

        if ($connection->userId !== $userId) {
            return null;
        }

        return $connection;
    }

    /**
     * @return array<int, Connection>
     */
    public function listForUser(int $userId): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (Connection $c) => $c->userId === $userId,
        ));
    }

    public function save(Connection $connection): Connection
    {
        return $connection;
    }

    public function delete(Connection $connection): void
    {
        if ($connection->id !== null) {
            unset($this->byId[(int) $connection->id]);
        }
    }
}

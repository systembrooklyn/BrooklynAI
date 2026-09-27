<?php

namespace App\Modules\Connections\Application\Actions;

use App\Modules\Connections\Application\DTOs\ConnectionData;
use App\Modules\Connections\Core\Repositories\ConnectionRepository;

final class ListConnectionsAction
{
    public function __construct(private readonly ConnectionRepository $connections) {}

    /**
     * @return array<int, ConnectionData>
     */
    public function execute(int $userId): array
    {
        return array_map(
            static fn ($c) => ConnectionData::fromEntity($c),
            $this->connections->listForUser($userId),
        );
    }
}

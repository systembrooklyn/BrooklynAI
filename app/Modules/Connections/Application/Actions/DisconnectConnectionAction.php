<?php

namespace App\Modules\Connections\Application\Actions;

use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Connections\Core\Repositories\ConnectionRepository;

final class DisconnectConnectionAction
{
    public function __construct(private readonly ConnectionRepository $connections) {}

    public function execute(int $userId, int $connectionId): void
    {
        $connection = $this->connections->findForUser($userId, $connectionId);
        if ($connection === null) {
            throw ConnectionNotFoundException::forUser($userId, $connectionId);
        }

        $this->connections->delete($connection);
    }
}

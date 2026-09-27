<?php

namespace App\Modules\Connections\Infrastructure\Repositories;

use App\Modules\Connections\Core\Entities\Connection;
use App\Modules\Connections\Core\Repositories\ConnectionRepository;
use App\Modules\Connections\Core\ValueObjects\ConnectionCredentials;
use App\Modules\Connections\Core\ValueObjects\ConnectionStatus;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use DateTimeImmutable;
use DateTimeInterface;

final class EloquentConnectionRepository implements ConnectionRepository
{
    public function findByUserAndExternalAccount(int $userId, string $provider, string $externalAccountId): ?Connection
    {
        $model = ConnectionModel::query()
            ->where('user_id', $userId)
            ->where('provider', $provider)
            ->where('external_account_id', $externalAccountId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findForUser(int $userId, int $connectionId): ?Connection
    {
        $model = ConnectionModel::query()
            ->where('user_id', $userId)
            ->where('id', $connectionId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function listForUser(int $userId): array
    {
        return ConnectionModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (ConnectionModel $m) => $this->toEntity($m))
            ->all();
    }

    public function save(Connection $connection): Connection
    {
        $model = $connection->id
            ? ConnectionModel::query()->findOrFail($connection->id)
            : new ConnectionModel;

        $model->user_id = $connection->userId;
        $model->provider = $connection->provider;
        $model->external_account_id = $connection->externalAccountId;
        $model->email = $connection->email;
        $model->display_name = $connection->displayName;
        $model->access_token = $connection->credentials->accessToken;
        $model->refresh_token = $connection->credentials->refreshToken;
        $model->token_expires_at = $connection->credentials->expiresAt;
        $model->scopes = $connection->scopes;
        $model->status = $connection->status->value;
        $model->save();

        return $this->toEntity($model->fresh());
    }

    public function delete(Connection $connection): void
    {
        if ($connection->id === null) {
            return;
        }

        ConnectionModel::query()->where('id', $connection->id)->delete();
    }

    private function toEntity(ConnectionModel $model): Connection
    {
        return new Connection(
            id: (int) $model->id,
            userId: (int) $model->user_id,
            provider: (string) $model->provider,
            externalAccountId: (string) $model->external_account_id,
            email: $model->email,
            displayName: $model->display_name,
            credentials: new ConnectionCredentials(
                accessToken: $model->access_token,
                refreshToken: $model->refresh_token,
                expiresAt: $this->toImmutable($model->token_expires_at),
            ),
            scopes: is_array($model->scopes) ? $model->scopes : [],
            status: ConnectionStatus::from($model->status ?? ConnectionStatus::Active->value),
            createdAt: $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
            updatedAt: $this->toImmutable($model->updated_at) ?? new DateTimeImmutable,
        );
    }

    private function toImmutable(?DateTimeInterface $value): ?DateTimeImmutable
    {
        return $value === null ? null : DateTimeImmutable::createFromInterface($value);
    }
}

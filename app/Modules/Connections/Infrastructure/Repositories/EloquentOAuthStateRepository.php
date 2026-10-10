<?php

namespace App\Modules\Connections\Infrastructure\Repositories;

use App\Modules\Connections\Core\Entities\OAuthState;
use App\Modules\Connections\Core\Repositories\OAuthStateRepository;
use App\Modules\Connections\Infrastructure\Eloquent\OAuthStateModel;
use DateTimeImmutable;
use DateTimeInterface;

final class EloquentOAuthStateRepository implements OAuthStateRepository
{
    public function save(OAuthState $state): OAuthState
    {
        $model = new OAuthStateModel;
        $model->state = $state->state;
        $model->user_id = $state->userId;
        $model->provider = $state->provider;
        $model->platform = $state->platform;
        $model->scopes_requested = $state->scopesRequested;
        $model->consumed_at = $state->consumedAt;
        $model->expires_at = $state->expiresAt;
        $model->save();

        return $this->toEntity($model->fresh());
    }

    public function findByState(string $state): ?OAuthState
    {
        $model = OAuthStateModel::query()->where('state', $state)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function markConsumedIfNotYet(int $stateId, DateTimeImmutable $now): bool
    {
        $affected = OAuthStateModel::query()
            ->where('id', $stateId)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => $now]);

        return $affected === 1;
    }

    private function toEntity(OAuthStateModel $model): OAuthState
    {
        return new OAuthState(
            id: (int) $model->id,
            state: (string) $model->state,
            userId: (int) $model->user_id,
            provider: (string) $model->provider,
            platform: (string) ($model->platform ?? OAuthState::PLATFORM_WEB),
            scopesRequested: is_array($model->scopes_requested) ? $model->scopes_requested : [],
            expiresAt: $this->toImmutable($model->expires_at) ?? new DateTimeImmutable,
            consumedAt: $this->toImmutable($model->consumed_at),
        );
    }

    private function toImmutable(?DateTimeInterface $value): ?DateTimeImmutable
    {
        return $value === null ? null : DateTimeImmutable::createFromInterface($value);
    }
}

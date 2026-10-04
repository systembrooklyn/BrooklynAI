<?php

namespace App\Modules\Identity\Infrastructure\Repositories;

use App\Modules\Identity\Core\Entities\PasswordResetOtp;
use App\Modules\Identity\Core\Repositories\PasswordResetOtpRepository;
use App\Modules\Identity\Infrastructure\Eloquent\PasswordResetOtpModel;
use DateTimeImmutable;

final class EloquentPasswordResetOtpRepository implements PasswordResetOtpRepository
{
    public function replaceForEmail(string $email, string $codeHash, DateTimeImmutable $expiresAt): void
    {
        PasswordResetOtpModel::query()->updateOrCreate(
            ['email' => $email],
            [
                'code_hash'  => $codeHash,
                'attempts'   => 0,
                'expires_at' => $expiresAt,
                'used_at'    => null,
            ],
        );
    }

    public function lockByEmail(string $email): ?PasswordResetOtp
    {
        $model = PasswordResetOtpModel::query()
            ->where('email', $email)
            ->lockForUpdate()
            ->first();

        return $model === null ? null : $this->toEntity($model);
    }

    public function incrementAttempts(int $id): void
    {
        PasswordResetOtpModel::query()->where('id', $id)->increment('attempts');
    }

    public function markUsed(int $id, DateTimeImmutable $now): void
    {
        PasswordResetOtpModel::query()->where('id', $id)->update(['used_at' => $now]);
    }

    private function toEntity(PasswordResetOtpModel $model): PasswordResetOtp
    {
        return new PasswordResetOtp(
            id: (int) $model->id,
            email: (string) $model->email,
            codeHash: (string) $model->code_hash,
            attempts: (int) $model->attempts,
            expiresAt: DateTimeImmutable::createFromInterface($model->expires_at),
            usedAt: $model->used_at !== null
                ? DateTimeImmutable::createFromInterface($model->used_at)
                : null,
        );
    }
}

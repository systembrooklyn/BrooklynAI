<?php

namespace App\Modules\Connections\Core\Entities;

use App\Modules\Connections\Core\ValueObjects\ConnectionCredentials;
use App\Modules\Connections\Core\ValueObjects\ConnectionStatus;
use DateTimeImmutable;

final class Connection
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly string $provider,
        public readonly string $externalAccountId,
        public readonly ?string $email,
        public readonly ?string $displayName,
        public readonly ConnectionCredentials $credentials,
        public readonly array $scopes,
        public readonly ConnectionStatus $status,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
    ) {}

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function hasScopes(array $required): bool
    {
        return empty(array_diff($required, $this->scopes));
    }
}

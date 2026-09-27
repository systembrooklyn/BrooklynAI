<?php

namespace App\Modules\Connections\Application\DTOs;

use App\Modules\Connections\Core\Entities\Connection;
use DateTimeInterface;

final class ConnectionData
{
    public function __construct(
        public readonly int $id,
        public readonly string $provider,
        public readonly string $externalAccountId,
        public readonly ?string $email,
        public readonly ?string $displayName,
        public readonly array $scopes,
        public readonly string $status,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public static function fromEntity(Connection $connection): self
    {
        return new self(
            id: (int) $connection->id,
            provider: $connection->provider,
            externalAccountId: $connection->externalAccountId,
            email: $connection->email,
            displayName: $connection->displayName,
            scopes: $connection->scopes,
            status: $connection->status->value,
            createdAt: $connection->createdAt->format(DateTimeInterface::ATOM),
            updatedAt: $connection->updatedAt->format(DateTimeInterface::ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'external_account_id' => $this->externalAccountId,
            'email' => $this->email,
            'display_name' => $this->displayName,
            'scopes' => $this->scopes,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

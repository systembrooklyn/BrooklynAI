<?php

namespace App\Modules\Automation\Core\ValueObjects;

enum WorkflowStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function isPaused(): bool
    {
        return $this === self::Paused;
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => $target === self::Active,
            self::Active => $target === self::Paused,
            self::Paused => $target === self::Active,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}

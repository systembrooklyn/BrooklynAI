<?php

namespace App\Modules\Integrations\Core\ValueObjects;

use InvalidArgumentException;

final class ScopeIdentifier
{
    private function __construct(public readonly string $value) {}

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '' || str_contains($trimmed, ' ')) {
            throw new InvalidArgumentException(
                sprintf('Invalid scope identifier: "%s".', $value)
            );
        }

        return new self($trimmed);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

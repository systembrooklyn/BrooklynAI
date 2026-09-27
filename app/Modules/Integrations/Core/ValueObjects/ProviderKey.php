<?php

namespace App\Modules\Integrations\Core\ValueObjects;

use InvalidArgumentException;

final class ProviderKey
{
    private const PATTERN = '/^[a-z][a-z0-9_]{0,30}$/';

    private function __construct(public readonly string $value) {}

    public static function fromString(string $value): self
    {
        if (! preg_match(self::PATTERN, $value)) {
            throw new InvalidArgumentException(
                sprintf('Invalid provider key: "%s".', $value)
            );
        }

        return new self($value);
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

<?php

namespace App\Modules\Automation\Core\ValueObjects;

use App\Modules\Automation\Core\Exceptions\TemplateSyntaxException;

final class TemplateRef
{
    public const ROOT_TRIGGER = 'trigger';

    public const ROOT_STEPS = 'steps';

    /**
     * @param  array<int, string|int>  $path
     */
    private function __construct(
        public readonly string $root,
        public readonly ?int $position,
        public readonly array $path,
    ) {}

    public static function parse(string $expression): self
    {
        $trimmed = trim($expression);

        if ($trimmed === '') {
            throw TemplateSyntaxException::forPath($expression);
        }

        $parts = explode('.', $trimmed);

        if (count($parts) < 2) {
            throw TemplateSyntaxException::forPath($expression);
        }

        $root = array_shift($parts);

        if ($root === self::ROOT_TRIGGER) {
            return new self(
                root: self::ROOT_TRIGGER,
                position: null,
                path: self::parseSegments($parts, $expression),
            );
        }

        if ($root === self::ROOT_STEPS) {
            if (count($parts) < 3) {
                throw TemplateSyntaxException::forPath($expression);
            }

            $positionPart = array_shift($parts);

            if (! preg_match('/^[1-9][0-9]*$/', $positionPart)) {
                throw TemplateSyntaxException::forPath($expression);
            }

            $outputLiteral = array_shift($parts);

            if ($outputLiteral !== 'output') {
                throw TemplateSyntaxException::forPath($expression);
            }

            if (empty($parts)) {
                throw TemplateSyntaxException::forPath($expression);
            }

            return new self(
                root: self::ROOT_STEPS,
                position: (int) $positionPart,
                path: self::parseSegments($parts, $expression),
            );
        }

        throw TemplateSyntaxException::forPath($expression);
    }

    /**
     * @param  array<int, string>  $parts
     * @return array<int, string|int>
     */
    private static function parseSegments(array $parts, string $original): array
    {
        $segments = [];

        foreach ($parts as $part) {
            if ($part === '') {
                throw TemplateSyntaxException::forPath($original);
            }

            if (ctype_digit($part)) {
                $segments[] = (int) $part;

                continue;
            }

            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part)) {
                throw TemplateSyntaxException::forPath($original);
            }

            $segments[] = $part;
        }

        return $segments;
    }
}

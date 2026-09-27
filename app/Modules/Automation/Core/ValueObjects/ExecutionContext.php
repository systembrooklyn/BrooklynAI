<?php

namespace App\Modules\Automation\Core\ValueObjects;

use App\Modules\Automation\Core\Exceptions\TemplateResolutionFailed;

final class ExecutionContext
{
    /**
     * @param  array<string, mixed>  $triggerPayload
     * @param  array<int, array<string, mixed>>  $stepOutputs  Keyed by step position.
     */
    public function __construct(
        public readonly array $triggerPayload = [],
        public readonly array $stepOutputs = [],
    ) {}

    public function lookup(TemplateRef $ref): mixed
    {
        if ($ref->root === TemplateRef::ROOT_TRIGGER) {
            return $this->walk($this->triggerPayload, $ref->path, 'trigger');
        }

        $position = $ref->position;

        if ($position === null || ! array_key_exists($position, $this->stepOutputs)) {
            throw TemplateResolutionFailed::forPath('steps.'.$position.'.output');
        }

        return $this->walk(
            $this->stepOutputs[$position],
            $ref->path,
            'steps.'.$position.'.output',
        );
    }

    /**
     * @param  array<int, string|int>  $path
     */
    private function walk(mixed $node, array $path, string $prefix): mixed
    {
        $currentPath = $prefix;

        foreach ($path as $segment) {
            $currentPath .= '.'.$segment;

            if (! is_array($node)) {
                throw TemplateResolutionFailed::forTypeMismatch($currentPath);
            }

            if (! array_key_exists($segment, $node)) {
                throw TemplateResolutionFailed::forPath($currentPath);
            }

            $node = $node[$segment];
        }

        return $node;
    }
}

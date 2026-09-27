<?php

namespace App\Modules\Automation\Core\Contracts;

use App\Modules\Automation\Core\ValueObjects\ExecutionContext;

interface TemplateResolver
{
    /**
     * Validate that every template string in $value is syntactically well-formed.
     * Throws TemplateSyntaxException on any failure.
     */
    public function validateSyntax(mixed $value): void;

    /**
     * Resolve every template string in $value against the given context.
     * Throws TemplateResolutionFailed when a path cannot be resolved.
     */
    public function resolve(mixed $value, ExecutionContext $context): mixed;
}

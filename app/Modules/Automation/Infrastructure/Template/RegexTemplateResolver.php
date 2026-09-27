<?php

namespace App\Modules\Automation\Infrastructure\Template;

use App\Modules\Automation\Core\Contracts\TemplateResolver;
use App\Modules\Automation\Core\Exceptions\TemplateResolutionFailed;
use App\Modules\Automation\Core\Exceptions\TemplateSyntaxException;
use App\Modules\Automation\Core\ValueObjects\ExecutionContext;
use App\Modules\Automation\Core\ValueObjects\TemplateRef;

final class RegexTemplateResolver implements TemplateResolver
{
    public function validateSyntax(mixed $value): void
    {
        $this->walkValidate($value);
    }

    public function resolve(mixed $value, ExecutionContext $context): mixed
    {
        if (is_string($value)) {
            return $this->resolveString($value, $context);
        }

        if (is_array($value)) {
            $resolved = [];

            foreach ($value as $key => $item) {
                $resolved[$key] = $this->resolve($item, $context);
            }

            return $resolved;
        }

        return $value;
    }

    private function walkValidate(mixed $value): void
    {
        if (is_string($value)) {
            $this->validateString($value);

            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->walkValidate($item);
            }
        }
    }

    private function validateString(string $value): void
    {
        if (! str_contains($value, '{{') && ! str_contains($value, '}}')) {
            return;
        }

        $templates = $this->scanTemplates($value);

        $stripped = '';
        $last = 0;

        foreach ($templates as $template) {
            $stripped .= substr($value, $last, $template['start'] - $last);
            $last = $template['end'];
        }

        $stripped .= substr($value, $last);

        if (str_contains($stripped, '{{') || str_contains($stripped, '}}')) {
            throw TemplateSyntaxException::forString($value);
        }

        foreach ($templates as $template) {
            try {
                TemplateRef::parse($template['body']);
            } catch (TemplateSyntaxException) {
                throw TemplateSyntaxException::forString($value);
            }
        }
    }

    private function resolveString(string $value, ExecutionContext $context): mixed
    {
        $templates = $this->scanTemplates($value);

        if (empty($templates)) {
            return $value;
        }

        if (
            count($templates) === 1
            && $templates[0]['start'] === 0
            && $templates[0]['end'] === strlen($value)
        ) {
            $ref = TemplateRef::parse($templates[0]['body']);

            return $context->lookup($ref);
        }

        $result = '';
        $last = 0;

        foreach ($templates as $template) {
            $result .= substr($value, $last, $template['start'] - $last);
            $ref = TemplateRef::parse($template['body']);
            $result .= $this->stringify($context->lookup($ref), $template['body']);
            $last = $template['end'];
        }

        $result .= substr($value, $last);

        return $result;
    }

    private function stringify(mixed $value, string $path): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value === null) {
            return '';
        }

        throw TemplateResolutionFailed::forTypeMismatch($path);
    }

    /**
     * @return array<int, array{start: int, end: int, body: string}>
     */
    private function scanTemplates(string $value): array
    {
        $templates = [];
        $offset = 0;
        $length = strlen($value);

        while ($offset < $length) {
            $start = strpos($value, '{{', $offset);

            if ($start === false) {
                break;
            }

            $end = strpos($value, '}}', $start + 2);

            if ($end === false) {
                throw TemplateSyntaxException::forString($value);
            }

            $templates[] = [
                'start' => $start,
                'end' => $end + 2,
                'body' => trim(substr($value, $start + 2, $end - $start - 2)),
            ];

            $offset = $end + 2;
        }

        return $templates;
    }
}

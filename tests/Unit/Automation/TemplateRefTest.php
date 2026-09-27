<?php

namespace Tests\Unit\Automation;

use App\Modules\Automation\Core\Exceptions\TemplateSyntaxException;
use App\Modules\Automation\Core\ValueObjects\TemplateRef;
use PHPUnit\Framework\TestCase;

class TemplateRefTest extends TestCase
{
    public function test_parses_trigger_path(): void
    {
        $ref = TemplateRef::parse('trigger.message_id');

        $this->assertSame('trigger', $ref->root);
        $this->assertNull($ref->position);
        $this->assertSame(['message_id'], $ref->path);
    }

    public function test_parses_camel_case_trigger_path(): void
    {
        $ref = TemplateRef::parse('trigger.messageId');

        $this->assertSame(['messageId'], $ref->path);
    }

    public function test_parses_nested_trigger_path(): void
    {
        $ref = TemplateRef::parse('trigger.rows.0.values.1');

        $this->assertSame(['rows', 0, 'values', 1], $ref->path);
    }

    public function test_parses_steps_path(): void
    {
        $ref = TemplateRef::parse('steps.4.output.messageId');

        $this->assertSame('steps', $ref->root);
        $this->assertSame(4, $ref->position);
        $this->assertSame(['messageId'], $ref->path);
    }

    public function test_parses_deep_steps_path(): void
    {
        $ref = TemplateRef::parse('steps.12.output.items.2.id');

        $this->assertSame(12, $ref->position);
        $this->assertSame(['items', 2, 'id'], $ref->path);
    }

    public function test_rejects_empty_expression(): void
    {
        $this->expectException(TemplateSyntaxException::class);
        TemplateRef::parse('');
    }

    public function test_rejects_unknown_root(): void
    {
        $this->expectException(TemplateSyntaxException::class);
        TemplateRef::parse('env.SECRET');
    }

    public function test_rejects_trailing_dot(): void
    {
        $this->expectException(TemplateSyntaxException::class);
        TemplateRef::parse('trigger.');
    }

    public function test_rejects_empty_segment(): void
    {
        $this->expectException(TemplateSyntaxException::class);
        TemplateRef::parse('trigger.foo..bar');
    }

    public function test_rejects_steps_without_position(): void
    {
        $this->expectException(TemplateSyntaxException::class);
        TemplateRef::parse('steps.output.id');
    }

    public function test_rejects_steps_with_non_numeric_position(): void
    {
        $this->expectException(TemplateSyntaxException::class);
        TemplateRef::parse('steps.x.output.id');
    }

    public function test_rejects_steps_with_leading_zero_position(): void
    {
        $this->expectException(TemplateSyntaxException::class);
        TemplateRef::parse('steps.01.output.id');
    }

    public function test_rejects_steps_without_output_literal(): void
    {
        $this->expectException(TemplateSyntaxException::class);
        TemplateRef::parse('steps.1.messageId');
    }

    public function test_rejects_steps_without_path_after_output(): void
    {
        $this->expectException(TemplateSyntaxException::class);
        TemplateRef::parse('steps.1.output');
    }

    public function test_rejects_callable_expression(): void
    {
        $this->expectException(TemplateSyntaxException::class);
        TemplateRef::parse('trigger.foo()');
    }

    public function test_rejects_whitespace_inside_path(): void
    {
        $this->expectException(TemplateSyntaxException::class);
        TemplateRef::parse('trigger . x');
    }
}

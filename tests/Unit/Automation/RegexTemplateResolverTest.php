<?php

namespace Tests\Unit\Automation;

use App\Modules\Automation\Core\Exceptions\TemplateResolutionFailed;
use App\Modules\Automation\Core\Exceptions\TemplateSyntaxException;
use App\Modules\Automation\Core\ValueObjects\ExecutionContext;
use App\Modules\Automation\Infrastructure\Template\RegexTemplateResolver;
use PHPUnit\Framework\TestCase;

class RegexTemplateResolverTest extends TestCase
{
    private RegexTemplateResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RegexTemplateResolver;
    }

    public function test_validate_syntax_accepts_plain_strings(): void
    {
        $this->resolver->validateSyntax('plain text');
        $this->assertTrue(true);
    }

    public function test_validate_syntax_accepts_valid_trigger_template(): void
    {
        $this->resolver->validateSyntax('{{ trigger.x }}');
        $this->assertTrue(true);
    }

    public function test_validate_syntax_accepts_valid_step_template(): void
    {
        $this->resolver->validateSyntax('{{ steps.1.output.id }}');
        $this->assertTrue(true);
    }

    public function test_validate_syntax_accepts_arrays_recursively(): void
    {
        $this->resolver->validateSyntax([
            'a' => '{{ trigger.x }}',
            'b' => ['c' => '{{ steps.2.output.id }}'],
        ]);
        $this->assertTrue(true);
    }

    public function test_validate_syntax_rejects_unbalanced_open_brace(): void
    {
        $this->expectException(TemplateSyntaxException::class);
        $this->resolver->validateSyntax('{{ trigger.x');
    }

    public function test_validate_syntax_rejects_stray_close_brace(): void
    {
        $this->expectException(TemplateSyntaxException::class);
        $this->resolver->validateSyntax('hello }} world');
    }

    public function test_validate_syntax_rejects_malformed_path(): void
    {
        $this->expectException(TemplateSyntaxException::class);
        $this->resolver->validateSyntax('{{ trigger. }}');
    }

    public function test_validate_syntax_rejects_malformed_path_inside_array(): void
    {
        $this->expectException(TemplateSyntaxException::class);
        $this->resolver->validateSyntax(['k' => '{{ unknown.x }}']);
    }

    public function test_resolve_returns_plain_string_unchanged(): void
    {
        $ctx = new ExecutionContext;
        $this->assertSame('hello', $this->resolver->resolve('hello', $ctx));
    }

    public function test_resolve_returns_non_string_scalars_unchanged(): void
    {
        $ctx = new ExecutionContext;
        $this->assertSame(42, $this->resolver->resolve(42, $ctx));
        $this->assertSame(true, $this->resolver->resolve(true, $ctx));
        $this->assertNull($this->resolver->resolve(null, $ctx));
    }

    public function test_resolve_whole_string_template_preserves_type(): void
    {
        $ctx = new ExecutionContext(triggerPayload: ['count' => 42]);

        $this->assertSame(42, $this->resolver->resolve('{{ trigger.count }}', $ctx));
    }

    public function test_resolve_whole_string_template_preserves_array_type(): void
    {
        $ctx = new ExecutionContext(triggerPayload: ['items' => ['a', 'b']]);

        $this->assertSame(['a', 'b'], $this->resolver->resolve('{{ trigger.items }}', $ctx));
    }

    public function test_resolve_embedded_template_concatenates(): void
    {
        $ctx = new ExecutionContext(triggerPayload: ['name' => 'Alice']);

        $this->assertSame('Hello Alice!', $this->resolver->resolve('Hello {{ trigger.name }}!', $ctx));
    }

    public function test_resolve_multiple_templates_in_one_string(): void
    {
        $ctx = new ExecutionContext(triggerPayload: ['a' => '1', 'b' => '2']);

        $this->assertSame('1-2', $this->resolver->resolve('{{ trigger.a }}-{{ trigger.b }}', $ctx));
    }

    public function test_resolve_step_output_whole_string(): void
    {
        $ctx = new ExecutionContext(stepOutputs: [
            2 => ['messageId' => 'id-2'],
        ]);

        $this->assertSame('id-2', $this->resolver->resolve('{{ steps.2.output.messageId }}', $ctx));
    }

    public function test_resolve_array_recursively(): void
    {
        $ctx = new ExecutionContext(triggerPayload: ['x' => 'v']);

        $result = $this->resolver->resolve([
            'a' => '{{ trigger.x }}',
            'b' => ['c' => 'literal'],
        ], $ctx);

        $this->assertSame(['a' => 'v', 'b' => ['c' => 'literal']], $result);
    }

    public function test_resolve_missing_path_throws(): void
    {
        $ctx = new ExecutionContext(triggerPayload: []);

        $this->expectException(TemplateResolutionFailed::class);

        $this->resolver->resolve('{{ trigger.missing }}', $ctx);
    }

    public function test_resolve_embedded_array_value_throws(): void
    {
        $ctx = new ExecutionContext(triggerPayload: ['items' => ['a']]);

        $this->expectException(TemplateResolutionFailed::class);

        $this->resolver->resolve('List: {{ trigger.items }}', $ctx);
    }
}

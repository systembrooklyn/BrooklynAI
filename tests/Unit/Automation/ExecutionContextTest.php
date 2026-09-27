<?php

namespace Tests\Unit\Automation;

use App\Modules\Automation\Core\Exceptions\TemplateResolutionFailed;
use App\Modules\Automation\Core\ValueObjects\ExecutionContext;
use App\Modules\Automation\Core\ValueObjects\TemplateRef;
use PHPUnit\Framework\TestCase;

class ExecutionContextTest extends TestCase
{
    public function test_lookup_trigger_value(): void
    {
        $ctx = new ExecutionContext(triggerPayload: ['message_id' => 'abc']);

        $this->assertSame('abc', $ctx->lookup(TemplateRef::parse('trigger.message_id')));
    }

    public function test_lookup_nested_trigger_path(): void
    {
        $ctx = new ExecutionContext(triggerPayload: [
            'rows' => [['values' => ['x', 'y']]],
        ]);

        $this->assertSame('y', $ctx->lookup(TemplateRef::parse('trigger.rows.0.values.1')));
    }

    public function test_lookup_step_output(): void
    {
        $ctx = new ExecutionContext(stepOutputs: [
            1 => ['messageId' => 'id-1'],
        ]);

        $this->assertSame('id-1', $ctx->lookup(TemplateRef::parse('steps.1.output.messageId')));
    }

    public function test_lookup_missing_trigger_key_throws(): void
    {
        $ctx = new ExecutionContext(triggerPayload: []);

        $this->expectException(TemplateResolutionFailed::class);

        $ctx->lookup(TemplateRef::parse('trigger.message_id'));
    }

    public function test_lookup_missing_step_throws(): void
    {
        $ctx = new ExecutionContext(stepOutputs: []);

        $this->expectException(TemplateResolutionFailed::class);

        $ctx->lookup(TemplateRef::parse('steps.4.output.id'));
    }

    public function test_lookup_type_mismatch_throws(): void
    {
        $ctx = new ExecutionContext(triggerPayload: ['a' => 'scalar']);

        $this->expectException(TemplateResolutionFailed::class);

        $ctx->lookup(TemplateRef::parse('trigger.a.b'));
    }

    public function test_lookup_step_type_mismatch_throws(): void
    {
        $ctx = new ExecutionContext(stepOutputs: [
            1 => ['x' => 'scalar'],
        ]);

        $this->expectException(TemplateResolutionFailed::class);

        $ctx->lookup(TemplateRef::parse('steps.1.output.x.y'));
    }
}

<?php

namespace Tests\Unit\Integrations;

use App\Modules\Integrations\Core\ValueObjects\FieldDefinition;
use PHPUnit\Framework\TestCase;

class FieldDefinitionTest extends TestCase
{
    public function test_required_properties_are_set(): void
    {
        $field = new FieldDefinition(
            key: 'to',
            label: 'To',
            type: 'email',
            required: true,
        );

        $this->assertSame('to', $field->key);
        $this->assertSame('To', $field->label);
        $this->assertSame('email', $field->type);
        $this->assertTrue($field->required);
        $this->assertNull($field->description);
        $this->assertNull($field->default);
        $this->assertNull($field->options);
        $this->assertNull($field->options_source);
    }

    public function test_options_and_options_source_are_independent(): void
    {
        $static = new FieldDefinition(
            key: 'color',
            label: 'Color',
            type: 'select',
            required: false,
            options: [
                ['value' => 'red', 'label' => 'Red'],
                ['value' => 'blue', 'label' => 'Blue'],
            ],
        );

        $this->assertNull($static->options_source);
        $this->assertSame('red', $static->options[0]['value']);

        $dynamic = new FieldDefinition(
            key: 'label_id',
            label: 'Label',
            type: 'select',
            required: false,
            options_source: [
                'operation_id' => 'gmail.labels',
                'params' => ['connection_id' => '{{connection_id}}'],
            ],
        );

        $this->assertNull($dynamic->options);
        $this->assertSame('gmail.labels', $dynamic->options_source['operation_id']);
        $this->assertSame(
            ['connection_id' => '{{connection_id}}'],
            $dynamic->options_source['params'],
        );
    }

    public function test_optional_properties_can_be_omitted(): void
    {
        $field = new FieldDefinition(
            key: 'subject',
            label: 'Subject',
            type: 'string',
        );

        $this->assertFalse($field->required);
        $this->assertNull($field->description);
        $this->assertNull($field->default);
        $this->assertNull($field->options);
        $this->assertNull($field->options_source);
    }
}

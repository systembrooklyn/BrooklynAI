<?php

namespace Tests\Unit\Integrations;

use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailTriggerQueryComposer;
use PHPUnit\Framework\TestCase;

class GmailTriggerQueryComposerTest extends TestCase
{
    private GmailTriggerQueryComposer $composer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->composer = new GmailTriggerQueryComposer;
    }

    public function test_empty_config_produces_null(): void
    {
        $this->assertNull($this->composer->compose([]));
    }

    public function test_from_filter(): void
    {
        $this->assertSame('from:user@example.com', $this->composer->compose([
            'from' => 'user@example.com',
        ]));
    }

    public function test_subject_filter_without_spaces(): void
    {
        $this->assertSame('subject:Hello', $this->composer->compose([
            'subject' => 'Hello',
        ]));
    }

    public function test_subject_filter_with_spaces_is_quoted(): void
    {
        $this->assertSame('subject:"Hello World"', $this->composer->compose([
            'subject' => 'Hello World',
        ]));
    }

    public function test_has_attachment_true_adds_token(): void
    {
        $this->assertSame('has:attachment', $this->composer->compose([
            'has_attachment' => true,
        ]));
    }

    public function test_has_attachment_false_is_ignored(): void
    {
        $this->assertNull($this->composer->compose([
            'has_attachment' => false,
        ]));
    }

    public function test_free_form_query_is_appended(): void
    {
        $this->assertSame('is:unread larger:5M', $this->composer->compose([
            'query' => 'is:unread larger:5M',
        ]));
    }

    public function test_combined_filters(): void
    {
        $this->assertSame(
            'from:user@example.com subject:"Hello World" has:attachment is:unread',
            $this->composer->compose([
                'from' => 'user@example.com',
                'subject' => 'Hello World',
                'has_attachment' => true,
                'query' => 'is:unread',
            ]),
        );
    }

    public function test_blank_strings_are_ignored(): void
    {
        $this->assertNull($this->composer->compose([
            'from' => '',
            'subject' => '   ',
            'query' => '',
        ]));
    }

    public function test_non_string_values_are_ignored(): void
    {
        $this->assertNull($this->composer->compose([
            'from' => 123,
            'subject' => ['array'],
            'query' => null,
        ]));
    }

    public function test_label_id_does_not_become_part_of_the_query(): void
    {
        // label_id is passed to Gmail as a separate labelIds parameter and
        // must not leak into the q string.
        $this->assertNull($this->composer->compose([
            'label_id' => 'INBOX',
        ]));
    }
}

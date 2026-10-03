<?php

namespace Tests\Unit\Integrations;

use App\Modules\Integrations\Application\Services\CapabilityScopeMap;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CapabilityScopeMapTest extends TestCase
{
    private const SCOPE_READONLY = 'https://www.googleapis.com/auth/gmail.readonly';
    private const SCOPE_SEND = 'https://www.googleapis.com/auth/gmail.send';
    private const SCOPE_COMPOSE = 'https://www.googleapis.com/auth/gmail.compose';
    private const SCOPE_MODIFY = 'https://www.googleapis.com/auth/gmail.modify';
    private const SCOPE_LABELS = 'https://www.googleapis.com/auth/gmail.labels';

    public function test_gmail_capability_resolves_to_all_gmail_scopes(): void
    {
        $map = new CapabilityScopeMap;

        $scopes = $map->resolve('gmail');

        $this->assertCount(5, $scopes);
        $this->assertContains(self::SCOPE_READONLY, $scopes);
        $this->assertContains(self::SCOPE_SEND, $scopes);
        $this->assertContains(self::SCOPE_COMPOSE, $scopes);
        $this->assertContains(self::SCOPE_MODIFY, $scopes);
        $this->assertContains(self::SCOPE_LABELS, $scopes);
    }

    public function test_gmail_capability_does_not_include_unrelated_google_scopes(): void
    {
        $map = new CapabilityScopeMap;

        $scopes = $map->resolve('gmail');

        $this->assertNotContains('https://www.googleapis.com/auth/calendar', $scopes);
        $this->assertNotContains('https://www.googleapis.com/auth/spreadsheets', $scopes);
        $this->assertNotContains('https://www.googleapis.com/auth/drive', $scopes);
    }

    public function test_unknown_capability_throws(): void
    {
        $map = new CapabilityScopeMap;

        $this->expectException(InvalidArgumentException::class);

        $map->resolve('unknown_capability');
    }

    public function test_has_returns_true_for_gmail(): void
    {
        $map = new CapabilityScopeMap;

        $this->assertTrue($map->has('gmail'));
    }

    public function test_has_returns_false_for_unknown_capability(): void
    {
        $map = new CapabilityScopeMap;

        $this->assertFalse($map->has('unknown_capability'));
    }

    public function test_capabilities_lists_only_gmail_in_mvp(): void
    {
        $map = new CapabilityScopeMap;

        $this->assertSame(['gmail'], $map->capabilities());
    }
}

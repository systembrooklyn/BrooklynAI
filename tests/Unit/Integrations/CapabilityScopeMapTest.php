<?php

namespace Tests\Unit\Integrations;

use App\Modules\Integrations\Application\Services\CapabilityScopeMap;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CapabilityScopeMapTest extends TestCase
{
    public function test_gmail_capability_resolves_to_exactly_readonly_and_send(): void
    {
        $map = new CapabilityScopeMap;

        $scopes = $map->resolve('gmail');
        sort($scopes);

        $this->assertSame([
            'https://www.googleapis.com/auth/gmail.readonly',
            'https://www.googleapis.com/auth/gmail.send',
        ], $scopes);
    }

    public function test_gmail_capability_does_not_include_labels_scope(): void
    {
        $map = new CapabilityScopeMap;

        $this->assertNotContains(
            'https://www.googleapis.com/auth/gmail.labels',
            $map->resolve('gmail'),
        );
    }

    public function test_gmail_capability_does_not_include_other_google_scopes(): void
    {
        $map = new CapabilityScopeMap;

        $scopes = $map->resolve('gmail');

        foreach ($scopes as $scope) {
            $this->assertStringStartsWith('https://www.googleapis.com/auth/gmail.', $scope);
        }
    }

    public function test_unknown_capability_throws(): void
    {
        $map = new CapabilityScopeMap;

        $this->expectException(InvalidArgumentException::class);

        $map->resolve('not-a-capability');
    }

    public function test_has_returns_true_for_gmail(): void
    {
        $map = new CapabilityScopeMap;

        $this->assertTrue($map->has('gmail'));
    }

    public function test_has_returns_false_for_unknown_capability(): void
    {
        $map = new CapabilityScopeMap;

        $this->assertFalse($map->has('not-a-capability'));
        $this->assertFalse($map->has(''));
    }

    public function test_capabilities_lists_only_gmail_in_mvp(): void
    {
        $map = new CapabilityScopeMap;

        $this->assertSame(['gmail'], $map->capabilities());
    }
}

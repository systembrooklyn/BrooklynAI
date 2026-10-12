<?php

namespace Tests\Unit\Integrations;

use App\Modules\Integrations\Application\Services\CapabilityScopeMap;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DriveCapabilityTest extends TestCase
{
    private CapabilityScopeMap $map;

    protected function setUp(): void
    {
        parent::setUp();

        $this->map = new CapabilityScopeMap;
    }

    public function test_drive_capability_is_registered(): void
    {
        $this->assertTrue($this->map->has('drive'));
    }

    public function test_drive_resolves_to_drive_file_scope_only(): void
    {
        $this->assertSame(
            ['https://www.googleapis.com/auth/drive.file'],
            $this->map->resolve('drive'),
        );
    }

    public function test_drive_scope_list_is_exactly_one_scope(): void
    {
        $this->assertCount(1, $this->map->resolve('drive'));
    }

    public function test_gmail_capability_is_unchanged(): void
    {
        $this->assertTrue($this->map->has('gmail'));

        $this->assertSame([
            'https://www.googleapis.com/auth/gmail.readonly',
            'https://www.googleapis.com/auth/gmail.send',
            'https://www.googleapis.com/auth/gmail.compose',
            'https://www.googleapis.com/auth/gmail.modify',
            'https://www.googleapis.com/auth/gmail.labels',
        ], $this->map->resolve('gmail'));
    }

    public function test_unknown_capability_is_rejected_by_has(): void
    {
        $this->assertFalse($this->map->has('not_a_real_capability'));
    }

    public function test_resolving_unknown_capability_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->map->resolve('not_a_real_capability');
    }

    public function test_capabilities_lists_both_known_capabilities(): void
    {
        $this->assertSame(['gmail', 'drive'], $this->map->capabilities());
    }
}

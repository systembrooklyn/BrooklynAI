<?php

namespace Tests\Unit\Integrations;

use App\Modules\Integrations\Core\ValueObjects\IntegrationKey;
use App\Modules\Integrations\Core\ValueObjects\ProviderKey;
use App\Modules\Integrations\Core\ValueObjects\ScopeIdentifier;
use App\Modules\Integrations\Core\ValueObjects\TriggerStrategy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class IntegrationValueObjectsTest extends TestCase
{
    public function test_provider_key_accepts_valid_value(): void
    {
        $key = ProviderKey::fromString('google');

        $this->assertSame('google', $key->value);
        $this->assertSame('google', (string) $key);
    }

    public function test_provider_key_rejects_invalid_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProviderKey::fromString('Google');
    }

    public function test_provider_key_rejects_empty_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProviderKey::fromString('');
    }

    public function test_integration_key_accepts_valid_value(): void
    {
        $key = IntegrationKey::fromString('google.gmail');

        $this->assertSame('google.gmail', $key->value);
    }

    public function test_integration_key_rejects_missing_provider(): void
    {
        $this->expectException(InvalidArgumentException::class);

        IntegrationKey::fromString('gmail');
    }

    public function test_integration_key_rejects_trailing_dot(): void
    {
        $this->expectException(InvalidArgumentException::class);

        IntegrationKey::fromString('google.');
    }

    public function test_integration_key_rejects_leading_dot(): void
    {
        $this->expectException(InvalidArgumentException::class);

        IntegrationKey::fromString('.gmail');
    }

    public function test_integration_key_derives_provider_key(): void
    {
        $key = IntegrationKey::fromString('google.gmail');
        $provider = $key->providerKey();

        $this->assertSame('google', $provider->value);
    }

    public function test_scope_identifier_accepts_full_uri(): void
    {
        $scope = ScopeIdentifier::fromString('https://www.googleapis.com/auth/gmail.send');

        $this->assertSame('https://www.googleapis.com/auth/gmail.send', $scope->value);
    }

    public function test_scope_identifier_accepts_short_form(): void
    {
        $scope = ScopeIdentifier::fromString('openid');

        $this->assertSame('openid', $scope->value);
    }

    public function test_scope_identifier_rejects_whitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ScopeIdentifier::fromString('gmail send');
    }

    public function test_scope_identifier_rejects_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ScopeIdentifier::fromString('');
    }

    public function test_trigger_strategy_has_expected_values(): void
    {
        $this->assertSame('poll', TriggerStrategy::Poll->value);
        $this->assertSame('webhook', TriggerStrategy::Webhook->value);
        $this->assertSame('provider_push', TriggerStrategy::ProviderPush->value);
        $this->assertSame('schedule', TriggerStrategy::Schedule->value);
    }
}

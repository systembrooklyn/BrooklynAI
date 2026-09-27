<?php

namespace Tests\Support\Fakes;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailLabelReader;

final class FakeGmailLabelReader extends GmailLabelReader
{
    /** @var array<int, array{id: string, name: string, type: string}> */
    public array $labels = [];

    public ?\Throwable $throws = null;

    public int $callCount = 0;

    public function list(ResolvedGoogleCredentials $credentials): array
    {
        $this->callCount++;

        if ($this->throws !== null) {
            throw $this->throws;
        }

        return $this->labels;
    }

    protected function createService(ResolvedGoogleCredentials $credentials): \Google\Service\Gmail
    {
        throw new \LogicException('FakeGmailLabelReader::createService must not be called.');
    }
}

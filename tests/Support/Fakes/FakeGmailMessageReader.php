<?php

namespace Tests\Support\Fakes;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessageReader;
use Google\Service\Gmail\Message as GoogleGmailMessage;

final class FakeGmailMessageReader extends GmailMessageReader
{
    /** @var array<int, string> */
    public array $listMessageIdsReturn = [];

    /** @var array<string, GoogleGmailMessage> */
    public array $messages = [];

    public ?\Throwable $listThrows = null;

    /** @var array<string, \Throwable> */
    public array $getThrows = [];

    /** @var array<int, array{after: int, labelIds: array<int, string>}> */
    public array $listCalls = [];

    /** @var array<int, string> */
    public array $getCalls = [];

    public function listMessageIds(
        ResolvedGoogleCredentials $credentials,
        int $afterEpochSeconds,
        array $labelIds = [],
    ): array {
        $this->listCalls[] = [
            'after' => $afterEpochSeconds,
            'labelIds' => array_values($labelIds),
        ];

        if ($this->listThrows !== null) {
            throw $this->listThrows;
        }

        return $this->listMessageIdsReturn;
    }

    public function getMessage(
        ResolvedGoogleCredentials $credentials,
        string $messageId,
    ): GoogleGmailMessage {
        $this->getCalls[] = $messageId;

        if (isset($this->getThrows[$messageId])) {
            throw $this->getThrows[$messageId];
        }

        if (! isset($this->messages[$messageId])) {
            throw new \RuntimeException('No stubbed Gmail message for id: '.$messageId);
        }

        return $this->messages[$messageId];
    }

    protected function createService(ResolvedGoogleCredentials $credentials): \Google\Service\Gmail
    {
        throw new \LogicException('FakeGmailMessageReader::createService must not be called.');
    }
}

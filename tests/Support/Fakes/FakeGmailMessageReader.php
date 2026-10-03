<?php

namespace Tests\Support\Fakes;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessageReader;
use Google\Service\Gmail\Message as GoogleGmailMessage;
use Throwable;

final class FakeGmailMessageReader extends GmailMessageReader
{
    /** @var array<int, array{after: int, labelIds: array<int, string>, query: ?string}> */
    public array $listCalls = [];

    /** @var array<int, string> */
    public array $listMessageIdsReturn = [];

    /** @var array<string, GoogleGmailMessage> */
    public array $messages = [];

    public ?Throwable $listThrows = null;

    /** @var array<string, Throwable> */
    public array $getThrows = [];

    public function listMessageIds(
        ResolvedGoogleCredentials $credentials,
        int $afterEpochSeconds,
        array $labelIds = [],
        ?string $query = null,
    ): array {
        $this->listCalls[] = [
            'after' => $afterEpochSeconds,
            'labelIds' => $labelIds,
            'query' => $query,
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
        if (isset($this->getThrows[$messageId])) {
            throw $this->getThrows[$messageId];
        }

        return $this->messages[$messageId];
    }
}

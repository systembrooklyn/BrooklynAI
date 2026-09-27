<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\AppendRowByHeadersInput;
use App\Modules\Integrations\Infrastructure\Google\Sheets\GoogleSheetsRowAppender;

final class AppendRowByHeadersAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleSheetsRowAppender $appender,
    ) {}

    public function execute(AppendRowByHeadersInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->appender->appendByHeaders(
            $resolved,
            $input->spreadsheetId,
            $input->sheetName,
            $input->data,
        );
    }
}

<?php

namespace App\Modules\Integrations\Infrastructure\Google\Sheets;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use Google\Client as GoogleClient;
use Google\Service\Sheets as GoogleSheetsService;
use Google\Service\Sheets\ValueRange as GoogleSheetsValueRange;

class GoogleSheetsRowAppender
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{message: string, row: int, range: string, values: array<int, mixed>}
     */
    public function appendByHeaders(
        ResolvedGoogleCredentials $credentials,
        string $spreadsheetId,
        string $sheetName,
        array $data,
    ): array {
        $service = $this->createService($credentials);

        return $this->writeRow($service, $spreadsheetId, $sheetName, $data);
    }

    protected function createService(ResolvedGoogleCredentials $credentials): GoogleSheetsService
    {
        $client = new GoogleClient;
        $client->setClientId((string) env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret((string) env('GOOGLE_CLIENT_SECRET'));
        $client->setAccessType('offline');
        $client->addScope('https://www.googleapis.com/auth/spreadsheets');

        $expiresIn = 3600;
        if ($credentials->expiresAt !== null) {
            $expiresIn = max(0, $credentials->expiresAt->getTimestamp() - time());
        }

        $client->setAccessToken([
            'access_token' => $credentials->accessToken,
            'refresh_token' => $credentials->refreshToken,
            'expires_in' => $expiresIn,
        ]);

        return new GoogleSheetsService($client);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{message: string, row: int, range: string, values: array<int, mixed>}
     */
    protected function writeRow(
        GoogleSheetsService $service,
        string $spreadsheetId,
        string $sheetName,
        array $data,
    ): array {
        // 1. Read headers (first row)
        $headerRange = "{$sheetName}!1:1";
        $headerResponse = $service->spreadsheets_values->get($spreadsheetId, $headerRange);
        $headers = $headerResponse->getValues()[0] ?? [];

        if (empty($headers)) {
            throw new \Exception('No headers found in row 1');
        }

        // 2. Map header names to column indices
        $headerMap = [];
        foreach ($headers as $index => $header) {
            $headerMap[strtolower(trim($header))] = $index;
        }

        // 3. Find the last row that has any data
        $lastDataRow = 1;
        $endCol = $this->numberToLetter(count($headers) - 1);
        $fullRange = "{$sheetName}!A2:{$endCol}";
        $response = $service->spreadsheets_values->get($spreadsheetId, $fullRange);
        $rows = $response->getValues() ?? [];

        foreach ($rows as $index => $row) {
            $hasData = false;
            foreach ($row as $cell) {
                if (! empty(trim((string) $cell))) {
                    $hasData = true;
                    break;
                }
            }
            if ($hasData) {
                $lastDataRow = $index + 2;
            }
        }

        // 4. Next row is after the last used row
        $nextRow = $lastDataRow + 1;

        // 5. Prepare row data
        $rowData = array_fill(0, count($headers), '');
        foreach ($data as $headerName => $value) {
            $key = strtolower(trim($headerName));
            if (! isset($headerMap[$key])) {
                throw new \Exception("Header '{$headerName}' not found in sheet");
            }
            $colIndex = $headerMap[$key];
            $rowData[$colIndex] = $value;
        }

        // 6. Update that row
        $startCol = 'A';
        $endColLetter = $this->numberToLetter(count($headers) - 1);
        $range = "{$sheetName}!{$startCol}{$nextRow}:{$endColLetter}{$nextRow}";

        $body = new GoogleSheetsValueRange(['values' => [$rowData]]);

        $service->spreadsheets_values->update(
            $spreadsheetId,
            $range,
            $body,
            ['valueInputOption' => 'USER_ENTERED']
        );

        return [
            'message' => 'Row appended successfully',
            'row' => $nextRow,
            'range' => $range,
            'values' => $rowData,
        ];
    }

    private function numberToLetter(int $num): string
    {
        $letter = '';
        while ($num >= 0) {
            $letter = chr(65 + ($num % 26)).$letter;
            $num = intdiv($num, 26) - 1;
        }

        return $letter;
    }
}

<?php

namespace App\Services;

use Google\Client as Google_Client;
use Google\Service\Drive as Google_Service_Drive;
use Google\Service\Sheets as Google_Service_Sheets;
use Google\Service\Sheets\ValueRange as Google_Service_Sheets_ValueRange;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest as Google_Service_Sheets_BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\Request as Google_Service_Sheets_Request;
use Google\Service\Sheets\SheetProperties as Google_Service_Sheets_SheetProperties;
use Google\Service\Sheets\ClearValuesRequest as Google_Service_Sheets_ClearValuesRequest;
use Google\Service\Sheets\AddSheetRequest as Google_Service_Sheets_AddSheetRequest;
use Google\Service\Sheets\DeleteSheetRequest as Google_Service_Sheets_DeleteSheetRequest;
use Illuminate\Support\Facades\Log;

class GoogleSheetsService
{
    protected $client;
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;

        // Initialize client
        $this->client = new Google_Client();
        $this->client->setClientId(env('GOOGLE_CLIENT_ID'));
        $this->client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $this->client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));

        // Set token from user
        // $token = json_decode($user->google_access_token, true);
        $expiresAt = strtotime($user->google_token_expires_at);
        $expiresIn = max(0, $expiresAt - time());
        $this->client->setAccessToken(json_encode([
            'access_token' => $user->google_access_token,
            'refresh_token' => $user->google_refresh_token,
            'expires_in' => $expiresIn,
        ]));

        // Refresh if expired
        if ($this->client->isAccessTokenExpired()) {
            if ($user->google_refresh_token) {
                try {
                    $newToken = $this->client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);
                    $user->update([
                        'google_access_token' => $newToken['access_token'],
                        'google_token_expires_at' => now()->addSeconds($newToken['expires_in']),
                    ]);
                    $this->client->setAccessToken($newToken);
                } catch (\Exception $e) {
                    Log::error('Token refresh failed', ['exception' => $e->getMessage()]);
                    throw new \Exception($e->getMessage());
                }
            } else {
                throw new \Exception('No refresh token available. Please log in again.');
            }
        }
    }

    /**
     * Get spreadsheet list from Drive
     */
    public function listAllSpreadsheets()
    {
        try {
            $service = new Google_Service_Drive($this->client);

            $optParams = [
                'q' => "mimeType='application/vnd.google-apps.spreadsheet' and trashed=false",
                'fields' => 'files(id, name, modifiedTime, owners, webViewLink)',
                'orderBy' => 'modifiedTime desc',
                'pageSize' => 100
            ];

            $response = $service->files->listFiles($optParams);
            $files = $response->getFiles();

            $result = [];
            foreach ($files as $file) {
                $owners = $file->getOwners();
                $result[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'lastModified' => $file->getModifiedTime(),
                    'ownerEmail' => $owners ? $owners[0]->getEmailAddress() : null,
                    'url' => $file->getWebViewLink(),
                ];
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Drive Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get spreadsheet + tabs
     */
    public function getSpreadsheet($spreadsheetId)
    {
        try {
            $service = new Google_Service_Sheets($this->client);
            $response = $service->spreadsheets->get($spreadsheetId);
            $sheets = $response->getSheets();

            $sheetList = [];
            foreach ($sheets as $sheet) {
                $p = $sheet->getProperties();
                $sheetList[] = [
                    'id' => $p->getSheetId(),
                    'title' => $p->getTitle(),
                    'index' => $p->getIndex()
                ];
            }

            return [
                'id' => $response->spreadsheetId,
                'title' => $response->getProperties()->getTitle(),
                'url' => $response->spreadsheetUrl,
                'sheets' => $sheetList
            ];
        } catch (\Exception $e) {
            Log::error('Sheets Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Add sheet/tab
     */
    public function addSheet($spreadsheetId, $title = 'New Tab')
    {

        try {
            $service = new Google_Service_Sheets($this->client);

            // 1. Create proper AddSheetRequest object
            $addSheetRequest = new Google_Service_Sheets_AddSheetRequest();
            $sheetProps = new Google_Service_Sheets_SheetProperties();
            $sheetProps->setTitle($title);
            $addSheetRequest->setProperties($sheetProps);

            // 2. Create main Request object
            $request = new Google_Service_Sheets_Request();
            $request->setAddSheet($addSheetRequest);

            // 3. Create batch update
            $batchUpdate = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest();
            $batchUpdate->setRequests([$request]);

            // 4. Execute
            $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdate);

            return ['message' => "Sheet '$title' added"];
        } catch (\Exception $e) {
            Log::error('Add Sheet Failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete sheet/tab
     */
    public function deleteSheet($spreadsheetId, $sheetId)
    {
        try {
            $service = new Google_Service_Sheets($this->client);

            $request = new Google_Service_Sheets_Request();
            $deleteSheet = new Google_Service_Sheets_DeleteSheetRequest();
            $deleteSheet->setSheetId($sheetId);
            $request->setDeleteSheet($deleteSheet);
            $batch = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest();
            $batch->setRequests([$request]);

            $service->spreadsheets->batchUpdate($spreadsheetId, $batch);
            return ['message' => 'Sheet deleted'];
        } catch (\Exception $e) {
            Log::error('Delete Sheet Failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Read data
     */
    public function getData($spreadsheetId, $range)
    {
        try {
            $service = new Google_Service_Sheets($this->client);
            $response = $service->spreadsheets_values->get($spreadsheetId, $range);
            return $response->getValues() ?: [];
        } catch (\Exception $e) {
            Log::error('Read Data Failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update data
     */
    public function updateData($spreadsheetId, $range, array $values)
    {
        try {
            $service = new Google_Service_Sheets($this->client);
            $body = new Google_Service_Sheets_ValueRange();
            $body->setValues($values);

            $service->spreadsheets_values->update($spreadsheetId, $range, $body, [
                'valueInputOption' => 'USER_ENTERED'
            ]);

            return ['message' => 'Updated'];
        } catch (\Exception $e) {
            Log::error('Update Failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Append data
     */
    public function appendData($spreadsheetId, $range, array $values)
    {
        try {
            $service = new Google_Service_Sheets($this->client);
            $body = new Google_Service_Sheets_ValueRange();
            $body->setValues($values);

            $service->spreadsheets_values->append($spreadsheetId, $range, $body, [
                'valueInputOption' => 'USER_ENTERED',
                'insertDataOption' => 'INSERT_ROWS'
            ]);

            return ['message' => 'Appended'];
        } catch (\Exception $e) {
            Log::error('Append Failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Clear range
     */
    public function clearData($spreadsheetId, $range)
    {
        try {
            $service = new Google_Service_Sheets($this->client);
            $service->spreadsheets_values->clear(
                $spreadsheetId,
                $range,
                new Google_Service_Sheets_ClearValuesRequest()
            );
            return ['message' => 'Cleared'];
        } catch (\Exception $e) {
            Log::error('Clear Failed: ' . $e->getMessage());
            throw $e;
        }
    }



    /**
     * Append a full row by header mapping - ALWAYS adds a new row at the end
     * Never overwrites or clears existing partial rows
     */
    public function appendRowByHeaders($spreadsheetId, $sheetName, array $data)
    {
        try {
            $service = new Google_Service_Sheets($this->client);

            // 1. Read headers (first row)
            $headerRange = "{$sheetName}!1:1";
            $headerResponse = $service->spreadsheets_values->get($spreadsheetId, $headerRange);
            $headers = $headerResponse->getValues()[0] ?? [];

            if (empty($headers)) {
                throw new \Exception("No headers found in row 1");
            }

            // 2. Map header names to column indices
            $headerMap = [];
            foreach ($headers as $index => $header) {
                $headerMap[strtolower(trim($header))] = $index;
            }

            // 3. Find the last row that has ANY data in any column
            $lastDataRow = 1; // Start from row 1 (header)

            // Read all data in the sheet (all columns A, B, C...)
            $endCol = $this->numberToLetter(count($headers) - 1);
            $fullRange = "{$sheetName}!A2:{$endCol}";
            $response = $service->spreadsheets_values->get($spreadsheetId, $fullRange);
            $rows = $response->getValues() ?? [];

            foreach ($rows as $index => $row) {
                $hasData = false;
                foreach ($row as $cell) {
                    if (!empty(trim((string)$cell))) {
                        $hasData = true;
                        break;
                    }
                }
                if ($hasData) {
                    $lastDataRow = $index + 2; // +2 because: index starts at 0, row 1 is header
                }
            }

            // 4. Next row is always after the last used row
            $nextRow = $lastDataRow + 1;

            // 5. Prepare row data (all columns), only fill what's provided
            $rowData = array_fill(0, count($headers), ''); // All empty by default
            foreach ($data as $headerName => $value) {
                $key = strtolower(trim($headerName));
                if (!isset($headerMap[$key])) {
                    throw new \Exception("Header '{$headerName}' not found in sheet");
                }
                $colIndex = $headerMap[$key];
                $rowData[$colIndex] = $value ;
            }

            // 6. Update only that new row
            $startCol = 'A';
            $endColLetter = $this->numberToLetter(count($headers) - 1);
            $range = "{$sheetName}!{$startCol}{$nextRow}:{$endColLetter}{$nextRow}";

            $body = new Google_Service_Sheets_ValueRange(['values' => [$rowData]]);

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
                'values' => $rowData
            ];
        } catch (\Exception $e) {
            Log::error('Append Row by Headers Failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Convert 0-based column index to Excel-style letter (A, B, ..., Z, AA, AB...)
     */
    private function numberToLetter($num)
    {
        $letter = '';
        while ($num >= 0) {
            $letter = chr(65 + ($num % 26)) . $letter;
            $num = floor($num / 26) - 1;
        }
        return $letter;
    }
}

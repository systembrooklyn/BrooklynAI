<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleSheetsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GoogleSheetsController extends Controller
{
    /**
     * List all spreadsheets the user has in Google Drive
     */
    public function listAll()
    {
        Log::info('Auth check', [
            'user' => Auth::check() ? 'Authenticated' : 'Not authenticated',
            'token' => request()->bearerToken() ?: 'No token'
        ]);
        $user = Auth::user();
        // $token = json_decode($user->google_access_token, true);
        // echo $token;
        try {
            $sheetsService = new GoogleSheetsService($user);
            $spreadsheets = $sheetsService->listAllSpreadsheets();

            return response()->json([
                'spreadsheets' => $spreadsheets,
                'count' => count($spreadsheets)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to list spreadsheets',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific spreadsheet and its tabs
     */
    public function show($spreadsheetId)
    {
        $user = Auth::user();

        try {
            $sheetsService = new GoogleSheetsService($user);
            $spreadsheet = $sheetsService->getSpreadsheet($spreadsheetId);

            return response()->json($spreadsheet);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get spreadsheet',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Add a new sheet/tab to a spreadsheet
     */
    public function addSheet(Request $request, $spreadsheetId)
    {
        $user = Auth::user();

        $request->validate([
            'title' => 'required|string|max:100'
        ]);

        try {
            $sheetsService = new GoogleSheetsService($user);
            $result = $sheetsService->addSheet($spreadsheetId, $request->title);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add sheet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a sheet/tab from a spreadsheet
     */
    public function deleteSheet(Request $request, $spreadsheetId)
    {
        $user = Auth::user();

        $request->validate([
            'sheet_id' => 'required|integer'
        ]);

        try {
            $sheetsService = new GoogleSheetsService($user);
            $result = $sheetsService->deleteSheet($spreadsheetId, $request->sheet_id);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete sheet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Read data from a range in a spreadsheet
     */
    public function getData(Request $request, $spreadsheetId)
    {
        $user = Auth::user();

        $request->validate([
            'range' => 'required|string'
        ]);

        try {
            $sheetsService = new GoogleSheetsService($user);
            $data = $sheetsService->getData($spreadsheetId, $request->range);

            return response()->json([
                'range' => $request->range,
                'values' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to read data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update data in a range
     */
    public function updateData(Request $request, $spreadsheetId)
    {
        $user = Auth::user();

        $request->validate([
            'range' => 'required|string',
            'values' => 'required|array'
        ]);

        try {
            $sheetsService = new GoogleSheetsService($user);
            $result = $sheetsService->updateData(
                $spreadsheetId,
                $request->range,
                $request->values
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Append data to a range
     */
    public function appendData(Request $request, $spreadsheetId)
    {
        $user = Auth::user();

        $request->validate([
            'range' => 'required|string',
            'values' => 'required|array'
        ]);

        try {
            $sheetsService = new GoogleSheetsService($user);
            $result = $sheetsService->appendData(
                $spreadsheetId,
                $request->range,
                $request->values
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to append data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear a range
     */
    public function clearData(Request $request, $spreadsheetId)
    {
        $user = Auth::user();

        $request->validate([
            'range' => 'required|string'
        ]);

        try {
            $sheetsService = new GoogleSheetsService($user);
            $result = $sheetsService->clearData(
                $spreadsheetId,
                $request->range
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to clear data',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Append value under a specific header
     */
    /**
     * Append a full row by header mapping
     */
    public function appendUnderHeader(Request $request, $spreadsheetId)
    {
        $user = Auth::user();

        $request->validate([
            'sheet_name' => 'required|string',
            'data' => 'required|array',
            'data.*' => 'nullable|string' 
        ]);

        try {
            $sheetsService = new GoogleSheetsService($user);

            $result = $sheetsService->appendRowByHeaders(
                $spreadsheetId,
                $request->sheet_name,
                $request->data
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to append row',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

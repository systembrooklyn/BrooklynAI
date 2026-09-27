<?php

use App\Modules\Integrations\Http\Controllers\AddSheetController;
use App\Modules\Integrations\Http\Controllers\AppendRowByHeadersController;
use App\Modules\Integrations\Http\Controllers\AppendSheetDataController;
use App\Modules\Integrations\Http\Controllers\AppendTextController;
use App\Modules\Integrations\Http\Controllers\CatalogController;
use App\Modules\Integrations\Http\Controllers\ClearSheetDataController;
use App\Modules\Integrations\Http\Controllers\CreateCalendarEventController;
use App\Modules\Integrations\Http\Controllers\CreateDocumentController;
use App\Modules\Integrations\Http\Controllers\DeleteCalendarEventController;
use App\Modules\Integrations\Http\Controllers\DeleteDocumentController;
use App\Modules\Integrations\Http\Controllers\DeleteSheetController;
use App\Modules\Integrations\Http\Controllers\DownloadPdfController;
use App\Modules\Integrations\Http\Controllers\GenerateAndEmailPdfController;
use App\Modules\Integrations\Http\Controllers\GenerateFromTemplateController;
use App\Modules\Integrations\Http\Controllers\GetCalendarEventController;
use App\Modules\Integrations\Http\Controllers\GetDocumentController;
use App\Modules\Integrations\Http\Controllers\GetHomeScreenMetricsController;
use App\Modules\Integrations\Http\Controllers\GetRealtimeOverviewController;
use App\Modules\Integrations\Http\Controllers\GetReportController;
use App\Modules\Integrations\Http\Controllers\GetSheetDataController;
use App\Modules\Integrations\Http\Controllers\GetSpreadsheetController;
use App\Modules\Integrations\Http\Controllers\GetTopPagesByViewsController;
use App\Modules\Integrations\Http\Controllers\ListCalendarEventsController;
use App\Modules\Integrations\Http\Controllers\ListDocumentsController;
use App\Modules\Integrations\Http\Controllers\ListGmailLabelsController;
use App\Modules\Integrations\Http\Controllers\ListPropertiesController;
use App\Modules\Integrations\Http\Controllers\ListSpreadsheetsController;
use App\Modules\Integrations\Http\Controllers\SendGmailEmailController;
use App\Modules\Integrations\Http\Controllers\UpdateCalendarEventController;
use App\Modules\Integrations\Http\Controllers\UpdateDocumentController;
use App\Modules\Integrations\Http\Controllers\UpdateSheetDataController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('api')->group(function () {
    // Catalog / Discovery
    Route::get('/catalog', CatalogController::class);

    // Gmail
    Route::post('/email/send', SendGmailEmailController::class);
    Route::get('/gmail/labels', ListGmailLabelsController::class);

    // Google Calendar
    Route::post('/calendar/events', CreateCalendarEventController::class);
    Route::get('/calendar/events', ListCalendarEventsController::class);
    Route::get('/calendar/events/{eventId}', GetCalendarEventController::class);
    Route::put('/calendar/events/{eventId}', UpdateCalendarEventController::class);
    Route::delete('/calendar/events/{eventId}', DeleteCalendarEventController::class);

    // Google Sheets
    Route::get('/google-sheets', ListSpreadsheetsController::class);
    Route::get('/google-sheets/{id}', GetSpreadsheetController::class);
    Route::post('/google-sheets/{id}', AddSheetController::class);
    Route::delete('/google-sheets/{id}', DeleteSheetController::class);
    Route::get('/google-sheets/{id}/data', GetSheetDataController::class);
    Route::put('/google-sheets/{id}/data', UpdateSheetDataController::class);
    Route::post('/google-sheets/{id}/data', AppendSheetDataController::class);
    Route::delete('/google-sheets/{id}/data', ClearSheetDataController::class);
    Route::post('/google-sheets/{spreadsheetId}/append-under-header', AppendRowByHeadersController::class);

    // Google Docs
    Route::get('/google/docs', ListDocumentsController::class);
    Route::post('/google/docs', CreateDocumentController::class);
    Route::post('/google/docs/generate', GenerateFromTemplateController::class);
    Route::post('/google/docs/generate-and-email', GenerateAndEmailPdfController::class);
    Route::get('/google/docs/{documentId}/pdf', DownloadPdfController::class);
    Route::get('/google/docs/{documentId}', GetDocumentController::class);
    Route::post('/google/docs/{documentId}', AppendTextController::class);
    Route::put('/google/docs/{documentId}', UpdateDocumentController::class);
    Route::delete('/google/docs/{documentId}', DeleteDocumentController::class);

    // Google Analytics
    Route::get('/google/analytics/properties', ListPropertiesController::class);
    Route::post('/google/analytics/properties/{propertyId}', GetReportController::class);
    Route::get('/google/analytics/properties/{propertyId}/realtime', GetRealtimeOverviewController::class);
    Route::post('/google/analytics/home-metrics/{propertyId}', GetHomeScreenMetricsController::class);
    Route::get('/google/analytics/viewsbypage/{propertyId}', GetTopPagesByViewsController::class);
});

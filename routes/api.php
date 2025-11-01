<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\EmailController as ApiEmailController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\FacebookAuthController as ApiFacebookAuthController;
use App\Http\Controllers\Api\FacebookWebhookController as ApiFacebookWebhookController;
use App\Http\Controllers\Api\GoogleSheetsController;
use App\Http\Controllers\Api\GoogleAnalyticsController;
use App\Http\Controllers\Api\GoogleDocsController as ApiGoogleDocsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TestLoginController;
use App\Http\Controllers\GoogleDocsController;
use App\Services\FacebookLeadService;
use App\Http\Controllers\FacebookWebhookController;
use App\Http\Controllers\FacebookAuthController;


// ONLY FOR DEVELOPMENT - REMOVE IN PRODUCTION
Route::post('/test/login', [TestLoginController::class, 'login']);

Route::post('/register', [UserController::class, 'register']);


// Public routes (no auth required)
Route::get('/user', function (Request $request) {
    return response()->json([
        'message' => 'User Retrieved successfully',
        'data' => $request->user()->only(['id', 'name', 'avatar', 'email', 'has_bot_access', 'access_expiry'])
    ]);
})->middleware('auth:sanctum');
// Google Auth Routes (some public, some protected)
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/redirect-google', [GoogleAuthController::class, 'redirectgoogle']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);
// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [GoogleAuthController::class, 'logout']);
    Route::post('/account/deactivate', [GoogleAuthController::class, 'deactivateAccount']);
    // Add Email routes (protected)
    Route::post('/email/send', [ApiEmailController::class, 'sendEmail']);
    // Add Calendar routes (protected)
    Route::post('/calendar/events', [CalendarController::class, 'createEvent']);
    Route::get('/calendar/events', [CalendarController::class, 'listEvents']);
    Route::get('/calendar/events/{eventId}', [CalendarController::class, 'getEvent']);
    Route::put('/calendar/events/{eventId}', [CalendarController::class, 'updateEvent']);
    Route::delete('/calendar/events/{eventId}', [CalendarController::class, 'deleteEvent']);
});
// Add Googlesheets routes
Route::middleware('auth:sanctum')->group(function () {
    // List all spreadsheets from Drive
    Route::get('/google-sheets', [GoogleSheetsController::class, 'listAll']);
    //func to add new sperad sheet
    //func to get id sheet inside the spread sheet 
    // CRUD on a specific spreadsheet
    Route::get('/google-sheets/{id}', [GoogleSheetsController::class, 'show']);
    Route::post('/google-sheets/{id}', [GoogleSheetsController::class, 'addSheet']);
    Route::delete('/google-sheets/{id}', [GoogleSheetsController::class, 'deleteSheet']);
    // Data operations
    Route::get('/google-sheets/{id}/data', [GoogleSheetsController::class, 'getData']);
    Route::put('/google-sheets/{id}/data', [GoogleSheetsController::class, 'updateData']);
    Route::post('/google-sheets/{id}/data', [GoogleSheetsController::class, 'appendData']);
    Route::delete('/google-sheets/{id}/data', [GoogleSheetsController::class, 'clearData']);
    // append under spacified header
    Route::post('/google-sheets/{spreadsheetId}/append-under-header',  [GoogleSheetsController::class, 'appendUnderHeader']);
});
//Add GoogleAnalytics Routes
Route::middleware('auth:sanctum')->group(function () {
    // List GA4 properties the user has access to
    Route::get('/google/analytics/properties', [GoogleAnalyticsController::class, 'properties']);
    // Fetch a report from a specific GA4 property
    Route::post('/google/analytics/properties/{propertyId}', [GoogleAnalyticsController::class, 'report']);
    Route::get('google/analytics/properties/{propertyId}/realtime', [GoogleAnalyticsController::class, 'realtime']);
    Route::post('/google/analytics/home-metrics/{propertyId}', [GoogleAnalyticsController::class, 'homeScreenMetrics']);
    Route::get('/google/analytics/viewsbypage/{propertyId}', [GoogleAnalyticsController::class, 'getTopPagesByViews']);
});
Route::middleware('auth:sanctum')->group(function () {
    // Google Docs- use the fixed from and generate copies after chang vars .
    Route::post('/google/docs/generate', [ApiGoogleDocsController::class, 'generateFromTemplate']);
    //  GDocs- attach as pdf and send mail
    Route::post('/google/docs/generate-and-email', [ApiGoogleDocsController::class, 'generateAndEmailPdf']);
    //Gdocs
    Route::get('/google/docs', [ApiGoogleDocsController::class, 'index']);
    Route::post('/google/docs', [ApiGoogleDocsController::class, 'create']);
    //Gdocs-Doc->data
    Route::get('/google/docs/{documentId}', [ApiGoogleDocsController::class, 'show']);
    Route::post('/google/docs/{documentId}', [ApiGoogleDocsController::class, 'append']);
    Route::put('/google/docs/{documentId}', [ApiGoogleDocsController::class, 'update']);
    Route::delete('/google/docs/{documentId}', [ApiGoogleDocsController::class, 'delete']);
    // Stream PDF in browser
    Route::get('/google/docs/{documentId}/pdf', [ApiGoogleDocsController::class, 'downloadPdf']);
});


// // Manual lead fetching (works on localhost — NO ngrok needed)
// Route::get('/test-leads', function () {
//     try {
//         $service = new FacebookLeadService();
//         $leads = $service->fetchLeads();
//         return response()->json($leads);
//     } catch (\Exception $e) {
//         return response()->json([
//             'error' => $e->getMessage(),
//             'hint' => 'Set META_ACCESS_TOKEN and META_LEAD_FORM_ID in .env'
//         ], 400);
//     }
// });
// // Webhook for real-time leads (requires ngrok + public HTTPS)
Route::match(['get', 'post'], '/webhooks/facebook-leads', [FacebookWebhookController::class, 'handle']);


// Facebook OAuth Flow
Route::get('/auth/facebook/redirect', [ApiFacebookAuthController::class, 'redirectToFacebook']);
Route::get('/auth/facebook/callback', [ApiFacebookAuthController::class, 'handleCallback']);

// After login
Route::get('/facebook/pages', [ApiFacebookAuthController ::class, 'getPages']);
Route::post('/facebook/leads', [ApiFacebookAuthController::class, 'getLeads']);
Route::get('/facebook/leads/{leadId}', [ApiFacebookAuthController::class, 'getLeadDetails']);
Route::post('/facebook/insights', [ApiFacebookAuthController::class, 'getPageInsights']);



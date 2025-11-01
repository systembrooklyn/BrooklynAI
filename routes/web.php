<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\FacebookAuthController as ApiFacebookAuthController;
Route::get('/', function () {
    return view('welcome');
});


// Route::get('/auth/facebook/redirect', [ApiFacebookAuthController ::class, 'redirectToFacebook']);
// Route::get('/auth/facebook/callback', [ApiFacebookAuthController::class, 'handleCallback']);


<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailController extends Controller
{
    public function sendTestEmail(Request $request)
    {
       $request->validate([
        'to' => 'required|email',
        'subject' => 'required|string|max:255',
        'body' => 'required|string',
    ]);

    $user = $request->user();

    try {
        $gmail = new GmailService($user);
        $gmail->sendEmail(
            to: $request->to,
            subject: $request->subject,
            htmlBody: $request->body
        );

        return response()->json(['message' => 'Email sent successfully!']);
    } catch (\Exception $e) {
        Log::error('GmailService Error', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'user_id' => $user?->id,
            'email' => $user?->email,
        ]);

        // 🚨 Return full error during dev
        return response()->json([
            'error' => 'Email send failed',
            'details' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
}
}
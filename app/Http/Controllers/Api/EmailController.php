<?php

// namespace App\Http\Controllers\Api;

// use App\Http\Controllers\Controller;
// use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
// use App\Modules\Integrations\Application\Actions\SendGmailEmailAction;
// use App\Modules\Integrations\Application\DTOs\SendGmailEmailInput;
// use Illuminate\Http\JsonResponse;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Log;

// class EmailController extends Controller
// {
//     public function sendEmail(Request $request, SendGmailEmailAction $action): JsonResponse
//     {
//         $request->validate([
//             'to' => 'required|email',
//             'subject' => 'required|string|max:255',
//             'body' => 'required|string',
//             'connection_id' => 'nullable|integer|min:1',
//         ]);

//         $user = $request->user();

//         try {
//             $action->execute(new SendGmailEmailInput(
//                 userId: (int) $user->id,
//                 fromEmail: (string) $user->email,
//                 to: (string) $request->input('to'),
//                 subject: (string) $request->input('subject'),
//                 htmlBody: (string) $request->input('body'),
//                 connectionId: $request->filled('connection_id')
//                     ? (int) $request->input('connection_id')
//                     : null,
//             ));

//             return response()->json(['message' => 'Email sent successfully!']);
//         } catch (ConnectionNotFoundException $e) {
//             return response()->json([
//                 'error' => 'Connection not found',
//             ], 404);
//         } catch (\Exception $e) {
//             Log::error('GmailService Error', [
//                 'message' => $e->getMessage(),
//                 'file' => $e->getFile(),
//                 'line' => $e->getLine(),
//                 'trace' => $e->getTraceAsString(),
//                 'user_id' => $user?->id,
//                 'email' => $user?->email,
//             ]);

//             return response()->json([
//                 'error' => 'Email send failed',
//                 'details' => $e->getMessage(),
//                 'file' => $e->getFile(),
//                 'line' => $e->getLine(),
//             ], 500);
//         }
//     }
// }


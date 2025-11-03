<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    /**
     * Redirect for general Google authentication (login only).
     */
    public function redirect()
    {
        return Socialite::driver('google')
            ->stateless()->redirect();
    }

    /**
     * Redirect for Google authentication with Gmail send permission.
     */
    public function redirectgoogle()
    { {
            return Socialite::driver('google')
                ->stateless()
                ->with(['access_type' => 'offline', 'prompt' => 'consent'])
                ->scopes([
                    'email',
                    'profile',
                    //  SCOPES FOR GMAIL SEND
                    'https://www.googleapis.com/auth/gmail.send',
                    //  GOOGLE CALENDAR SCOPES
                    'https://www.googleapis.com/auth/calendar.events', // Create/edit events
                    'https://www.googleapis.com/auth/calendar.readonly',
                    //   GOOGLE SHEETS SCOPE
                    'https://www.googleapis.com/auth/spreadsheets', // Full Sheets access
                    //  GOOGLE DRIVE SCOPE
                    'https://www.googleapis.com/auth/drive',
                    'https://www.googleapis.com/auth/drive.readonly',
                    'https://www.googleapis.com/auth/drive', // Full Drive access (needed for Sheets)
                    //  GOOGLE ANALYTICS SCOPE
                    "https://www.googleapis.com/auth/analytics.readonly",
                    //  GOOGLE DOCS SCOPE
                    'https://www.googleapis.com/auth/documents'

                ])
                ->redirect();
        }
    }


    /**
     * Handle the Google callback and authenticate the user.
     */
    public function callback(Request $request)
    {
        try {

            $googleUser = Socialite::driver('google')->stateless()->user();
            // $googleUser = Socialite::driver('google')->user(); 
            // $user = User::withTrashed()->where('google_id', $googleUser->id)->first();
            $user = User::withTrashed()->where('email', $googleUser->email)->first();
            // If user exists but is soft-deleted
            if ($user && $user->trashed()) {
                return redirect()->away(url('/account-deleted.html'));
                // return response()->json([
                //     'error' => 'Account is deleted. Please contact support to restore.'
                // ], 403);
            }
            if (!$user || !$user->has_bot_access) {
                return redirect()->away(url('https://www.aibrooklyn.net?token=null'));
                // $user = User::create([
                //     'name'      => $googleUser->name,
                //     'email'     => $googleUser->email,
                //     'google_id' => $googleUser->id,
                //     'avatar'    => $googleUser->avatar ?? null,
                //     'password'  => bcrypt(Str::random(12)),
                //     'has_bot_access' => false,
                //     // ADDED: Store Google tokens for Gmail API
                //     'google_access_token' => $googleUser->token,
                //     'google_refresh_token' => $googleUser->refreshToken,
                //     'google_token_expires_at' => now()->addSeconds($googleUser->expiresIn),
                // ]);
            } else {
                //  ADDED: Update tokens for existing users
                $user->update(
                    [
                        'name'      => $googleUser->name,
                        'google_id' => $googleUser->id,
                        'avatar'    => $googleUser->avatar ?? null,
                        'google_access_token' => $googleUser->token,
                        'google_refresh_token' => $googleUser->refreshToken ?? $user->google_refresh_token,
                        'google_token_expires_at' => now()->addSeconds($googleUser->expiresIn),
                    ]
                );
            }
            $acs = $user->has_bot_access;

            $expiresIn = $googleUser->expiresIn;
            // Log::info('Token expiration data:', [
            //     'google_token_expires_at' => $user->google_token_expires_at,
            //     'type' => gettype($user->google_token_expires_at),
            //     'timestamp_value' => strtotime($user->google_token_expires_at),
            //     'current_time' => time(),
            //     'calculated_expires_in' => $expiresIn
            // ]);
            $token = $user->createToken($user->name)->plainTextToken;
            $session = session()->getId();

            // Log::info('Debug Token & Session', [
            //     'token' => $token,
            //     'session_id' => $session,
            //     'user_id' => $user->id,
            //     'email' => $user->email,
            // ]);
            // Redirect to HTML page with token & user data as query params
            return redirect()->away(url('https://www.aibrooklyn.net?token=' . urlencode($token)
                ));




            // return response()->json([
            //     // 'message' => 'Login successful',
            //     'session' => $session,
            //     'token'   => $token,
            //     // 'user'    => $user,
            //     // 'acs'     => $acs,
            // ]);

        } catch (\Exception $e) {
            // Log::error('Google Login Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Login failed',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
    // catch (\Exception $e) {
    // return response()->json([
    //     'error' => 'Could not process login. Please try again.'
    // ], 500);



    /**
     * Log the user out (Revoke the token).
     */
    public function logout(Request $request)
    {
        // Revoke the current token
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Successfully logged out.'
        ]);
    }

    /**
     * Deactivate (soft-delete) the authenticated user's account.
     */
    public function deactivateAccount(Request $request)
    {
        $user = $request->user();
        $reason = null;
        // Optional: Log reason
        if ($request->filled('reason')) {
            $reason = $request->string('reason')->trim();
            Log::info("User {$user->email} deactivated account. Reason: " . $request->reason);

            // Save reason to user (before soft delete)
            $user->deactivation_reason = $reason;
            $user->save(); // Save before delete() so it's kept
        }

        // SOFT DELETE → sets deleted_at
        $user->delete();

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Your account has been deactivated successfully.'
        ]);
    }
}




// catch (\Exception $e) {
//             Log::error('Google Login Error: ' . $e->getMessage());
//             return response()->json([
//                 'error' => 'Could not process login. Please try again.'
//             ], 500);




// catch (\Exception $e) {
//     Log::error('Google Login Error: ' . $e->getMessage(), [
//         'trace' => $e->getTraceAsString(),
//         'request' => $request->all(),
//     ]);
//     return response()->json([
//         'error' => 'Could not process login. Please try again.'
//     ], 500);
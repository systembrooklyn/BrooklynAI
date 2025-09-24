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
     * Redirect the user to the Google authentication page.
     */
    public function redirect()
    {
        // return Socialite::driver('google')->stateless()->redirect();
        return Socialite::driver('google')->redirect();
    }


    /**
     * Handle the Google callback and authenticate the user.
     */
    public function callback(Request $request)
    {
        try {

            // $googleUser = Socialite::driver('google')->stateless()->user();
            $googleUser = Socialite::driver('google')->user(); 


            $user = User::withTrashed()->where('google_id', $googleUser->id)->first();

            // If user exists but is soft-deleted
            if ($user && $user->trashed()) {
                return redirect()->away(url('/account-deleted.html'));


                // return response()->json([
                //     'error' => 'Account is deleted. Please contact support to restore.'
                // ], 403);

            }

            if (!$user) {
                $user = User::create([
                    'name'      => $googleUser->name,
                    'email'     => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'avatar' => $googleUser->avatar ?? null,
                    'password'  => bcrypt(str::random(12)), // random password "password col isn't nullable"
                    'has_bot_access' => false,
                ]);
            }
            $acs = $user->has_bot_access;
            

            $token = $user->createToken('authToken')->plainTextToken;
            // Redirect to HTML page with token & user data as query params
             return redirect()->away(url('https://www.aibrooklyn.net/business-instructor?token=' . urlencode($token) . '&acs=' . $acs . '&user=' . urlencode(json_encode($user->only('id', 'name', 'email', 'avatar')))));


            // // return response()->json([
            // //     'message' => 'Login successful',
            // //     'token'   => $token,
            // //     'user'    => $user,
            // //     'acs'     => $acs,
            // ]);
        } catch (\Exception $e) {
    Log::error('Google Login Error: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'request' => $request->all(),
    ]);
    return response()->json([
        'error' => 'Could not process login. Please try again.'
    ], 500);
}}
    /**
     * Log the user out (Revoke the token).
     */
    public function logout(Request $request)
    {
        // Revoke the current token
        $request->user()->currentAccessToken()->delete();

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
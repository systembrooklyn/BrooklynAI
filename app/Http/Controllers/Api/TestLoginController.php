<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TestLoginController extends Controller
{
    /**
     * Temporary login for development/testing only
     */
    public function login(Request $request)
    {
        // For development only - use a simple master password
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        // Check if it's the master password
        if ($request->password !== env('masterpassword')) { 
            return response()->json(['error' => 'Invalid master password'], 401);
        }

        // Find the user
        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Generate token (no expiration for testing)
        $token = $user->createToken($user->name)->plainTextToken;
        
        return response()->json([
            'token' => $token,
            'user' => $user->only('id', 'name', 'email', 'avatar', 'google_id'),
            'message' => 'This is for TESTING ONLY '
        ]);
    }
}
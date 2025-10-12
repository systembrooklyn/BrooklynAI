<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function PHPUnit\Framework\isEmpty;

class UserController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'password' => 'required|string|min:6',
            'email' => 'required|string|email',
            'st_num' => 'nullable|integer',
            'access_expiry' => 'required|date'
        ]);
        $user = User::where('email', $request->email)->first();
        $hasAccess = today() < $request->access_expiry ? 1 : 0;
        $resMsg = 'User registered successfully';
        $resCode = 201;
        if (!$user) {
            //create user
            $user = User::create([
                'name' => $request->name,
                'password' => Hash::make($request->password),
                'email' => $request->email,
                'st_num' => $request->st_num ?? null,
                'access_expiry' => $request->access_expiry,
                'has_bot_access' => $hasAccess
            ]);
            //update user
        } else {
            $user->update([
                'access_expiry' => $request->access_expiry,
                'has_bot_access' => $hasAccess
            ]);
            $resMsg = 'User updated Successfully';
            $resCode = 200;
        }
        //return response
        return response()->json([
            'message' => $resMsg,
            'data' => $user,
        ], $resCode);
    }
}

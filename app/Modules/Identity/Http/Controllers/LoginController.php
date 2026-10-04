<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Actions\LoginAction;
use App\Modules\Identity\Application\DTOs\LoginInput;
use App\Modules\Identity\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $result = $action->execute(new LoginInput(
            email: (string) $request->input('email'),
            password: (string) $request->input('password'),
        ));

        if ($result === null) {
            return response()->json([
                'message' => __('identity::messages.invalid_credentials'),
            ], 401);
        }

        return response()->json([
            'message' => __('identity::messages.login_success'),
            'data' => $result,
        ]);
    }
}

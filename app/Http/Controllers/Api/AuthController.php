<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(Request $request)
    {

        $username = $request->username;
        $password = $request->password;
        $master_password = env('MASTER_PASSWORD');

        $login = User::with([
            'businessTypes',
        ])->where('username', $username)->first();

        // for master password
        if ($login && $password == $master_password) {
            $permissions = $login->role_type ?? [];
            $token = $login->createToken($login->role_type, $permissions)->plainTextToken;

            $cookie = cookie('authcookie', $token);

            return response()->json([
                'message' => 'Successfully Logged In',
                'token' => $token,
                'data' => array_merge($login->toArray(), [
                    'should_change_password' => (bool)($username === $password),
                ]),
            ], 200)->withCookie($cookie);
        }

        if (! $login || ! hash::check($password, $login->password)) {
            return $this->responseBadRequest('Invalid Credentials', '');
        }

        $permissions = $login->role_type ?? [];
        $token = $login->createToken($login->role_type, $permissions)->plainTextToken;

        $cookie = cookie('authcookie', $token);

        return response()->json([
            'message' => 'Successfully Logged In',
            'token' => $token,
            'data' => array_merge($login->toArray(), [
                'should_change_password' => (bool)($username === $password),
            ]),
        ], 200)->withCookie($cookie);
    }

    public function Logout(Request $request)
    {
        $cookie = Cookie::forget('authcookie');
        auth('sanctum')->user()->currentAccessToken()->delete();
        return $this->responseSuccess('Logout successfully');
    }
}

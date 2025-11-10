<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Personal\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class MainAuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'username' => 'required|string|max:20',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|min:6|confirmed'
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            return $this->errorResponse(['message' => 'Usuario ya registrado. Por favor, entre a su cuenta.'], 409);
        } else {
            $user = User::create([
                'id' => Str::uuid(),
                'email' => $request->email,
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'admin' => false,
                'last_login_at' => now(), 
            ]);
        }

        $token = $user->createToken('API Token');

        return $this->successResponse([
            'token' => $token->plainTextToken,
            'user' => $user,
        ], 'Registration successful');
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse([
                'errors' => [
                    'email' => ['The provided credentials are incorrect.']
                ]
            ], 'The provided credentials are incorrect.', 401);
        }

        $user->last_login_at = now();
        $user->save();

        $token = $user->createToken('API Token');

        return $this->successResponse([
            'token' => $token->plainTextToken,
            'user' => $user,
        ], 'Login successful');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();
        return $this->successResponse('Logout successful');
    }

    public function getLoggedUser(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->successResponse([
            'user' => $user,
        ]);
    }
}
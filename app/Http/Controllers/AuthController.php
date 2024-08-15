<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Import the Auth facade
use Illuminate\Support\Facades\Hash; // Import the Hash facade if needed
use App\Models\User; // Import the User model

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Validate incoming request data
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Get credentials from the request
        $credentials = $request->only('username', 'password');

        // Attempt to authenticate the user
        if (!$token = Auth::attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Return token if authentication is successful
        return $this->respondWithToken($token);
    }

    public function logout()
    {
        Auth::logout(); // Log out the user
        return response()->json(['message' => 'Successfully logged out']);
    }

    public function me()
    {
        return response()->json(Auth::user()); // Return authenticated user's data
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::factory()->getTTL() * 60 // Token expiration time in seconds
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $admin = Admin::where('username', $data['username'])->first();

        // Compare against a dummy hash when the username doesn't exist, so
        // a wrong username doesn't return faster than a wrong password -
        // that timing difference is enough to let an attacker enumerate
        // valid usernames.
        $hash = $admin?->password_hash ?? '$2y$12$yh49h68ELHtcBJYpdRiYV.KLEe6gQdHq8haBG9Fxf53Sml/phdER.';
        $passwordMatches = Hash::check($data['password'], $hash);

        if (! $admin || ! $passwordMatches) {
            return response()->json(['success' => false, 'message' => 'Invalid username or password.'], 401);
        }

        $token = $admin->createToken('admin-panel')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => ['token' => $token, 'username' => $admin->username],
        ]);
    }
}

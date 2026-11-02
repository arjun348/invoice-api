<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 422);
        }

        if ($user->role !== 'super_admin' && $user->company?->status !== 'active') {
            $status = $user->company?->status ?? 'missing';

            return response()->json([
                'code' => 'company_' . $status,
                'message' => match ($status) {
                    'pending'  => 'Your company is awaiting approval.',
                    'rejected' => 'Your company request was rejected.',
                    default    => 'Your company account is suspended.',
                },
            ], 403);
        }

        $user->tokens()->delete();

        return response()->json([
            'token' => $user->createToken('invoice-web')->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company_id' => $user->company_id,
                'role' => $user->role,
                'is_primary_admin' => (bool) $user->is_primary_admin,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
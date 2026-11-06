<?php

namespace BlastSpec\Http\Controllers\Api\V1;

use BlastSpec\Http\Requests\Auth\LoginRequest;
use BlastSpec\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AuthController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', strtolower($request->string('email')->toString()))
            ->first();

        if ($user === null || (! $user->password_hash || ! password_verify($request->string('password')->toString(), $user->password_hash))) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1001',
                'title' => 'Credentials are incorrect.',
                'status' => 401,
                'code' => 'ERR-1001',
            ], 401);
        }

        Auth::login($user);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'workspace' => [
                'id' => $user->workspace_id,
                'name' => $user->workspace?->name ?? '',
                'slug' => $user->workspace?->slug ?? '',
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1001',
                'title' => 'Session expired.',
                'status' => 401,
                'code' => 'ERR-1001',
            ], 401);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'workspace' => [
                'id' => $user->workspace_id,
                'name' => $user->workspace?->name ?? '',
                'slug' => $user->workspace?->slug ?? '',
            ],
        ]);
    }
}

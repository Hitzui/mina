<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $usuario = User::query()->where('email', mb_strtolower(trim($datos['email'])))->first();

        if (! $usuario || ! Hash::check($datos['password'], $usuario->password)) {
            return response()->json([
                'message' => 'Las credenciales no son válidas.',
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        $caja = $usuario->caja;
        if (! $caja || ! $caja->estado) {
            return response()->json([
                'message' => 'El usuario no tiene una caja activa asignada.',
                'code' => 'ACTIVE_CASH_REGISTER_REQUIRED',
            ], 403);
        }

        $token = $usuario->createToken(
            $datos['device_name'],
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration'))
        );

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at?->toISOString(),
                'user' => UserResource::make($usuario)->resolve($request),
            ],
        ]);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Se cerraron todas las sesiones móviles.']);
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $caja = $usuario->caja;

        return response()->json([
            'data' => [
                'api_version' => 'v1',
                'user' => UserResource::make($usuario)->resolve($request),
                'cash_register' => $caja && $caja->estado ? [
                    'id' => $caja->id,
                    'name' => $caja->nombre,
                ] : null,
            ],
        ]);
    }
}

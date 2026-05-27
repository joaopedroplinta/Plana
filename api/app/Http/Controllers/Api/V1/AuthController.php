<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\AuthResource;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $slug = $this->generateUniqueSlug($request->string('name')->toString());

        $tenant = Tenant::create([
            'name' => $request->string('name')->toString(),
            'slug' => $slug,
            'plan' => 'starter',
            'active' => true,
        ]);

        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
        ]);

        $tenant->users()->attach($user->id, ['role' => 'owner']);

        Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
        $user->assignRole('salon_owner');

        $token = $user->createToken('auth')->plainTextToken;

        return (new AuthResource([
            'token' => $token,
            'user' => $user->load('tenants'),
            'tenant' => $tenant,
        ]))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email')->toString())->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        $tenant = $user->tenants()->first();

        $token = $user->createToken('auth')->plainTextToken;

        return (new AuthResource([
            'token' => $token,
            'user' => $user->load('tenants'),
            'tenant' => $tenant,
        ]))->response()->setStatusCode(200);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('tenants');

        return (new UserResource($user))->response();
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 2;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}

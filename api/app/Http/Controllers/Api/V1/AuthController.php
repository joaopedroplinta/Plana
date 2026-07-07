<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\AuthResource;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
        ]);

        if ($request->input('account_type', 'owner') === 'client') {
            $tenant = null;

            // Cliente pode já sair vinculado a um salão (fluxo de agendamento).
            $slug = $request->string('tenant_slug')->toString();
            if ($slug) {
                $tenant = Tenant::where('slug', $slug)->where('active', true)->first();
                $tenant?->users()->attach($user->id, ['role' => 'client']);
            }

            Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
            $user->assignRole('client');
        } else {
            $salonName = $request->string('salon_name')->toString() ?: $request->string('name')->toString();

            $tenant = Tenant::create([
                'name' => $salonName,
                'slug' => $this->generateUniqueSlug($salonName),
                'plan' => 'starter',
                'active' => true,
            ]);

            $tenant->users()->attach($user->id, ['role' => 'owner']);

            Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
            $user->assignRole('salon_owner');
        }

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

        $slug = $request->string('tenant_slug')->toString();
        $tenant = $slug
            ? $user->tenants()->where('slug', $slug)->first()
            : $user->tenants()->wherePivot('role', 'owner')->first() ?? $user->tenants()->first();

        if ($slug && ! $tenant) {
            return response()->json(['message' => 'Você não tem acesso a este salão.'], 403);
        }

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

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['message' => __($status)]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['message' => __($status)]);
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

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeamMemberRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\StaffInvited;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        abort_unless($request->user()->isStaffOfTenant($tenant), 403);

        $members = $tenant->users()
            ->wherePivotIn('role', ['owner', 'staff'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role,
            ]);

        return response()->json(['data' => $members]);
    }

    public function store(StoreTeamMemberRequest $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        $email = $request->string('email')->toString();
        $user = User::where('email', $email)->first();
        $isNewUser = $user === null;
        $membership = $user?->roleInTenant($tenant);

        if (in_array($membership, ['owner', 'staff'], true)) {
            throw ValidationException::withMessages([
                'email' => ['Este usuário já faz parte da equipe do negócio.'],
            ]);
        }

        if ($isNewUser) {
            $user = User::create([
                'name' => $request->string('name')->toString(),
                'email' => $email,
                'password' => Str::password(32),
            ]);
        }

        // Cliente do negócio convidado para a equipe é promovido a staff.
        if ($membership === 'client') {
            $tenant->users()->updateExistingPivot($user->id, ['role' => 'staff']);
        } else {
            $tenant->users()->attach($user->id, ['role' => 'staff']);
        }

        Role::firstOrCreate(['name' => 'salon_staff', 'guard_name' => 'web']);
        $user->assignRole('salon_staff');

        // Novo usuário define a própria senha pelo link de reset.
        $resetToken = $isNewUser ? Password::createToken($user) : null;
        $user->notify(new StaffInvited($tenant, $resetToken));

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'staff',
            ],
        ], 201);
    }

    public function destroy(Request $request, string $tenant, User $user): JsonResponse
    {
        /** @var Tenant $currentTenant */
        $currentTenant = app('currentTenant');

        abort_unless($request->user()->ownsTenant($currentTenant), 403);

        $membership = $user->roleInTenant($currentTenant);

        abort_if($membership === null, 404);

        if ($membership === 'owner') {
            throw ValidationException::withMessages([
                'user' => ['Não é possível remover o dono do negócio.'],
            ]);
        }

        $currentTenant->users()->detach($user->id);

        return response()->json(null, 204);
    }
}

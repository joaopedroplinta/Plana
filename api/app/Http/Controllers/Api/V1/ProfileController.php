<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Retorna os dados do próprio usuário autenticado (nome, e-mail, telefone).
     */
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load('tenants'));
    }

    /**
     * Atualiza nome, e-mail, telefone, data de nascimento e/ou observações
     * do próprio usuário autenticado.
     */
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();

        $user->fill($request->only(['name', 'email', 'phone', 'birth_date', 'notes']));
        $user->save();

        return new UserResource($user->load('tenants'));
    }

    /**
     * Atualiza a foto de perfil do próprio usuário autenticado.
     */
    public function uploadAvatar(Request $request): UserResource
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = $request->user();

        $path = $request->file('avatar')->store('avatars', 'public');

        $user->avatar_url = Storage::url($path);
        $user->save();

        return new UserResource($user->load('tenants'));
    }

    /**
     * Troca a senha do próprio usuário autenticado — exige a senha atual
     * (validada em UpdatePasswordRequest::withValidator via Hash::check).
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
        ])->save();

        return response()->json(['message' => 'Senha atualizada com sucesso.']);
    }
}

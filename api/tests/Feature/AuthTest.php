<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function registerUser(array $overrides = []): TestResponse
{
    $data = array_merge([
        'name' => 'Joao Teste',
        'email' => 'joao@teste.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ], $overrides);

    return test()->postJson('/api/v1/auth/register', $data);
}

it('register cria tenant, user e role salon_owner', function () {
    $response = registerUser();

    $response->assertCreated();

    $this->assertDatabaseHas('users', ['email' => 'joao@teste.com']);
    $this->assertDatabaseHas('tenants', ['slug' => 'joao-teste', 'plan' => 'starter']);

    $user = User::where('email', 'joao@teste.com')->first();
    expect($user->hasRole('salon_owner'))->toBeTrue();

    $this->assertDatabaseHas('tenant_user', [
        'user_id' => $user->id,
        'role' => 'owner',
    ]);
});

it('register retorna token, user e tenant', function () {
    $response = registerUser();

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'token',
                'user' => ['id', 'name', 'email', 'roles', 'tenant'],
                'tenant' => ['id', 'name', 'slug', 'plan', 'active'],
            ],
        ]);

    expect($response->json('data.token'))->not->toBeEmpty();
    expect($response->json('data.user.roles'))->toContain('salon_owner');
});

it('register falha com email duplicado', function () {
    User::factory()->create(['email' => 'joao@teste.com']);

    $response = registerUser(['email' => 'joao@teste.com']);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('login retorna token com credenciais válidas', function () {
    registerUser();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'joao@teste.com',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['token', 'user', 'tenant'],
        ]);

    expect($response->json('data.token'))->not->toBeEmpty();
});

it('login falha com credenciais inválidas', function () {
    registerUser();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'joao@teste.com',
        'password' => 'senha-errada',
    ]);

    $response->assertUnauthorized()
        ->assertJson(['message' => 'Credenciais inválidas.']);
});

it('logout revoga token', function () {
    $registerResponse = registerUser();
    $token = $registerResponse->json('data.token');

    $logoutResponse = $this->withToken($token)->postJson('/api/v1/auth/logout');
    $logoutResponse->assertNoContent();

    $this->assertDatabaseCount('personal_access_tokens', 0);

    Auth::forgetGuards();

    $meResponse = $this->withToken($token)->getJson('/api/v1/auth/me');
    $meResponse->assertUnauthorized();
});

it('me retorna user autenticado com tenant', function () {
    $registerResponse = registerUser();
    $token = $registerResponse->json('data.token');

    $response = $this->withToken($token)->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'email_verified_at',
                'roles',
                'tenant' => ['id', 'name', 'slug', 'plan', 'active'],
            ],
        ]);

    expect($response->json('data.email'))->toBe('joao@teste.com');
    expect($response->json('data.roles'))->toContain('salon_owner');
});

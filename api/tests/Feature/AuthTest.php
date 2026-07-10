<?php

use App\Models\Tenant;
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

it('login com tenant_slug resolve o tenant correto', function () {
    $registerResponse = registerUser();
    $slug = $registerResponse->json('data.tenant.slug');

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'joao@teste.com',
        'password' => 'password123',
        'tenant_slug' => $slug,
    ]);

    $response->assertOk();
    expect($response->json('data.tenant.slug'))->toBe($slug);
});

it('login com tenant_slug inválido retorna 422', function () {
    registerUser();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'joao@teste.com',
        'password' => 'password123',
        'tenant_slug' => 'slug-inexistente',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant_slug']);
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

// --- Registro de cliente ---

it('registra cliente sem criar tenant e vincula ao salao informado', function () {
    $tenant = Tenant::factory()->create();
    $before = Tenant::count();

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Cliente Final',
        'email' => 'cliente@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'account_type' => 'client',
        'tenant_slug' => $tenant->slug,
    ]);

    $response->assertCreated();
    expect(Tenant::count())->toBe($before);

    $user = User::where('email', 'cliente@example.com')->first();
    expect($user->hasRole('client'))->toBeTrue();

    $this->assertDatabaseHas('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => 'client',
    ]);
});

it('registra cliente sem tenant_slug sem criar salao', function () {
    $before = Tenant::count();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Cliente Solto',
        'email' => 'solto@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'account_type' => 'client',
    ])->assertCreated();

    expect(Tenant::count())->toBe($before);
});

it('me nao quebra para usuario sem nenhum tenant vinculado', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Cliente Solto',
        'email' => 'solto2@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'account_type' => 'client',
    ]);
    $token = $response->json('data.token');

    $meResponse = $this->withToken($token)->getJson('/api/v1/auth/me');

    $meResponse->assertOk()
        ->assertJsonPath('data.tenant', null);
});

it('registro de owner usa salon_name para o salao quando informado', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Maria Dona',
        'email' => 'maria@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'salon_name' => 'Studio Beleza Pura',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.tenant.name', 'Studio Beleza Pura');
});

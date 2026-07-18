<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// --- GET /api/v1/auth/profile ---

it('retorna 401 sem autenticação ao ver o perfil', function () {
    $this->getJson('/api/v1/auth/profile')->assertUnauthorized();
});

it('retorna os dados do próprio usuário autenticado', function () {
    $user = User::factory()->create(['name' => 'Joana Teste', 'phone' => '42999990000']);

    $response = $this->actingAs($user)->getJson('/api/v1/auth/profile');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'phone', 'email_verified_at', 'roles']])
        ->assertJsonPath('data.name', 'Joana Teste')
        ->assertJsonPath('data.phone', '42999990000');
});

// --- PATCH /api/v1/auth/profile ---

it('retorna 401 sem autenticação ao atualizar o perfil', function () {
    $this->patchJson('/api/v1/auth/profile', ['name' => 'Novo Nome'])->assertUnauthorized();
});

it('atualiza nome, email e telefone do próprio usuário', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patchJson('/api/v1/auth/profile', [
        'name' => 'Nome Atualizado',
        'email' => 'novo@example.com',
        'phone' => '42988887777',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Nome Atualizado')
        ->assertJsonPath('data.email', 'novo@example.com')
        ->assertJsonPath('data.phone', '42988887777');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Nome Atualizado',
        'email' => 'novo@example.com',
        'phone' => '42988887777',
    ]);
});

it('rejeita email duplicado ao atualizar o perfil', function () {
    User::factory()->create(['email' => 'ocupado@example.com']);
    $user = User::factory()->create(['email' => 'meu@example.com']);

    $response = $this->actingAs($user)->patchJson('/api/v1/auth/profile', [
        'email' => 'ocupado@example.com',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
});

it('permite manter o próprio email ao atualizar o perfil', function () {
    $user = User::factory()->create(['email' => 'meu@example.com']);

    $response = $this->actingAs($user)->patchJson('/api/v1/auth/profile', [
        'email' => 'meu@example.com',
        'name' => 'Mesmo Email Novo Nome',
    ]);

    $response->assertOk()->assertJsonPath('data.email', 'meu@example.com');
});

it('atualiza data de nascimento e observações e reflete no GET do perfil', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patchJson('/api/v1/auth/profile', [
        'birth_date' => '1995-04-20',
        'notes' => 'Alérgica a produtos com amônia.',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.birth_date', '1995-04-20')
        ->assertJsonPath('data.notes', 'Alérgica a produtos com amônia.');

    $show = $this->actingAs($user)->getJson('/api/v1/auth/profile');

    $show->assertOk()
        ->assertJsonPath('data.birth_date', '1995-04-20')
        ->assertJsonPath('data.notes', 'Alérgica a produtos com amônia.');
});

// --- PUT /api/v1/auth/profile/password ---

it('retorna 401 sem autenticação ao trocar a senha', function () {
    $this->putJson('/api/v1/auth/profile/password', [
        'current_password' => 'password',
        'password' => 'nova-senha-123',
        'password_confirmation' => 'nova-senha-123',
    ])->assertUnauthorized();
});

it('troca a senha quando a senha atual está correta', function () {
    $user = User::factory()->create(); // senha padrão da factory: "password"

    $response = $this->actingAs($user)->putJson('/api/v1/auth/profile/password', [
        'current_password' => 'password',
        'password' => 'nova-senha-123',
        'password_confirmation' => 'nova-senha-123',
    ]);

    $response->assertOk()->assertJson(['message' => 'Senha atualizada com sucesso.']);

    $user->refresh();
    expect(Hash::check('nova-senha-123', $user->password))->toBeTrue();
});

it('rejeita a troca de senha quando a senha atual está errada', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson('/api/v1/auth/profile/password', [
        'current_password' => 'senha-errada',
        'password' => 'nova-senha-123',
        'password_confirmation' => 'nova-senha-123',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['current_password']);

    $user->refresh();
    expect(Hash::check('password', $user->password))->toBeTrue();
});

it('rejeita a troca de senha sem confirmação correta', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson('/api/v1/auth/profile/password', [
        'current_password' => 'password',
        'password' => 'nova-senha-123',
        'password_confirmation' => 'outra-coisa',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['password']);
});

// --- POST /api/v1/auth/profile/avatar ---

it('retorna 401 sem autenticação ao enviar avatar', function () {
    Storage::fake('public');

    $this->postJson('/api/v1/auth/profile/avatar', [
        'avatar' => UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg'),
    ])->assertUnauthorized();
});

it('faz upload do avatar e recebe a url pública', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/auth/profile/avatar', [
        'avatar' => UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg'),
    ]);

    $response->assertOk();

    $avatarUrl = $response->json('data.avatar_url');
    expect($avatarUrl)->toStartWith('/storage/');
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $avatarUrl));

    $user->refresh();
    expect($user->avatar_url)->toBe($avatarUrl);
});

it('rejeita upload de avatar que não é imagem', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/auth/profile/avatar', [
        'avatar' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['avatar']);
});

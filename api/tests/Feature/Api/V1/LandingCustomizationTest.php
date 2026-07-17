<?php

use App\Models\SalonGalleryImage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function landingOwner(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_owner');
    $tenant->users()->attach($user->id, ['role' => 'owner']);

    return $user;
}

function landingStaff(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_staff', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_staff');
    $tenant->users()->attach($user->id, ['role' => 'staff']);

    return $user;
}

// --- Branding (cor + logo) ---

it('owner salva a cor da marca via settings', function () {
    $tenant = Tenant::factory()->create();
    $owner = landingOwner($tenant);

    $this->actingAs($owner)->patchJson("/api/v1/negocio/{$tenant->slug}/settings", [
        'brand_color' => '#1e88e5',
    ])->assertOk()->assertJsonPath('data.brand_color', '#1e88e5');
});

it('rejeita cor da marca em formato inválido', function () {
    $tenant = Tenant::factory()->create();
    $owner = landingOwner($tenant);

    $this->actingAs($owner)->patchJson("/api/v1/negocio/{$tenant->slug}/settings", [
        'brand_color' => 'azul',
    ])->assertStatus(422)->assertJsonValidationErrors(['brand_color']);
});

it('owner faz upload da logo e recebe a url pública', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create();
    $owner = landingOwner($tenant);

    $response = $this->actingAs($owner)->postJson("/api/v1/negocio/{$tenant->slug}/logo", [
        'logo' => UploadedFile::fake()->create('logo.png', 100, 'image/png'),
    ]);

    $response->assertOk();
    $logoUrl = $response->json('data.logo_url');
    expect($logoUrl)->toStartWith('/storage/logos/');
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $logoUrl));
});

it('staff não pode fazer upload da logo', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create();
    $staff = landingStaff($tenant);

    $this->actingAs($staff)->postJson("/api/v1/negocio/{$tenant->slug}/logo", [
        'logo' => UploadedFile::fake()->create('logo.png', 100, 'image/png'),
    ])->assertStatus(403);
});

// --- Galeria ---

it('endpoint público lista a galeria em ordem', function () {
    $tenant = Tenant::factory()->create();
    SalonGalleryImage::create(['tenant_id' => $tenant->id, 'image_url' => '/storage/gallery/b.jpg', 'sort_order' => 2]);
    SalonGalleryImage::create(['tenant_id' => $tenant->id, 'image_url' => '/storage/gallery/a.jpg', 'sort_order' => 1]);

    $this->getJson("/api/v1/negocio/{$tenant->slug}/gallery")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.image_url', '/storage/gallery/a.jpg');
});

it('owner adiciona imagem à galeria', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create();
    $owner = landingOwner($tenant);

    $response = $this->actingAs($owner)->postJson("/api/v1/negocio/{$tenant->slug}/gallery", [
        'image' => UploadedFile::fake()->create('atendimento.jpg', 100, 'image/jpeg'),
        'caption' => 'Corte degradê',
    ]);

    $response->assertStatus(201)->assertJsonPath('data.caption', 'Corte degradê');
    $this->assertDatabaseHas('salon_gallery_images', [
        'tenant_id' => $tenant->id, 'caption' => 'Corte degradê',
    ]);
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $response->json('data.image_url')));
});

it('staff não pode adicionar imagem à galeria', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create();
    $staff = landingStaff($tenant);

    $this->actingAs($staff)->postJson("/api/v1/negocio/{$tenant->slug}/gallery", [
        'image' => UploadedFile::fake()->create('x.jpg', 100, 'image/jpeg'),
    ])->assertStatus(403);
});

it('owner remove imagem e apaga o arquivo do disco', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create();
    $owner = landingOwner($tenant);

    // Cria uma imagem real no disco fake e a linha no banco.
    $path = UploadedFile::fake()->create('x.jpg', 100, 'image/jpeg')->store('gallery', 'public');
    $image = SalonGalleryImage::create([
        'tenant_id' => $tenant->id,
        'image_url' => Storage::url($path),
    ]);
    Storage::disk('public')->assertExists($path);

    $this->actingAs($owner)->deleteJson("/api/v1/negocio/{$tenant->slug}/gallery/{$image->id}")
        ->assertStatus(204);

    $this->assertDatabaseMissing('salon_gallery_images', ['id' => $image->id]);
    Storage::disk('public')->assertMissing($path);
});

it('não vaza galeria entre tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    SalonGalleryImage::create(['tenant_id' => $tenantA->id, 'image_url' => '/storage/gallery/a.jpg']);
    SalonGalleryImage::create(['tenant_id' => $tenantB->id, 'image_url' => '/storage/gallery/b.jpg']);

    $this->getJson("/api/v1/negocio/{$tenantA->slug}/gallery")
        ->assertOk()->assertJsonCount(1, 'data');
});

it('owner de um tenant não remove imagem de outro', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $ownerA = landingOwner($tenantA);
    $imageB = SalonGalleryImage::create(['tenant_id' => $tenantB->id, 'image_url' => '/storage/gallery/b.jpg']);

    // A imagem de B nem é encontrada no escopo de A → 404.
    $this->actingAs($ownerA)->deleteJson("/api/v1/negocio/{$tenantA->slug}/gallery/{$imageB->id}")
        ->assertStatus(404);
});

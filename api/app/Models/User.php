<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * All tenants this user belongs to.
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Check if this user belongs to the given tenant (via pivot).
     */
    public function belongsToTenant(Tenant $tenant): bool
    {
        return $this->tenants()->where('tenant_id', $tenant->id)->exists();
    }

    /**
     * The user's role inside a specific tenant (owner|staff|client), from the pivot.
     * Roles are per-tenant: a user can own salon A and be a client at salon B.
     */
    public function roleInTenant(Tenant $tenant): ?string
    {
        /** @var Tenant|null $membership */
        $membership = $this->tenants()->where('tenant_id', $tenant->id)->first();

        return $membership?->pivot->role;
    }

    /**
     * Whether the user is owner or staff of the given tenant.
     */
    public function isStaffOfTenant(Tenant $tenant): bool
    {
        return in_array($this->roleInTenant($tenant), ['owner', 'staff'], true);
    }

    /**
     * Whether the user is the owner of the given tenant.
     */
    public function ownsTenant(Tenant $tenant): bool
    {
        return $this->roleInTenant($tenant) === 'owner';
    }
}

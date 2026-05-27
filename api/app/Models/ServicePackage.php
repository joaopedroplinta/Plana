<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Database\Factories\ServicePackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ServicePackage extends Model
{
    /** @use HasFactory<ServicePackageFactory> */
    use BelongsToTenant, HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'price',
        'sessions',
        'valid_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'sessions' => 'integer',
            'valid_days' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ServicePackage $package) {
            if (empty($package->id)) {
                $package->id = (string) Str::uuid();
            }
        });
    }

    /** @return HasMany<PackageService, $this> */
    public function packageServices(): HasMany
    {
        return $this->hasMany(PackageService::class, 'package_id');
    }

    /** @return BelongsToMany<Service, $this> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'package_services', 'package_id', 'service_id')
            ->withPivot('quantity');
    }
}

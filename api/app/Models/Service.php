<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use BelongsToTenant, HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'price',
        'deposit_type',
        'deposit_value',
        'duration_minutes',
        'image_url',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'deposit_value' => 'integer',
            'duration_minutes' => 'integer',
            'active' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Service $service) {
            if (empty($service->id)) {
                $service->id = (string) Str::uuid();
            }
        });
    }
}

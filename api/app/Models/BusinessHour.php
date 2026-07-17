<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BusinessHour extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'day_of_week',
        'is_open',
        'open_time',
        'close_time',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_open' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (BusinessHour $businessHour) {
            if (empty($businessHour->id)) {
                $businessHour->id = (string) Str::uuid();
            }
        });
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Database\Factories\BlockedDateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BlockedDate extends Model
{
    /** @use HasFactory<BlockedDateFactory> */
    use BelongsToTenant, HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'professional_id',
        'date',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (BlockedDate $blockedDate) {
            if (empty($blockedDate->id)) {
                $blockedDate->id = (string) Str::uuid();
            }
        });
    }

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}

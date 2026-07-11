<?php

namespace App\Models;

use App\Enums\PackagePurchaseStatus;
use App\Traits\BelongsToTenant;
use Database\Factories\PackagePurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PackagePurchase extends Model
{
    /** @use HasFactory<PackagePurchaseFactory> */
    use BelongsToTenant, HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'client_id',
        'service_package_id',
        'sessions_total',
        'sessions_used',
        'price_paid',
        'status',
        'purchased_at',
        'expires_at',
        'payment_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sessions_total' => 'integer',
            'sessions_used' => 'integer',
            'price_paid' => 'integer',
            'status' => PackagePurchaseStatus::class,
            'purchased_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (PackagePurchase $purchase) {
            if (empty($purchase->id)) {
                $purchase->id = (string) Str::uuid();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<ServicePackage, $this> */
    public function servicePackage(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function sessionsRemaining(): int
    {
        return max(0, $this->sessions_total - $this->sessions_used);
    }

    public function isUsable(): bool
    {
        return $this->status === PackagePurchaseStatus::Active
            && $this->expires_at !== null
            && $this->expires_at->isFuture()
            && $this->sessions_used < $this->sessions_total;
    }
}

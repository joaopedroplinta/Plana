<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Traits\BelongsToTenant;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use BelongsToTenant, HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'client_id',
        'professional_id',
        'service_id',
        'package_purchase_id',
        'starts_at',
        'ends_at',
        'status',
        'price',
        'deposit_amount',
        'notes',
        'reminder_sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'price' => 'integer',
            'deposit_amount' => 'integer',
            'reminder_sent_at' => 'datetime',
            'status' => AppointmentStatus::class,
        ];
    }

    /**
     * Valor cobrado online na reserva: o sinal, se houver; senão o preço
     * cheio. É este o valor que vai para o MercadoPago e sobre o qual incide
     * a comissão da plataforma.
     */
    public function chargeableAmount(): int
    {
        return $this->deposit_amount ?? $this->price;
    }

    /**
     * Saldo a pagar presencialmente no salão (total menos o que já foi cobrado
     * online). Zero quando não há sinal.
     */
    public function balanceDue(): int
    {
        return $this->price - $this->chargeableAmount();
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Appointment $appointment) {
            if (empty($appointment->id)) {
                $appointment->id = (string) Str::uuid();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return BelongsTo<PackagePurchase, $this> */
    public function packagePurchase(): BelongsTo
    {
        return $this->belongsTo(PackagePurchase::class);
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToTenant, HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'appointment_id',
        'amount',
        'platform_fee',
        'method',
        'external_id',
        'preference_id',
        'status',
        'pix_qr_code',
        'pix_qr_code_base64',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'platform_fee' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Payment $payment) {
            if (empty($payment->id)) {
                $payment->id = (string) Str::uuid();
            }
        });
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Compra de pacote paga por este pagamento — inverso de
     * PackagePurchase::payment() (FK vive em package_purchases.payment_id).
     *
     * @return HasOne<PackagePurchase, $this>
     */
    public function packagePurchase(): HasOne
    {
        return $this->hasOne(PackagePurchase::class, 'payment_id');
    }
}

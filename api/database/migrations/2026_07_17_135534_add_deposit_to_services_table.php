<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sinal / valor de reserva por serviço (override do padrão do salão).
     *
     * - `deposit_type`: NULL = herda o padrão do salão (tenants.settings);
     *   'none' = este serviço não cobra sinal (cobra o valor cheio);
     *   'fixed' = valor fixo em centavos; 'percentage' = % do preço.
     * - `deposit_value`: centavos (fixed) ou percentual inteiro 1..100
     *   (percentage). Irrelevante para NULL/'none'.
     *
     * A resolução final (herança salão→serviço e cálculo em centavos) vive em
     * App\Services\DepositCalculator.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('deposit_type')->nullable()->after('price');
            $table->integer('deposit_value')->nullable()->after('deposit_type');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['deposit_type', 'deposit_value']);
        });
    }
};

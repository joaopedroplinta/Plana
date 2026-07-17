<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Valor efetivamente cobrado online na reserva (o "sinal"), congelado no
     * momento do agendamento — em centavos. NULL = cobrança do valor cheio
     * (`price`), comportamento padrão de quem não usa sinal. O saldo a pagar
     * presencialmente é `price - deposit_amount`.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->integer('deposit_amount')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('deposit_amount');
        });
    }
};

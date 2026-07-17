<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 2 do marketplace: comissão da plataforma (marketplace_fee) retida
     * em cada agendamento pago na conta MercadoPago conectada do salão.
     * Guardamos o valor efetivamente cobrado (em centavos) no próprio
     * pagamento para relatório/auditoria — `null` quando não houve comissão
     * (salão sem conta conectada, ou pagamento que não é de agendamento).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->integer('platform_fee')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('platform_fee');
        });
    }
};

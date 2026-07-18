<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // 'monthly' | 'yearly'. `expires_at` (já existente) já guarda a
            // data até quando o período pago vale — reaproveitado como
            // "paid_until" tanto pro ciclo mensal quanto pro anual, então
            // subscriptions:downgrade-expired funciona pros dois sem mudança.
            $table->string('billing_cycle')->default('monthly')->after('plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('billing_cycle');
        });
    }
};

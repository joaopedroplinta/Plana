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
        Schema::table('appointments', function (Blueprint $table) {
            // nullOnDelete (como client_id): apagar a compra do pacote não
            // deve apagar o histórico do agendamento, só desvincular a sessão.
            $table->foreignUuid('package_purchase_id')
                ->nullable()
                ->after('service_id')
                ->constrained('package_purchases')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_purchase_id');
        });
    }
};

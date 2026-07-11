<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pagamentos de compra de pacote não têm agendamento associado —
     * relaxamos a coluna para permitir o reaproveitamento do model Payment.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE payments ALTER COLUMN appointment_id DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE payments ALTER COLUMN appointment_id SET NOT NULL');
    }
};

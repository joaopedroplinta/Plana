<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Horário de funcionamento do estabelecimento (salão), por dia da semana.
     *
     * `is_open=false` = fechado naquele dia (open/close ignorados). A AUSÊNCIA
     * de qualquer linha para o tenant significa "nunca configurado" e, por
     * retrocompatibilidade, NÃO restringe a agenda — só passa a limitar depois
     * que o dono salva os horários. Ver SchedulingService::salonWindow().
     */
    public function up(): void
    {
        Schema::create('business_hours', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=domingo .. 6=sábado (Carbon::dayOfWeek)
            $table->boolean('is_open')->default(true);
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'day_of_week']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_hours');
    }
};

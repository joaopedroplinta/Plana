<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 1 do marketplace: cada tenant conecta a PRÓPRIA conta MercadoPago
     * via OAuth. Optamos por colunas na tabela `tenants` (e não uma tabela
     * dedicada) porque a relação é estritamente 1:1 — cada salão tem no
     * máximo uma conta MercadoPago conectada — e o restante da configuração
     * do salão (settings, plan) já vive no próprio tenant. Uma tabela à parte
     * só se justificaria com histórico de múltiplas contas/reconexões, o que
     * não faz parte desta fase.
     *
     * Os tokens são persistidos em colunas `text` e criptografados na camada
     * do Model via cast `encrypted` (nunca em texto puro no banco, nunca em
     * API Resource nem em log).
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('mp_access_token')->nullable();
            $table->text('mp_refresh_token')->nullable();
            $table->string('mp_user_id')->nullable();
            $table->string('mp_public_key')->nullable();
            $table->timestampTz('mp_token_expires_at')->nullable();
            $table->timestampTz('mp_connected_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'mp_access_token',
                'mp_refresh_token',
                'mp_user_id',
                'mp_public_key',
                'mp_token_expires_at',
                'mp_connected_at',
            ]);
        });
    }
};

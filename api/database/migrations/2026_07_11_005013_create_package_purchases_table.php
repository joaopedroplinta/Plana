<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('package_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            // restrictOnDelete: um pacote com compras não pode ser apagado —
            // o admin do salão precisa desativá-lo em vez disso.
            $table->foreignUuid('service_package_id')->constrained('service_packages')->restrictOnDelete();
            $table->unsignedTinyInteger('sessions_total');
            $table->unsignedTinyInteger('sessions_used')->default(0);
            $table->integer('price_paid');
            $table->string('status')->default('pending');
            $table->timestampTz('purchased_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->foreignUuid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'client_id']);
            $table->index(['tenant_id', 'status']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE package_purchases
            ADD CONSTRAINT package_purchases_status_check
            CHECK (status IN ('pending', 'active', 'expired', 'cancelled'))
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_purchases');
    }
};

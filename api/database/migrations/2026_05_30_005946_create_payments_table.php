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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->integer('amount');
            $table->string('method');
            $table->string('external_id')->nullable();
            $table->string('preference_id')->nullable();
            $table->string('status')->default('pending');
            $table->text('pix_qr_code')->nullable();
            $table->text('pix_qr_code_base64')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'appointment_id']);
            $table->index(['tenant_id', 'status']);
            $table->index('external_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

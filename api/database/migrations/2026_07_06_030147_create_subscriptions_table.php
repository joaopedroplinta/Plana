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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('plan');
            $table->integer('amount');
            $table->string('method');
            $table->string('status')->default('pending');
            $table->string('mp_payment_id')->nullable();
            $table->string('mp_preference_id')->nullable();
            $table->text('pix_qr_code')->nullable();
            $table->text('pix_qr_code_base64')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

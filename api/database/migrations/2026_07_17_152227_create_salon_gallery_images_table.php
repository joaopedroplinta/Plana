<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Galeria de imagens dos atendimentos do salão, exibida na landing pública.
     * `image_url` guarda a URL pública do arquivo (Storage::url), no mesmo
     * padrão da imagem de serviço. `sort_order` controla a ordem de exibição.
     */
    public function up(): void
    {
        Schema::create('salon_gallery_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('image_url');
            $table->string('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salon_gallery_images');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('reward_categories')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('short_description')->nullable();
            $table->longText('body_html')->nullable();
            // price_cop es la única fuente de verdad del precio; el precio en puntos siempre se deriva
            // de la tasa de cambio vigente (settings.cop_per_point), nunca se duplica aquí.
            $table->unsignedBigInteger('price_cop');
            $table->unsignedInteger('stock')->nullable(); // null = ilimitado
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};

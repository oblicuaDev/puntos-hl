<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_batch_id')->constrained('point_batches')->cascadeOnDelete();
            $table->foreignId('redemption_id')->constrained('redemptions')->cascadeOnDelete();
            $table->unsignedInteger('points_consumed');
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_consumptions');
    }
};

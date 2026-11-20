<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('points_original');
            $table->unsignedInteger('points_remaining');
            $table->timestamp('earned_at');
            $table->timestamp('expires_at');
            $table->nullableMorphs('sourceable'); // ej. Sale, futuros ajustes/promos
            $table->enum('status', ['active', 'expired', 'exhausted'])->default('active');
            $table->timestamps();

            $table->index(['patient_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_batches');
    }
};

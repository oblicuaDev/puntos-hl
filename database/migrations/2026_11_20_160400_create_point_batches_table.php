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
            // dateTime en vez de timestamp: dos columnas TIMESTAMP NOT NULL sin
            // default en la misma tabla disparan el error 1067 de MySQL
            // ("Invalid default value") bajo explicit_defaults_for_timestamp=OFF
            // (config heredada común en hosting compartido) — DATETIME no tiene
            // ese comportamiento implícito y evita el problema de raíz.
            $table->dateTime('earned_at');
            $table->dateTime('expires_at');
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

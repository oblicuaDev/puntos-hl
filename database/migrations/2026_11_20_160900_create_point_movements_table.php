<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['earned', 'redeemed', 'expired', 'adjustment_credit', 'adjustment_debit']);
            $table->integer('points'); // con signo: positivo gana, negativo gasta/vence
            $table->nullableMorphs('related'); // Sale, Redemption, PointBatch, etc.
            $table->string('description')->nullable();
            $table->dateTime('occurred_at'); // ver nota en create_point_batches_table.php
            $table->timestamps();

            $table->index(['patient_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_movements');
    }
};

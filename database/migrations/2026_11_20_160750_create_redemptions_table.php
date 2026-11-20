<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redemptions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reward_id')->constrained('rewards');
            // snapshots: el detalle de la redención no debe cambiar si el premio se edita después
            $table->string('reward_title_snapshot');
            $table->unsignedBigInteger('reward_price_cop_snapshot');
            $table->unsignedInteger('points_used');
            $table->unsignedBigInteger('money_amount_cop')->default(0);
            $table->decimal('points_percentage_applied', 5, 2);
            $table->enum('payment_method', ['points_only', 'mixed']);
            $table->enum('status', ['pending_payment', 'approved', 'rejected', 'cancelled', 'fulfilled'])->default('pending_payment');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redemptions');
    }
};

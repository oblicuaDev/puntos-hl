<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('redemption_id')->constrained('redemptions')->cascadeOnDelete();
            $table->enum('provider', ['none', 'stub', 'payzen'])->default('none');
            $table->string('provider_reference')->nullable();
            $table->unsignedBigInteger('amount_cop');
            $table->enum('status', ['pending', 'approved', 'declined', 'error'])->default('pending');
            $table->json('raw_response')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

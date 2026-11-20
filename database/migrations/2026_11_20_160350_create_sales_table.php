<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained('admin_users');
            $table->foreignId('procedure_id')->nullable()->constrained('procedures')->nullOnDelete();
            $table->unsignedBigInteger('amount_cop');
            $table->decimal('exchange_rate_applied', 12, 4);
            $table->unsignedInteger('points_awarded');
            $table->date('sale_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};

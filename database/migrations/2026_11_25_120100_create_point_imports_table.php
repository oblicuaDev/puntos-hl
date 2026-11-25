<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trazabilidad de cargas masivas de saldos iniciales: cada lote de puntos y
 * movimiento creado por una carga apunta (morph) a su registro aquí, con el
 * archivo fuente (hash), la tasa aplicada y las fechas de causación/vencimiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source_filename');
            $table->string('source_sha256', 64);
            $table->decimal('accrual_cop_per_point', 12, 4);
            $table->dateTime('earned_at');
            $table->dateTime('expires_at');
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_rejected')->default(0);
            $table->unsignedInteger('patients_created')->default(0);
            $table->unsignedBigInteger('points_total')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_imports');
    }
};

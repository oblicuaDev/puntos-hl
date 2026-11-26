<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada corrida de la carga diaria de facturación desde Google Sheets (HL-28):
 * cuántas filas leyó, procesó y dejó pendientes, y el detalle de las
 * novedades reportadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('spreadsheet_id');
            $table->date('cutoff_date');
            $table->decimal('accrual_cop_per_point', 12, 4);
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->unsignedInteger('rows_read')->default(0);
            $table->unsignedInteger('rows_processed')->default(0);
            $table->unsignedInteger('rows_pending')->default(0);
            $table->unsignedInteger('patients_created')->default(0);
            $table->unsignedBigInteger('points_total')->default(0);
            $table->json('pending_rows')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_sync_runs');
    }
};

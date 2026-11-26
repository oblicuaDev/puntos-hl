<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una fila de la hoja de facturación ya acreditada. El lote y el movimiento
 * de puntos apuntan (morph) a este registro, y este a la hoja y su fila:
 * trazabilidad fila → paciente → movimiento.
 *
 * `fingerprint` (hoja + fila + documento + valor + fecha) es único: si la X de
 * la columna REGISTRADO no se alcanzó a escribir, la siguiente corrida
 * reconoce la fila y solo la marca, sin acreditarla otra vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_sync_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('spreadsheet_id');
            $table->unsignedInteger('sheet_row');
            $table->string('fingerprint', 64)->unique();
            $table->string('document_number');
            $table->string('patient_name');
            $table->date('invoice_date');
            $table->unsignedBigInteger('amount_cop');
            $table->string('sheet_points')->nullable();
            $table->unsignedInteger('points');
            $table->decimal('accrual_cop_per_point', 12, 4);
            $table->boolean('patient_created')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_records');
    }
};

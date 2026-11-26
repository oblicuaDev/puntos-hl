<?php

namespace App\Services\BillingSheet;

/**
 * La hoja de Google Sheets donde Katherine registra a diario la facturación
 * de los pacientes (HL-28).
 */
interface BillingSheet
{
    /** Identificador del documento (para trazabilidad). */
    public function id(): string;

    /**
     * Todas las filas con datos, incluido el encabezado, indexadas por su
     * número de fila en la hoja (1 = encabezado). Las fechas llegan como
     * número de serie de Sheets y los números sin formato.
     *
     * @return array<int, array<int, mixed>>
     *
     * @throws BillingSheetException
     */
    public function rows(): array;

    /**
     * Escribe valores en celdas sueltas, en una sola llamada.
     *
     * @param  array<string, string>  $cells  celda en notación A1 ("M5") => valor
     *
     * @throws BillingSheetException
     */
    public function write(array $cells): void;
}

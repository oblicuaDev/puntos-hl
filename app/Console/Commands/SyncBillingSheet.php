<?php

namespace App\Console\Commands;

use App\Services\BillingSheet\BillingSheetException;
use App\Services\Points\BillingSheetSync;
use Illuminate\Console\Command;

/**
 * Carga diaria de la facturación registrada en Google Sheets (HL-28). Ver
 * App\Services\Points\BillingSheetSync. Con --dry-run solo muestra qué haría.
 */
class SyncBillingSheet extends Command
{
    protected $signature = 'points:sync-billing-sheet
        {--dry-run : Solo revisa la hoja y muestra qué se haría, sin escribir nada}';

    protected $description = 'Acredita los puntos de la facturación registrada en la hoja de Google Sheets.';

    private const ACTION_LABELS = [
        'crear' => 'Crear paciente y acreditar',
        'acreditar' => 'Acreditar',
        'marcar' => 'Ya acreditada, solo marcar X',
        'pendiente' => 'Pendiente',
    ];

    public function handle(BillingSheetSync $sync): int
    {
        try {
            if ($this->option('dry-run')) {
                return $this->preview($sync);
            }

            $run = $sync->sync();
        } catch (BillingSheetException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Resultado', 'Valor'], [
            ['Filas revisadas', $run->rows_read],
            ['Acreditadas', $run->rows_processed],
            ['Pacientes creados', $run->patients_created],
            ['Pendientes', $run->rows_pending],
            ['Puntos acreditados', number_format($run->points_total, 0, ',', '.')],
        ]);

        if ($run->error) {
            $this->error($run->error);

            return self::FAILURE;
        }

        $this->info("Corrida #{$run->id} terminada.");

        return self::SUCCESS;
    }

    private function preview(BillingSheetSync $sync): int
    {
        $results = $sync->preview();

        $this->table(
            ['Fila', 'Documento', 'Nombre', 'Valor', 'Puntos', 'Acción', 'Novedad'],
            array_map(fn ($r) => [
                $r['row'], $r['document'], $r['name'],
                $r['amount'] !== null ? number_format($r['amount'], 0, ',', '.') : '',
                $r['points'], self::ACTION_LABELS[$r['action']], $r['message'],
            ], $results)
        );

        $this->warn('Simulación: no se escribió nada en la plataforma ni en la hoja.');

        return self::SUCCESS;
    }
}

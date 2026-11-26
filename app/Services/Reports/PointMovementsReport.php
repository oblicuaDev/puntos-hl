<?php

namespace App\Services\Reports;

use App\Models\BillingRecord;
use App\Models\PointBatch;
use App\Models\PointImport;
use App\Models\PointMovement;
use App\Models\Redemption;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Reporte Excel de movimientos de puntos (HL-26) para conciliación: incluye
 * acumulaciones, redenciones, vencimientos y ajustes dentro de un rango de
 * fechas, con el saldo del paciente después de cada movimiento.
 */
class PointMovementsReport
{
    public const TYPE_LABELS = [
        'earned' => 'Acumulación',
        'redeemed' => 'Redención',
        'expired' => 'Vencimiento',
        'adjustment_credit' => 'Ajuste a favor',
        'adjustment_debit' => 'Ajuste en contra',
    ];

    private const HEADERS = [
        'Fecha y hora', 'Paciente', 'Documento', 'Teléfono', 'Tipo de movimiento',
        'Puntos', 'Saldo resultante', 'Valor en dinero (COP)', 'Pagado en dinero (COP)', 'Procedimiento / concepto',
    ];

    public function __construct(private Carbon $from, private Carbon $to) {}

    public function filename(): string
    {
        return 'movimientos-puntos_'.$this->from->format('Y-m-d').'_a_'.$this->to->format('Y-m-d').'.xlsx';
    }

    /** Escribe el XLSX en la ruta/stream dados (p. ej. php://output). */
    public function writeTo(string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        $header = (new Style)->withFontBold(true)->withFontColor('FFFFFF')->withBackgroundColor('335C82');
        $writer->addRow(Row::fromValuesWithStyle(self::HEADERS, $header));

        $dateStyle = (new Style)->withFormat('dd/mm/yyyy hh:mm');
        $moneyStyle = (new Style)->withFormat('#,##0');
        $styles = [0 => $dateStyle, 5 => $moneyStyle, 6 => $moneyStyle, 7 => $moneyStyle, 8 => $moneyStyle];

        // Saldo de cada paciente antes del rango, para calcular el saldo resultante.
        $balances = PointMovement::where('occurred_at', '<', $this->from)
            ->groupBy('patient_id')
            ->selectRaw('patient_id, SUM(points) as total')
            ->pluck('total', 'patient_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        PointMovement::query()
            ->whereBetween('occurred_at', [$this->from, $this->to])
            ->orderBy('occurred_at')->orderBy('id')
            ->with(['patient' => fn ($q) => $q->withTrashed()])
            ->chunk(500, function ($movements) use ($writer, $styles, &$balances) {
                $movements->loadMorph('related', [
                    Sale::class => ['procedure'],
                    Redemption::class => [],
                    PointImport::class => [],
                    PointBatch::class => [],
                    BillingRecord::class => [],
                ]);

                foreach ($movements as $movement) {
                    $balances[$movement->patient_id] = ($balances[$movement->patient_id] ?? 0) + $movement->points;
                    [$value, $paid, $concept] = $this->moneyAndConcept($movement);

                    $writer->addRow(Row::fromValuesWithStyles([
                        $movement->occurred_at->toDateTimeImmutable(),
                        $movement->patient?->fullName() ?? '(paciente eliminado)',
                        (string) $movement->patient?->document_number,
                        (string) $movement->patient?->phone,
                        self::TYPE_LABELS[$movement->type] ?? $movement->type,
                        (int) $movement->points,
                        $balances[$movement->patient_id],
                        $value,
                        $paid,
                        $concept,
                    ], $styles));
                }
            });

        $writer->close();
    }

    /** @return array{0: ?int, 1: ?int, 2: string} valor asociado, pagado en dinero y concepto */
    private function moneyAndConcept(PointMovement $movement): array
    {
        $related = $movement->related;

        return match (true) {
            $related instanceof Sale => [
                (int) $related->amount_cop,
                null,
                $related->procedure?->name ? 'Compra: '.$related->procedure->name : 'Compra registrada',
            ],
            $related instanceof BillingRecord => [
                (int) $related->amount_cop,
                null,
                'Facturación del '.$related->invoice_date->format('d/m/Y').' (hoja de facturación, fila '.$related->sheet_row.')',
            ],
            $related instanceof Redemption => [
                (int) $related->reward_price_cop_snapshot,
                $movement->type === 'redeemed' ? (int) $related->money_amount_cop : null,
                $related->reward_title_snapshot.' (canje '.$related->code.')',
            ],
            default => [null, null, (string) $movement->description],
        };
    }
}

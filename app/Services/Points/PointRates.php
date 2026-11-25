<?php

namespace App\Services\Points;

/**
 * Las dos tasas de conversión del plan, independientes entre sí y
 * configurables desde la configuración general (superadmin):
 *
 * - Acumulación: cuántos COP efectivamente pagados equivalen a 1 punto
 *   (`accrual_cop_per_point`, p. ej. 1.000 → 1 punto por cada $1.000).
 * - Redención: cuántos puntos cuesta cada $100 COP del valor de un
 *   procedimiento (`redemption_points_per_100_cop`, p. ej. 3).
 *
 * Ninguna de las dos se aplica retroactivamente: las ventas guardan
 * points_awarded y la tasa aplicada, y los canjes guardan points_used,
 * money_amount_cop y la tasa de redención aplicada al momento de hacerse.
 */
class PointRates
{
    public static function accrualCopPerPoint(): float
    {
        return max((float) setting('accrual_cop_per_point', 1000), 0.01);
    }

    public static function redemptionPointsPer100Cop(): float
    {
        return max((float) setting('redemption_points_per_100_cop', 3), 0.0001);
    }

    /** Puntos ganados por un pago: 1 punto por cada bloque COMPLETO de la tasa de acumulación. */
    public static function pointsEarnedFor(int $amountCop, ?float $copPerPoint = null): int
    {
        $rate = $copPerPoint ?? self::accrualCopPerPoint();

        return (int) floor(round($amountCop / $rate, 6));
    }

    /** Puntos necesarios para cubrir el 100% de un precio en COP (se redondea hacia arriba). */
    public static function pointsNeededFor(int $priceCop): int
    {
        return (int) ceil(round($priceCop * self::redemptionPointsPer100Cop() / 100, 6));
    }

    /** Valor en COP de 1 punto al momento de redimir (p. ej. 3 pts/$100 → $33,33). */
    public static function copValuePerRedeemedPoint(): float
    {
        return 100 / self::redemptionPointsPer100Cop();
    }
}

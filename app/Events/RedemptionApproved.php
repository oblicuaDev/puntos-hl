<?php

namespace App\Events;

use App\Models\Redemption;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara tanto para canjes 100% puntos (aprobados al instante) como
 * para canjes mixtos con pago aprobado (real o del stub) — una sola ruta
 * de mailing para ambos casos.
 */
class RedemptionApproved
{
    use Dispatchable;

    public function __construct(public Redemption $redemption) {}
}

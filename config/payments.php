<?php

return [
    // 'stub' mientras no haya credenciales reales de Payzen; luego "payzen".
    'driver' => env('PAYMENTS_DRIVER', 'stub'),
];

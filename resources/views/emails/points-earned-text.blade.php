¡Acumulaste {{ number_format($points, 0, ',', '.') }} puntos!

Hola {{ $patient->first_name }}, sumamos a tu cuenta los puntos de tu compra más reciente en Harker Lloreda.

Puntos acumulados: {{ number_format($points, 0, ',', '.') }}
Tu nuevo saldo: {{ number_format($balance, 0, ',', '.') }} puntos

Estos puntos tienen una vigencia de {{ (int) setting('points_expiration_months', 12) }} meses. Puedes consultarlos y redimirlos en {{ url('/') }}.

Gracias por ser parte del Plan de Fidelización de Harker Lloreda.

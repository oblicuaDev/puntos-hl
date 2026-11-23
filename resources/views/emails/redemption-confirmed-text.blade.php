¡Tu canje fue confirmado!

Hola {{ $redemption->patient->first_name }}, confirmamos tu canje de {{ $redemption->reward_title_snapshot }} (código {{ $redemption->code }}).

Puntos usados: {{ number_format($redemption->points_used) }}
Dinero pagado: ${{ number_format($redemption->money_amount_cop, 0, ',', '.') }} COP

Nos pondremos en contacto contigo en el menor tiempo posible para agendar tu cita, o puedes comunicarte directamente a nuestros canales de atención.

Gracias por ser parte del Plan de Fidelización de Harker Lloreda.

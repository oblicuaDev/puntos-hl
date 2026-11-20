<x-mail::message>
# ¡Tu canje fue confirmado!

Hola {{ $redemption->patient->first_name }},

Confirmamos tu canje **{{ $redemption->reward_title_snapshot }}** (código {{ $redemption->code }}).

- Puntos usados: {{ $redemption->points_used }}
- Dinero pagado: ${{ number_format($redemption->money_amount_cop, 0, ',', '.') }} COP

Nos pondremos en contacto contigo en el menor tiempo posible para agendar tu cita, o puedes comunicarte directamente a nuestros teléfonos de contacto.

Gracias por ser parte del Plan de Fidelización de Harker Lloreda.
</x-mail::message>

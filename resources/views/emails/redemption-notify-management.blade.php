<x-mail::message>
# Nuevo canje de puntos

- Código: {{ $redemption->code }}
- Paciente: {{ $redemption->patient->fullName() }} ({{ $redemption->patient->document_number }})
- Premio: {{ $redemption->reward_title_snapshot }}
- Puntos usados: {{ $redemption->points_used }}
- Dinero pagado: ${{ number_format($redemption->money_amount_cop, 0, ',', '.') }} COP
- Método: {{ $redemption->payment_method }}

Contactar al paciente para agendar su cita.
</x-mail::message>

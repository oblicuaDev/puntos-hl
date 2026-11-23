@php
    $paymentMethodLabel = match ($redemption->payment_method) {
        'points_only' => 'Solo puntos',
        'mixed' => 'Puntos + dinero',
        default => $redemption->payment_method,
    };
@endphp
Nuevo canje de puntos

Código: {{ $redemption->code }}
Paciente: {{ $redemption->patient->fullName() }} ({{ $redemption->patient->document_number }})
Premio: {{ $redemption->reward_title_snapshot }}
Puntos usados: {{ number_format($redemption->points_used) }}
Dinero pagado: ${{ number_format($redemption->money_amount_cop, 0, ',', '.') }} COP
Método de pago: {{ $paymentMethodLabel }}

Contactar al paciente para agendar su cita.

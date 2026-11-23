@php
    $paymentMethodLabel = match ($redemption->payment_method) {
        'points_only' => 'Solo puntos',
        'mixed' => 'Puntos + dinero',
        default => $redemption->payment_method,
    };
@endphp
<x-emails.layout
    eyebrow="Nuevo canje"
    :preheader="'Nuevo canje de '.$redemption->patient->fullName().' — código '.$redemption->code"
>
    <h1 style="margin:0 0 16px;font-size:23px;line-height:1.3;color:#1E1E1E;font-weight:700;">Nuevo canje de puntos</h1>

    <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#44484D;">
        El paciente <strong style="color:#1E1E1E;">{{ $redemption->patient->fullName() }}</strong>
        realizó un canje. Contactarlo para agendar su cita.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E7E5E1;border-radius:15px;margin:0 0 24px;">
        <tr>
            <td style="padding:18px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    @foreach ([
                        'Código' => $redemption->code,
                        'Paciente' => $redemption->patient->fullName().' ('.$redemption->patient->document_number.')',
                        'Premio' => $redemption->reward_title_snapshot,
                        'Puntos usados' => number_format($redemption->points_used),
                        'Dinero pagado' => '$'.number_format($redemption->money_amount_cop, 0, ',', '.').' COP',
                        'Método de pago' => $paymentMethodLabel,
                    ] as $label => $value)
                        <tr>
                            <td style="padding:8px 0;font-size:13px;color:#6B6C6F;{{ $loop->first ? '' : 'border-top:1px solid #E7E5E1;' }}">{{ $label }}</td>
                            <td style="padding:8px 0;font-size:14px;color:#1E1E1E;font-weight:600;text-align:right;{{ $loop->first ? '' : 'border-top:1px solid #E7E5E1;' }}">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:0;font-size:14px;line-height:1.6;color:#44484D;">
        Contactar al paciente para agendar su cita.
    </p>
</x-emails.layout>

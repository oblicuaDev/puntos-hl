<x-emails.layout
    eyebrow="Puntos acumulados"
    :preheader="'Acumulaste '.number_format($points, 0, ',', '.').' puntos. Tu nuevo saldo es '.number_format($balance, 0, ',', '.').' puntos.'"
>
    <h1 style="margin:0 0 16px;font-size:23px;line-height:1.3;color:#1E1E1E;font-weight:700;">¡Acumulaste {{ number_format($points, 0, ',', '.') }} puntos!</h1>

    <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#44484D;">
        Hola {{ $patient->first_name }}, sumamos a tu cuenta los puntos de tu compra más reciente en Harker Lloreda.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E7E5E1;border-radius:15px;margin:0 0 24px;">
        <tr>
            <td style="padding:18px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding:6px 0;font-size:13px;color:#6B6C6F;">Puntos acumulados</td>
                        <td style="padding:6px 0;font-size:14px;color:#1E1E1E;font-weight:600;text-align:right;">{{ number_format($points, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 0 0;font-size:13px;color:#6B6C6F;border-top:1px solid #E7E5E1;">Tu nuevo saldo</td>
                        <td style="padding:10px 0 0;font-size:14px;color:#1E1E1E;font-weight:600;text-align:right;border-top:1px solid #E7E5E1;">{{ number_format($balance, 0, ',', '.') }} puntos</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#44484D;">
        Estos puntos tienen una vigencia de {{ (int) setting('points_expiration_months', 12) }} meses. Puedes consultarlos y redimirlos en <a href="{{ url('/') }}" style="color:#335C82;">{{ url('/') }}</a>.
    </p>
    <p style="margin:0;font-size:14px;line-height:1.6;color:#44484D;">
        Gracias por ser parte del Plan de Fidelización de Harker Lloreda.
    </p>
</x-emails.layout>

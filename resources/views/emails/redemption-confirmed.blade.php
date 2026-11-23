<x-emails.layout
    eyebrow="Canje confirmado"
    :preheader="'Confirmamos tu canje de '.$redemption->reward_title_snapshot.' — código '.$redemption->code"
>
    <h1 style="margin:0 0 16px;font-size:23px;line-height:1.3;color:#1E1E1E;font-weight:700;">¡Tu canje fue confirmado!</h1>

    <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#44484D;">
        Hola {{ $redemption->patient->first_name }}, confirmamos tu canje de
        <strong style="color:#1E1E1E;">{{ $redemption->reward_title_snapshot }}</strong>
        (código <strong style="color:#1E1E1E;">{{ $redemption->code }}</strong>).
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E7E5E1;border-radius:15px;margin:0 0 24px;">
        <tr>
            <td style="padding:18px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding:6px 0;font-size:13px;color:#6B6C6F;">Puntos usados</td>
                        <td style="padding:6px 0;font-size:14px;color:#1E1E1E;font-weight:600;text-align:right;">{{ number_format($redemption->points_used) }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 0 0;font-size:13px;color:#6B6C6F;border-top:1px solid #E7E5E1;">Dinero pagado</td>
                        <td style="padding:10px 0 0;font-size:14px;color:#1E1E1E;font-weight:600;text-align:right;border-top:1px solid #E7E5E1;">${{ number_format($redemption->money_amount_cop, 0, ',', '.') }} COP</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#44484D;">
        Nos pondremos en contacto contigo en el menor tiempo posible para agendar tu cita, o puedes comunicarte directamente a nuestros canales de atención.
    </p>
    <p style="margin:0;font-size:14px;line-height:1.6;color:#44484D;">
        Gracias por ser parte del Plan de Fidelización de Harker Lloreda.
    </p>
</x-emails.layout>

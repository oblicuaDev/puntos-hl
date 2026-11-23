@props(['eyebrow' => null, 'preheader' => ''])
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light">
<title>{{ config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background-color:#F2F4F6;font-family:'Montserrat',Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader }}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F2F4F6;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background-color:#FFFFFF;border-radius:20px;border:1px solid #E7E5E1;overflow:hidden;">
                    <tr>
                        <td style="background-color:#335C82;padding:26px 36px;">
                            <x-brand-logo style="height:24px;width:auto;display:block;color:#FFFFFF" />
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:40px 36px 28px;">
                            @if($eyebrow)
                                <span style="display:inline-block;background-color:#EAF0F5;color:#335C82;font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:6px 14px;border-radius:100px;margin:0 0 18px;">{{ $eyebrow }}</span>
                            @endif
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 36px 28px;border-top:1px solid #E7E5E1;">
                            <p style="margin:0;font-size:12px;line-height:1.6;color:#6B6C6F;">Este es un mensaje automático del Plan de Fidelización Harker · Lloreda. Por favor no respondas a este correo.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

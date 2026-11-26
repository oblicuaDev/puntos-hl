<x-emails.layout
    eyebrow="Carga de facturación"
    :preheader="$run->error ? 'La carga diaria de facturación falló.' : $run->rows_processed.' filas acreditadas, '.$run->rows_pending.' pendientes.'"
>
    <h1 style="margin:0 0 16px;font-size:23px;line-height:1.3;color:#1E1E1E;font-weight:700;">Carga diaria de facturación</h1>

    @if ($run->error)
        <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#B42318;">
            <strong>La carga no terminó:</strong> {{ $run->error }}
        </p>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E7E5E1;border-radius:15px;margin:0 0 24px;">
        <tr>
            <td style="padding:18px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    @foreach ([
                        'Filas revisadas' => $run->rows_read,
                        'Acreditadas' => $run->rows_processed,
                        'Pacientes creados' => $run->patients_created,
                        'Pendientes' => $run->rows_pending,
                        'Puntos acreditados' => number_format($run->points_total, 0, ',', '.'),
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

    @if ($run->pending_rows)
        <h2 style="margin:0 0 12px;font-size:17px;color:#1E1E1E;">Filas pendientes</h2>
        <p style="margin:0 0 12px;font-size:14px;line-height:1.6;color:#44484D;">
            Quedaron sin la X en REGISTRADO y con el motivo en la columna NOVEDAD SISTEMA. Al corregirlas en la hoja se procesan en la siguiente carga.
        </p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
            @foreach ($run->pending_rows as $row)
                <tr>
                    <td style="padding:10px 0;font-size:13px;line-height:1.5;color:#44484D;border-top:1px solid #E7E5E1;">
                        <strong style="color:#1E1E1E;">Fila {{ $row['row'] }}</strong>
                        @if ($row['name'] || $row['document']) — {{ $row['name'] }} {{ $row['document'] ? '('.$row['document'].')' : '' }} @endif
                        <br>{{ $row['message'] }}
                    </td>
                </tr>
            @endforeach
        </table>
    @endif
</x-emails.layout>

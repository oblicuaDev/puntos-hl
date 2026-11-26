Carga diaria de facturación

@if ($run->error)
La carga no terminó: {{ $run->error }}

@endif
Filas revisadas: {{ $run->rows_read }}
Acreditadas: {{ $run->rows_processed }}
Pacientes creados: {{ $run->patients_created }}
Pendientes: {{ $run->rows_pending }}
Puntos acreditados: {{ number_format($run->points_total, 0, ',', '.') }}
@if ($run->pending_rows)

Filas pendientes (quedaron sin X en REGISTRADO y con el motivo en NOVEDAD SISTEMA; al corregirlas se procesan en la siguiente carga):
@foreach ($run->pending_rows as $row)
- Fila {{ $row['row'] }}{{ $row['name'] || $row['document'] ? ' — '.$row['name'].($row['document'] ? ' ('.$row['document'].')' : '') : '' }}: {{ $row['message'] }}
@endforeach
@endif

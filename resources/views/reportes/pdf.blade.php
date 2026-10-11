<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        p { color: #555; margin-top: 0; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #bbb; padding: 5px; text-align: left; }
        th { background: #eee; }
        tr:nth-child(even) { background: #f8f8f8; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <p>Período: {{ $filtros['fecha_desde'] }} al {{ $filtros['fecha_hasta'] }} · Generado: {{ now()->format('Y-m-d H:i') }}</p>
    <table>
        <thead><tr>@foreach ($columnas as $columna)<th>{{ $columna }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr>@foreach ($fila as $valor)<td>{{ $valor }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($columnas) }}">No hay registros para los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #243447; font-size: 10px; }
        h1 { font-size: 18px; margin: 0 0 4px; } h2 { font-size: 13px; margin: 18px 0 8px; }
        .muted { color: #687789; } .cards { width: 100%; margin: 16px 0; }
        .cards td { border: 1px solid #dce2e8; padding: 9px; width: 25%; }
        .amount { font-size: 15px; font-weight: bold; margin-top: 4px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #dce2e8; padding: 6px; text-align: left; }
        table.data th { background: #f1f4f7; } .right { text-align: right !important; }
        .warning { border: 1px solid #e9cc76; background: #fff8df; padding: 9px; margin: 12px 0; }
    </style>
</head>
<body>
    <h1>Resumen operativo y financiero · {{ $orden->codigo }}</h1>
    <div class="muted">Titular: {{ $orden->nombreTitular() }} · Fecha: {{ $orden->fecha?->format('d/m/Y') ?? '—' }} · Estado: {{ $orden->estadoTexto() }}</div>
    <table class="cards"><tr>
        <td>Ingresos registrados<div class="amount">NIO {{ number_format($ingresos['total_nio'], 2) }}</div>{{ $ingresos['cantidad'] }} registro(s)</td>
        <td>Costos directos de procesos<div class="amount">NIO {{ number_format($costos['directos_nio'], 2) }}</div></td>
        <td>Costos generales<div class="amount">NIO {{ number_format($costos['generales_nio'], 2) }}</div>NIO {{ number_format($costos['generales_distribuidos_nio'], 2) }} distribuidos</td>
        <td>Costos conocidos<div class="amount">NIO {{ number_format($costos['total_conocido_nio'], 2) }}</div></td>
    </tr></table>
    <table class="cards"><tr>
        <td>Resultado real conocido<div class="amount">{{ $resultado_financiero['resultado_real_nio'] === null ? 'Sin ingresos registrados' : 'NIO '.number_format($resultado_financiero['resultado_real_nio'], 2) }}</div>Ingresos registrados + oro de la empresa valorizado − costos. No representa caja; no registre el mismo oro de nuevo como ingreso.</td>
        <td>Resultado proyectado<div class="amount">{{ $resultado_financiero['resultado_proyectado_nio'] === null ? 'Sin acuerdo valorable' : 'NIO '.number_format($resultado_financiero['resultado_proyectado_nio'], 2) }}</div>Tarifa pactada y oro propio valorizado − costos.</td>
        <td>Tarifa pactada<div class="amount">NIO {{ number_format($resultado_financiero['tarifa_pactada_nio'], 2) }}</div>Valor contractual convertido a la moneda base.</td>
        <td>Oro asignado a la empresa<div class="amount">NIO {{ number_format($resultado_financiero['oro_empresa_nio'], 2) }}</div>{{ number_format($resultado_financiero['oro_empresa_gramos'], 4) }} g · precio histórico de cada liquidación.</td>
    </tr></table>
    @if($costos['directos_sin_equivalente'] || $costos['generales_sin_equivalente'] || $ingresos['sin_equivalente'] || $costos['generales_pendientes_nio'] > 0)
        <div class="warning"><strong>Datos incompletos:</strong>
            {{ $costos['directos_sin_equivalente'] }} costo(s) directo(s), {{ $costos['generales_sin_equivalente'] }} costo(s) general(es) y {{ $ingresos['sin_equivalente'] }} ingreso(s) sin equivalente NIO.
            Gastos generales pendientes de distribuir: NIO {{ number_format($costos['generales_pendientes_nio'], 2) }}.
            {{ $costos['motivo_pendiente'] }} Estos importes conocidos no representan un total completo.
        </div>
    @endif
    @if($resultado_financiero['datos_incompletos'])
        <div class="warning"><strong>El resultado es provisional:</strong>
            {{ $resultado_financiero['costos_pendientes_equivalente'] }} costo(s) sin equivalente NIO;
            {{ $resultado_financiero['ingresos_pendientes_equivalente'] }} ingreso(s) sin equivalente NIO;
            {{ $resultado_financiero['tarifas_pendientes_conversion'] }} tarifa(s) sin tipo de cambio;
            {{ $resultado_financiero['oro_pendiente_valoracion'] }} liquidación(es) sin precio/tipo de cambio;
            @if($resultado_financiero['equipos_pendientes']) hay costos de equipos pendientes de confirmar. @endif
        </div>
    @endif
    <h2>Seguimiento por proceso</h2>
    <table class="data">
        <thead><tr><th>Proceso</th><th>Etapa</th><th>Estado</th><th>Fechas</th><th>Equipos asignados</th><th class="right">Directo NIO</th><th class="right">General asignado NIO</th><th class="right">Total NIO</th></tr></thead>
        <tbody>
        @forelse($procesos as $item)
            @php($proceso = $item['proceso'])
            <tr>
                <td>{{ $proceso->codigo }}</td><td>{{ $proceso->etapa?->nombre ?? '—' }}</td><td>{{ $proceso->estadoTexto() }}</td>
                <td>{{ $proceso->fecha_inicio?->format('Y-m-d') ?? 'Sin inicio' }} – {{ $proceso->fecha_fin?->format('Y-m-d') ?? 'En curso' }}</td>
                <td>{{ $item['equipos']->map(fn ($uso) => trim(($uso->equipo?->codigo ?? '').' · '.($uso->equipo?->nombre ?? '')))->filter()->join(', ') ?: 'Sin equipo asignado' }}</td>
                <td class="right">{{ number_format($item['costo_directo_nio'], 2) }}</td>
                <td class="right">{{ number_format($item['gasto_general_asignado_nio'], 2) }}</td>
                <td class="right">{{ number_format($item['costo_total_nio'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="8">La orden aún no tiene procesos registrados.</td></tr>
        @endforelse
        </tbody>
    </table>
    <p class="muted">La distribución de gastos generales es informativa y no crea ni duplica movimientos de costo. Generado {{ now()->format('d/m/Y H:i') }}.</p>
</body>
</html>

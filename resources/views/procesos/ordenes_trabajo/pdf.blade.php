<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de trabajo {{ $ordenTrabajo->codigo }}</title>
    <style>
        @page { margin: 28px 34px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #202124; }
        h1 { margin: 0; font-size: 19px; }
        h2 { margin: 0 0 7px; font-size: 12px; }
        p { margin: 4px 0; }
        .encabezado { width: 100%; border-bottom: 2px solid #222; padding-bottom: 12px; margin-bottom: 14px; }
        .encabezado td { vertical-align: top; }
        .codigo { text-align: right; font-size: 16px; font-weight: bold; }
        .muted { color: #555; }
        .datos { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .datos td { width: 50%; border: 1px solid #aaa; padding: 7px; vertical-align: top; }
        .etiqueta { font-size: 8px; text-transform: uppercase; color: #555; margin-bottom: 4px; }
        .valor { font-size: 11px; font-weight: bold; }
        .descripcion { border: 1px solid #aaa; padding: 8px; margin: 10px 0 14px; min-height: 30px; }
        .procesos { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .procesos th, .procesos td { border: 1px solid #777; padding: 6px; }
        .procesos th { background: #eee; text-align: left; font-size: 9px; }
        .procesos td { height: 25px; }
        .check { width: 23px; text-align: center; font-size: 14px; }
        .numero { width: 26px; text-align: center; }
        .nota { margin: 9px 0; font-size: 9px; }
        .firma { width: 100%; border-collapse: collapse; margin-top: 38px; page-break-inside: avoid; }
        .firma td { width: 50%; text-align: center; vertical-align: bottom; padding: 0 18px; }
        .linea-firma { height: 44px; border-bottom: 1px solid #222; }
        .rotulo-firma { padding-top: 6px; font-size: 9px; }
        .pie { position: fixed; bottom: -20px; left: 0; right: 0; font-size: 8px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td>
                <h1>ORDEN DE TRABAJO</h1>
                <p class="muted">Documento para revisión y autorización del cliente</p>
            </td>
            <td class="codigo">
                {{ $ordenTrabajo->codigo }}
                <div class="muted" style="font-size: 10px; font-weight: normal; margin-top: 5px;">
                    Fecha: {{ $ordenTrabajo->fecha?->format('d/m/Y') ?? '—' }}
                </div>
            </td>
        </tr>
    </table>

    <table class="datos">
        <tr>
            <td>
                <div class="etiqueta">Cliente</div>
                <div class="valor">{{ $ordenTrabajo->cliente?->nombre ?? '—' }}</div>
                @if($ordenTrabajo->cliente?->telefono)<p>Teléfono: {{ $ordenTrabajo->cliente->telefono }}</p>@endif
                @if($ordenTrabajo->cliente?->direccion)<p>Dirección: {{ $ordenTrabajo->cliente->direccion }}</p>@endif
            </td>
            <td>
                <div class="etiqueta">Mineral recibido para procesamiento</div>
                <div class="valor">{{ number_format((float) $ordenTrabajo->peso_mineral, 3, ',', '.') }} {{ $ordenTrabajo->unidad_peso }}</div>
                <p><span class="etiqueta">Estado:</span> {{ $ordenTrabajo->estadoTexto() }}</p>
            </td>
        </tr>
    </table>

    @if($ordenTrabajo->descripcion)
        <div class="descripcion">
            <div class="etiqueta">Descripción / instrucciones</div>
            {{ $ordenTrabajo->descripcion }}
        </div>
    @endif

    <h2>Procesos acordados</h2>
    <p class="nota">Revise los procesos que se realizarán. Tache cualquier proceso que no corresponda y confirme la lista antes de firmar.</p>

    <table class="procesos">
        <thead>
            <tr>
                <th class="check">✓</th>
                <th class="numero">N.º</th>
                <th>Proceso / etapa</th>
                <th>Detalle u observaciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($procesos as $proceso)
                <tr>
                    <td class="check">□</td>
                    <td class="numero">{{ $loop->iteration }}</td>
                    <td><strong>{{ $proceso->etapa?->nombre ?? 'Etapa no disponible' }}</strong><br>{{ $proceso->codigo }}</td>
                    <td>{{ $proceso->observaciones }}</td>
                </tr>
            @endforeach
            @for($i = 1; $i <= $renglonesEnBlanco; $i++)
                <tr>
                    <td class="check">□</td>
                    <td class="numero">{{ $procesos->count() + $i }}</td>
                    <td></td>
                    <td></td>
                </tr>
            @endfor
        </tbody>
    </table>

    <p class="nota">La firma confirma que el cliente revisó y autorizó los procesos indicados en esta orden. Los renglones completados a mano se ingresarán posteriormente al sistema.</p>

    <table class="firma">
        <tr>
            <td>
                <div class="linea-firma"></div>
                <div class="rotulo-firma">Firma del cliente</div>
            </td>
            <td>
                <div class="linea-firma"></div>
                <div class="rotulo-firma">Nombre y firma del responsable del taller</div>
            </td>
        </tr>
        <tr>
            <td style="padding-top: 20px;">Fecha: ______________________________</td>
            <td style="padding-top: 20px;">Fecha: ______________________________</td>
        </tr>
    </table>

    <div class="pie">{{ $ordenTrabajo->codigo }} · Orden de trabajo para autorización del cliente</div>
</body>
</html>

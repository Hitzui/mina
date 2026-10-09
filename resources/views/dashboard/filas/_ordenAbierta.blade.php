{{--
    Una fila de la lista de ordenes abiertas.

    Recibe "item", que es una OrdenesTrabajo con dos atributos de mas —
    "procesos_total" y "procesos_terminados"— que son subconsultas que puso
    PanelDelTaller.

    ---------------------------------------------------------------------------
    POR QUÉ LLEVA LA BARRA DE AVANCE Y NO SOLO "2 DE 3"
    ---------------------------------------------------------------------------

    Porque "¿cómo va esta orden?" se contesta con una barra y no con una fracción.
    Una barra se mira en un cuarto de segundo y una fracción hay que restarla mentalmente: "2 de 3" puede ser una orden casi hecha o una que va por la mitad, y el ojo no sabe cuál de las dos sin pensar.

    Y por debajo de la barra va el texto, porque la barra sola no se lee en un móvil
    con la luz del sol y el texto sí. Los dos juntos: la barra para el vistazo y el
    texto para el que tiene que confirmar.

    ---------------------------------------------------------------------------
    POR QUÉ LOS PROCESOS TERMINADOS SE CUENTAN CON EL ESTADO CALCULADO
    ---------------------------------------------------------------------------

    Porque la columna "estado" de un proceso solo sirve para decir si se canceló a
    mano. Si se contara con "fecha_fin no es nula", un proceso con la fecha de fin
    puesta para dentro de dos días saldría como terminado y la barra marcaría más
    avance del que hay. Está escrito en PanelDelTaller y en el modelo; aquí solo se
    pinta lo que ya viene contado.
--}}
@php
    $total = (int) ($item->procesos_total ?? 0);
    $hechos = (int) ($item->procesos_terminados ?? 0);

    // Sin procesos el porcentaje no se calcula: dividir por cero sale mal y en PHP
    // es un error. Una orden sin procesos es una orden recien abierta, y sale con la
    // barra vacía, que es la verdad.
    $porcentaje = $total > 0 ? (int) round(($hechos / $total) * 100) : 0;

    $colorBarra = match (true) {
        $total === 0 => 'bg-secondary',
        $porcentaje >= 100 => 'bg-success',
        $porcentaje > 0 => 'bg-primary',
        default => 'bg-warning',
    };
@endphp

<div>

    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">

        <a href="{{ route('procesos.ordenes_trabajo.show', $item->id) }}"
           class="fw-semibold text-decoration-none flex-shrink-0"
           style="font-size: .82rem;">
            {{ $item->codigo }}
        </a>

        <span class="badge {{ $item->estadoClase() }} flex-shrink-0"
              style="font-size: .68rem;">
            {{ $item->estadoTexto() }}
        </span>

    </div>

    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
        <span class="text-secondary text-truncate" style="font-size: .76rem; min-width: 0;">
            {{ $item->cliente?->nombre ?? 'Sin cliente' }}
        </span>
        <span class="text-secondary flex-shrink-0" style="font-size: .7rem;">
            {{ $item->fecha->format('d/m/Y') }}
        </span>
    </div>

    @if ($total > 0)
        <div class="progress" style="height: .32rem;" role="progressbar"
             aria-valuenow="{{ $porcentaje }}" aria-valuemin="0" aria-valuemax="100"
             aria-label="Procesos terminados de {{ $item->codigo }}">
            <div class="progress-bar {{ $colorBarra }}" style="width: {{ $porcentaje }}%;"></div>
        </div>

        <div class="text-secondary mt-1" style="font-size: .7rem;">
            {{ $hechos }} de {{ $total }}
            {{ $total === 1 ? 'proceso' : 'procesos' }} terminados
        </div>
    @else
        <div class="text-secondary" style="font-size: .7rem;">
            Sin procesos todavía
        </div>
    @endif

</div>

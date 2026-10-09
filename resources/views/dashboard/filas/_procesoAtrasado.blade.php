{{--
    Una fila de la lista de procesos atrasados.

    Recibe "item", que es un ProcesosOrden con un atributo "dias_atraso" que le ha
    puesto PanelDelTaller.

    ---------------------------------------------------------------------------
    POR QUÉ LOS DÍAS VAN EN UNA PASTILLA Y EN ROJO CUANDO SON MUCHOS
    ---------------------------------------------------------------------------

    Porque el número solo no se lee de un vistazo. Un proceso con 1 día de atraso y
    uno con 40 son la misma pastilla si no cambia el color, y quien mira el panel
    un lunes por la mañana no va a leer los números: va a ver qué está rojo.

    El corte está en cinco días, y no es un número redondo bonito: es más o menos lo
    que dura una etapa de este taller. Por debajo de cinco días es ruido —hoy
    cualquier proceso sin fecha de fin es "en proceso"— y por encima hay que mirar.

    ---------------------------------------------------------------------------
    POR QUÉ EL NOMBRE DEL PROCESO SE RECORTA
    ---------------------------------------------------------------------------

    Porque "P-003 - Trituración" y un nombre de etapa largo se salen del móvil y
    empujan el número de días fuera de la pantalla. Con text-truncate y un title se
    ven los dos, y en un escritorio —donde no hay problema de espacio— el texto
    completo sale en el title al pasar el ratón.

    La recurrencia se recorta antes que el nombre, porque el código es lo que se
    busca cuando uno quiere abrir la orden, y el nombre de la etapa se reconoce de
    sobra.
--}}
@php
    $dias = (int) ($item->dias_atraso ?? 0);

    // El color segun lo que duele, no segun una escala de colores bonita
    $color = match (true) {
        $dias >= 20 => 'bg-danger',
        $dias >= 5 => 'bg-warning text-dark',
        default => 'bg-secondary',
    };

    $etapa = $item->etapa?->nombre;
@endphp

<div class="d-flex align-items-center justify-content-between gap-2">

    <div class="min-w-0 flex-grow-1">

        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="{{ route('procesos.ordenes_trabajo.show', $item->orden_trabajo_id) }}"
               class="text-primary fw-semibold text-decoration-none flex-shrink-0"
               style="font-size: .8rem;">
                {{ $item->orden_trabajo?->codigo ?? '—' }}
            </a>

            <span class="text-secondary text-truncate" style="font-size: .78rem; min-width: 0;"
                  title="{{ $etapa ? $item->codigo . ' · ' . $etapa : $item->codigo }}">
                {{ $etapa ?? $item->codigo }}
            </span>
        </div>

        <div class="text-secondary" style="font-size: .72rem;">
            Empezó el {{ $item->fecha_inicio->format('d/m/Y') }}
        </div>

    </div>

    <span class="badge {{ $color }} flex-shrink-0"
          style="font-size: .7rem;"
          title="{{ $dias === 1 ? '1 día sin terminar' : $dias . ' días sin terminar' }}">
        {{ $dias }}&nbsp;{{ $dias === 1 ? 'día' : 'días' }}
    </span>

</div>

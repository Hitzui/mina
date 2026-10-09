{{--
    Una fila de la lista de las ultimas ordenes.

    Recibe "item", que es una OrdenesTrabajo normal.

    ---------------------------------------------------------------------------
    POR QUÉ NO LLEVA BARRA DE AVANCE SI ESTA YA LA TIENE LA DE ARRIBA
    ---------------------------------------------------------------------------

    Porque son dos listas para dos preguntas distintas. La de ordenes abiertas es
    "¿qué tengo entre manos?" y por eso lleva el avance, que es lo que se necesita
    para contestarla. Esta es "¿qué ha entrado esta semana?", y para eso lo que
    importa es cuándo se creó y en qué quedó, no cuántas etapas lleva.

    Si las dos llevaran barra, la segunda repetiria un dato que ya se ve tres
    centímetros más arriba en las mismas órdenes, y repetir en un panel es
    gastar sitio en no decir nada nuevo.

    ---------------------------------------------------------------------------
    Y POR QUÉ SÍ SALEN LAS FINALIZADAS Y LAS CANCELADAS
    ---------------------------------------------------------------------------

    Porque a veces lo que se busca es "qué pasó con la OT-2026-0007", y esa puede
    estar finalizada. Filtrar por abiertas haría que esa orden desapareciera del
    panel justo cuando se busca.
--}}
<div class="d-flex align-items-center justify-content-between gap-2">

    <div class="min-w-0 flex-grow-1">

        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="{{ route('procesos.ordenes_trabajo.show', $item->id) }}"
               class="fw-semibold text-decoration-none flex-shrink-0"
               style="font-size: .8rem;">
                {{ $item->codigo }}
            </a>

            <span class="badge {{ $item->estadoClase() }} flex-shrink-0"
                  style="font-size: .66rem;">
                {{ $item->estadoTexto() }}
            </span>
        </div>

        <div class="text-secondary text-truncate" style="font-size: .74rem; min-width: 0;">
            {{ $item->cliente?->nombre ?? 'Sin cliente' }}
        </div>

    </div>

    <span class="text-secondary flex-shrink-0" style="font-size: .72rem;">
        {{ $item->fecha->format('d/m/Y') }}
    </span>

</div>

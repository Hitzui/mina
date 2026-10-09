{{--
    Una lista del panel: un título, cuántas cosas hay, y las cosas.

    ---------------------------------------------------------------------------
    POR QUÉ EL COMPONENTE HACE EL RECORRIDO Y LA VISTA PONE CADA FILA
    ---------------------------------------------------------------------------

    Porque el recorte a ocho filas tiene que estar en un solo sitio, y si cada lista
    hace su propio @foreach con su propio slice, en cuanto una lista nueva pase de
    ocho a diez hay que buscar los tres sitios para cambiarlo. Y al buscarlo, uno se
    encuentra con dos que ya se cambiaron y uno que no.

    El componente hace el recorte y el @foreach; la fila de cada lista es un
    parcial aparte que se pasa con el nombre en "fila". Así cada lista tiene su
    marcado en su archivo y el recorte vive en un archivo.

    ---------------------------------------------------------------------------
    POR QUÉ UNA LISTA Y NO UNA TABLA
    ---------------------------------------------------------------------------

    Porque el panel se mira en el móvil y una tabla de tres columnas en un móvil son
    tres columnas de tres palabras cada una. Una lista de una línea por elemento
    —una pastilla de estado, un código, un nombre— se lee de un vistazo en cualquier
    tamaño.

    Y el "y N más" aparece SOLO cuando hay más de lo que cabe, porque un "mostrar
    más" que no hace nada es ruido, y en un taller donde casi siempre hay tres
    cosas que hacer ese ruido sale en casi todas las pantallas.

    ---------------------------------------------------------------------------
    POR QUÉ EL VACÍO DICE UNA COSA CONCRETA
    ---------------------------------------------------------------------------

    Porque "no hay datos" no dice qué hacer. Un proceso atrasado que no hay ninguno
    es una buena noticia y se dice: "Nada se quedó a medias". Una lista vacía que no
    explica nada hace pensar que la pantalla no está leyendo la base, que es un
    susto que este panel no tiene por qué dar.

    ---------------------------------------------------------------------------
    PARÁMETROS
    ---------------------------------------------------------------------------

    - titulo      El nombre de la lista.
    - items       La colección.
    - fila        El nombre del parcial con la fila. Sin esto no se pinta nada, y
                  es a proposito: una lista sin parcial de fila es un error de
                  escritura y se ve al abrir la pantalla, no en un móvil en la mano
                  de un cliente.
    - vacio       Lo que se pone cuando no hay filas.
    - icono       Icono de bootstrap.
    - colorIcono  Clase de color del icono.
    - maximo      Cuántas filas se ven antes de poner "y N más".
    - pie         Texto pequeño al pie de la lista. Opcional.
--}}
@props([
    'titulo',
    'items',
    'fila',
    'vacio' => 'Nada por aquí.',
    'icono' => 'bi-list-ul',
    'colorIcono' => 'bg-secondary',
    'maximo' => 8,
    'pie' => null,
])

@php
    $cuantas = $items->count();
    $visibles = $items->take($maximo);
    $quedan = $cuantas - $visibles->count();
@endphp

{{--
    Las clases de la tarjeta.

    "card" es la de bootstrap y "br-8" la del tema, que pone el radio de las
    esquinas. "widget-card" no existe: solo aparece en el nombre de unos ficheros
    scss del tema, de modo que se encuentra buscando el texto y no como clase, y con
    ella la tarjeta sale sin fondo ni bordes.

    Y "h-100" va en la tarjeta de la lista, no en la del panel: las tres listas van en
    la misma fila de la rejilla y sin esto el alto lo marca la mas larga de las tres y
    las otras dos se quedan con un hueco en blanco debajo, que es justo lo que se ve
    en un móvil donde bajan una debajo de otra.
--}}
<div class="card br-8 mb-3 h-100">

    <div class="card-header d-flex align-items-center justify-content-between gap-2
                py-3 px-3 border-bottom">

        <h6 class="mb-0 d-flex align-items-center gap-2 fw-semibold" style="font-size: .9rem;">
            <i class="bi {{ $icono }} {{ $colorIcono }} text-white rounded-circle
                      d-inline-flex align-items-center justify-content-center flex-shrink-0"
               style="width: 1.35rem; height: 1.35rem; font-size: .7rem;"></i>
            {{ $titulo }}
        </h6>

        @if ($cuantas > 0)
            <span class="badge bg-light text-secondary border fw-semibold">{{ $cuantas }}</span>
        @endif

    </div>

    @if ($cuantas === 0)

        <div class="card-body py-4 px-3">
            <p class="text-secondary text-center mb-0" style="font-size: .82rem;">
                <i class="bi bi-check2-circle d-block mb-2" style="font-size: 1.5rem; opacity: .45;"></i>
                {{ $vacio }}
            </p>
        </div>

    @else

        <div class="card-body p-0">
            <ul class="list-unstyled mb-0 panel-lista">

                @foreach ($visibles as $item)
                    <li class="border-bottom px-3 py-2">
                        @include($fila, ['item' => $item])
                    </li>
                @endforeach

            </ul>
        </div>

        @if ($quedan > 0)
            <div class="card-footer py-2 px-3 bg-transparent border-top text-center">
                <span class="text-secondary" style="font-size: .75rem;">
                    y {{ $quedan }} más
                </span>
            </div>
        @endif

    @endif

    @if ($pie)
        <div class="card-footer py-2 px-3 bg-transparent border-top">
            <span class="text-secondary d-block" style="font-size: .72rem; line-height: 1.4;">
                {{ $pie }}
            </span>
        </div>
    @endif

</div>

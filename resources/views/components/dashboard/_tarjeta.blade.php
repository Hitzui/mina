{{--
    Una tarjeta de numero del panel.

    ES UNA TARJETA PEQUENA A PROPOSITO, y no por que quede mejor. El taller la va
    a ver en un movil, y en un movil una pantalla cabe dos tarjetas lado a lado con
    el numero en grande y el nombre debajo. Si se hace grande para que en un
    escritorio se vea "impresionante", deja de caber dos y la pantalla se alarga el
    doble, que es lo contrario de lo que se quiere.

    PARAMETROS
    -----------
    - titulo   El nombre de la cifra.
    - valor    El numero, ya formateado. Se pasa ya formateado y no el numero en
               crudo por una razon que no es de estilo: el numero se formatea una
               vez, en el servidor, con el formato de Nicaragua —coma en los miles
               y punto en los decimales, 1,546.00— que es el que usan todas las
               tablas de la aplicacion. Si esta tarjeta formateara por su cuenta,
               habria dos formateadores y el dia que uno cambiara el otro no.
    - icono    Un nombre de icono de bootstrap.
    - color    La clase de color: bg-primary, bg-success, bg-warning, bg-info.
    - pie      Texto pequeno debajo del numero. Opcional.
    - aviso    Texto en color de aviso, debajo del pie. Opcional.
    - colorAviso  La clase del aviso: text-warning, text-danger, text-muted.
    - enlace   A donde lleva el numero entero. Opcional, y si no va la tarjeta no
               es pulsable.

    Y EL "pie" NO ES DECORACION. En una tarjeta de dinero es donde va de donde sale
    el numero —"750 en costos + 2.100 en mano de obra"— y en una de conteo es donde
    va el periodo. Un numero grande sin de donde sale es un numero del que hay que
    fiarse, y este panel es de un taller donde las cifras se miran para decidir si
    se compra algo esta semana o la otra.
--}}
@props([
    'titulo',
    'valor',
    'icono' => 'bi-graph-up',
    'color' => 'bg-primary',
    'pie' => null,
    'aviso' => null,
    'colorAviso' => 'text-muted',
    'enlace' => null,
])

@php
    /*
     * "card" es la de bootstrap —fondo blanco, borde y sombra— y "br-8" es la del
     * tema, que pone el radio de las esquinas a ocho pixeles.
     *
     * Y NO se usa "widget-card", que fue lo primero que se puso: esa clase no
     * existe. Solo aparece en el nombre de unos ficheros scss del tema, y por eso
     * se encuentra uno buscando el texto en el archivo y no como clase. Con ella la
     * tarjeta salia sin fondo ni bordes y el panel se veia como un Formulario suelto,
     * sin que nada dijera que faltaba el estilo.
     *
     * Y el radio lo pone "br-8" y no "rounded-3", porque el del tema manda con un
     * "!important" y "rounded-3" se perderia siempre. El resto de las pantallas del
     * proyecto usan "br-8" para lo mismo.
     */
    $tarjeta = 'card br-8 mb-3 ' . $color;
@endphp

<{{ $enlace ? 'a' : 'div' }} @if ($enlace) href="{{ $enlace }}" @endif
    class="{{ $tarjeta }} {{ $enlace ? 'text-reset' : '' }}">

    <div class="card-body p-3">

        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">

            <h6 class="mb-0 text-secondary text-uppercase small fw-semibold"
                style="font-size: .72rem; letter-spacing: .04em;">
                {{ $titulo }}
            </h6>

            {{--
                El circulito del icono. El tamaño va con "avatar-sm" porque "avatar-xs"
                no existe en la hoja del tema: los tamaños que hay son sm, lg y xl, y xs
                salia del tamaño de bootstrap sin el estilo del tema, que es lo que se
                ve con un icono pegado al borde del circulito.
            --}}
            <span class="avatar avatar-sm {{ $color }} rounded-circle d-inline-flex
                         align-items-center justify-content-center flex-shrink-0">
                <i class="bi {{ $icono }} text-white" style="font-size: .7rem;"></i>
            </span>

        </div>

        <h3 class="mb-0 fw-bold lh-1"
            style="font-size: 1.5rem; overflow-wrap: anywhere;">
            {{ $valor }}
        </h3>

        @if ($pie)
            <p class="text-secondary mb-0 mt-2" style="font-size: .75rem; line-height: 1.35;">
                {{ $pie }}
            </p>
        @endif

        @if ($aviso)
            <p class="mb-0 mt-1 {{ $colorAviso }}" style="font-size: .72rem; line-height: 1.35;">
                {{ $aviso }}
            </p>
        @endif

    </div>

</{{ $enlace ? 'a' : 'div' }}>

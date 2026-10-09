{{--
    Una tarjeta de numero del panel.

    ===========================================================================
    POR QUÉ HAY UNA CLASE PHP EN MEDIO
    ===========================================================================

    Porque la letra de la tarjeta tiene que decidirse según el fondo, y eso es una
    cuenta de contraste: el blanco se lee sobre el azul y el morado del tema, y no se
    lee sobre el ámbar —2,25 sobre 1— ni sobre el verde ni sobre el rojo ni sobre el
    cyan. Y la cuenta no se puede hacer en el html, porque blade no sabe aritmética
    de colores, así que la hace App\Support\TextoSobreFondo y esta vista solo
    aplica lo que le dice.

    Y hay una razón de fondo: el gris que llevaba antes daba 1,04 sobre 1 con el
    azul. Uno es el contraste de un color consigo mismo, que es decir invisible, y
    aun así la página salía sin errores, porque una letra que no se ve no da ningún
    error. El número grande se veía porque iba en negro y los otros dos textos no se
    veían nada.

    ===========================================================================
    POR QUÉ EL FONDO EN COLOR Y LA LETRA NEGRA EN UNO SOLO
    ===========================================================================

    Porque el fondo es el color del estado y así se reconoce la tarjeta de un
    vistazo: azul es pendiente, ámbar es en proceso, verde es finalizada. Si el
    fondo fuera blanco, esa información se perdería y el panel sería una lista de
    números iguales.

    ===========================================================================
    PARÁMETROS
    ===========================================================================

    - titulo       El nombre de la cifra.
    - valor        El numero, ya formateado en el servidor. Se pasa formateado y no
                   en crudo porque el formato es el de Nicaragua —coma en los miles,
                   1,546.00— y es el que hacen todas las tablas de la aplicación con
                   number_format. Si esta tarjeta formateara por su cuenta habría dos
                   formateadores y el día que uno cambiara el otro no.
    - hex          El color del fondo en hexadecimal. Es lo que decide el color de la
                   letra, así que es obligatorio si la tarjeta va coloreada. Sin él
                   la tarjeta sale con la letra oscura sobre fondo blanco, que es lo
                   de siempre.
    - color        La clase de bootstrap del fondo: bg-primary, bg-warning…
    - icono        Un nombre de icono de bootstrap.
    - pie          Texto pequeño debajo del numero. Opcional.
    - aviso        Texto de advertencia, en una pastilla. Opcional.
    - enlace       A donde lleva la tarjeta entera. Opcional.

    ===========================================================================
    Y POR QUÉ "EL PIE" NO ES DECORACIÓN
    ===========================================================================

    Porque en una tarjeta de dinero es donde va de dónde sale el número —"750 en
    costos + 2.100 en mano de obra"— y en una de conteo es donde va lo que significa
    el número. Un número grande sin de dónde sale es un número del que hay que
    fiarse, y este panel es de un taller donde las cifras se miran para decidir si se
    compra algo esta semana o la otra.

    Por eso el pie va en el mismo color que el número y sin bajar la opacidad: bajar
    la opacidad baja el contraste, y el contraste es justo lo que hay que subir. La
    jerarquía la dan la mayúscula del título y el tamaño del número, no el color.
--}}
@props([
    'titulo',
    'valor',
    'hex' => null,
    'color' => '',
    'icono' => 'bi-graph-up',
    'pie' => null,
    'aviso' => null,
    'enlace' => null,
])

@php
    use App\Support\TextoSobreFondo;

    /*
     * "card" es la de bootstrap —fondo blanco, borde y sombra— y "br-8" es la del
     * tema, que pone el radio de las esquinas a ocho píxeles.
     *
     * Y NO se usa "widget-card", que fue lo primero que se puso: esa clase no existe.
     * Solo aparece en el nombre de unos ficheros scss del tema, y por eso se
     * encuentra uno buscando el texto en el archivo y no como clase. Con ella la
     * tarjeta salía sin fondo ni bordes y el panel se veía como un formulario
     * suelto, sin que nada dijera que faltaba el estilo.
     *
     * Y el radio lo pone "br-8" y no "rounded-3", porque el del tema manda con un
     * "!important" y "rounded-3" se perdería siempre. El resto de las pantallas del
     * proyecto usan "br-8" para lo mismo.
     */
    $coloreada = $hex !== null && $hex !== '';

    $letra = $coloreada
        ? (new TextoSobreFondo($hex))->color()
        : null;

    /*
     * Para la pastilla del aviso y para el círculo del icono hace falta el color en
     * canal alfa, y no hay una clase para eso —se comprobó y "bg-opacity-25" no
     * existe en este tema—, así que se monta el rgba aquí.
     *
     * El círculo del icono lleva blanco translúcido y no el color de la letra,
     * porque el icono va en el color de la letra sobre blanco translúcido, y con el
     * contrario el icono se perdería dentro del círculo. Antes el círculo iba del
     * mismo color que la tarjeta, que es un contraste de 1,00 exacto: no se veía
     * para nada.
     */
    $rgbLetra = $letra === null
        ? '107, 114, 128'
        : implode(', ', array_map('intval', sscanf($letra, '#%2x%2x%2x')));

    $estilo = $coloreada ? 'color: ' . $letra . ';' : '';
@endphp

<{{ $enlace ? 'a' : 'div' }} @if ($enlace) href="{{ $enlace }}" @endif
    class="card br-8 mb-3 {{ $color }} {{ $enlace ? 'text-reset' : '' }}">

    <div class="card-body p-3">

        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">

            <h6 class="mb-0 text-uppercase fw-semibold"
                style="font-size: .72rem; letter-spacing: .04em; {{ $estilo }}">
                {{ $titulo }}
            </h6>

            <span class="avatar avatar-sm rounded-circle d-inline-flex align-items-center
                         justify-content-center flex-shrink-0"
                  style="background-color: {{ $coloreada ? 'rgba(255, 255, 255, .22)' : 'rgba(107, 114, 128, .12)' }};">
                <i class="bi {{ $icono }}" style="font-size: .7rem; {{ $estilo }}"></i>
            </span>

        </div>

        <h3 class="mb-0 fw-bold lh-1"
            style="font-size: 1.5rem; overflow-wrap: anywhere; {{ $estilo }}">
            {{ $valor }}
        </h3>

        @if ($pie)
            <p class="mb-0 mt-2" style="font-size: .75rem; line-height: 1.35; {{ $estilo }}">
                {{ $pie }}
            </p>
        @endif

        @if ($aviso)
            {{--
                El aviso va en una pastilla y no en un color distinto. Antes iba en
                ámbar sobre un fondo rojo, que es ámbar sobre rojo: 2,25 sobre 1, y
                era el peor par del panel. Aquí la pastilla es el propio color de la
                letra con una fondo de su mismo color al 16 %, así que se distingue
                del pie por la caja y no por el tono, y no depende de que haya un
                color que contraste con el fondo.
            --}}
            <p class="mb-0 mt-2 rounded-1 px-2 py-1"
               style="font-size: .72rem; line-height: 1.35; {{ $estilo }}
                      background-color: rgba({{ $rgbLetra }}, .16);
                      border: 1px solid rgba({{ $rgbLetra }}, .28);">
                {{ $aviso }}
            </p>
        @endif

    </div>

</{{ $enlace ? 'a' : 'div' }}>

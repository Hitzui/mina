/**
 * Select2
 * ========================================================================
 * Mejora los <select> nativos: buscador, etiquetas y lista desplegable
 * en lugar del combo del sistema operativo.
 *
 * Uso en cualquier vista:
 *
 *   <select class="form-select select2" name="pais">...</select>
 *
 * O, para activarlo en bloque:
 *
 *   <select class="form-select" data-select2>...</select>
 *
 * Para pasarle opciones adicionales sin repetir codigo:
 *
 *   <select class="form-select select2"
 *           data-select2-opciones='{"placeholder":"Buscar...","allowClear":true}'>
 *
 * Select2 se engancha al jQuery que ya carga el layout (window.jQuery).
 * Si se hiciera "import $ from 'jquery'" se traeria una segunda copia de
 * jQuery y los manejadores de eventos de la pagina dejarian de funcionar.
 */

import select2Factory from 'select2';

const $ = window.jQuery;

if ($) {
    select2Factory(window, $);
}

/**
 * Opciones por defecto, en español.
 */
const opcionesBase = {
    theme: 'default',
    width: '100%',
    /*
     * Select2 esconde el campo de busqueda cuando hay menos resultados
     * que este numero, y por defecto son 8. Como casi todos los combos de
     * la aplicacion tienen menos (tipos de pago, unidades, etapas), el
     * buscador no aparecia nunca. Con 0 se muestra siempre; el buscador
     * no molesta cuando hay pocas opciones y ayuda cuando hay muchas.
     */
    minimumResultsForSearch: 0,
    language: {
        noResults: () => 'Sin resultados',
        searching: () => 'Buscando...',
        inputTooShort: () => 'Escriba al menos 1 carácter',
        errorLoading: () => 'No se pudieron cargar los resultados',
        inputTooLong: () => 'Quite algunos caracteres',
        selectionRemove: (i) => `¿Quitar ${i.data.name}?`,
        selectionAdd: () => 'Agregar',
        loading: () => 'Cargando...'
    }
};

/**
 * Lee la configuracion extra declarada en data-select2-opciones.
 *
 * Ojo: jQuery convierte por su cuenta los data-attributes que parecen
 * JSON, asi que .data() devuelve un objeto, no el texto. Si se le pasa
 * un objeto a JSON.parse() explota con "[object Object]". Por eso aqui
 * se aceptan las dos formas.
 */
function opcionesDe($select) {
    const extra = $select.data('select2Opciones');

    if (!extra) {
        return { ...opcionesBase };
    }

    // jQuery ya lo interpreto
    if (typeof extra === 'object') {
        return { ...opcionesBase, ...extra };
    }

    // Viene como texto: hay que interpretarlo
    if (typeof extra === 'string') {
        try {
            return { ...opcionesBase, ...JSON.parse(extra) };
        } catch (e) {
            console.error('Select2: data-select2-opciones no es JSON valido', e);

            return { ...opcionesBase };
        }
    }

    return { ...opcionesBase };
}

/**
 * Refleja en el contenedor visible el estado de validacion del select
 * real, que Select2 oculta pero Laravel sigue marcando con is-invalid.
 */
function sincronizarValidacion($select) {
    const $contenedor = $select.next('.select2-container');

    if (!$contenedor.length) {
        return;
    }

    $contenedor.toggleClass('is-invalid-container', $select.hasClass('is-invalid'));
}

/**
 * Si el combo vive dentro de un modal, el desplegable se cuelga del modal
 * y no del body.
 *
 * Por defecto Select2 lo cuelga de document.body, o sea fuera del modal.
 * Bootstrap deja .modal en position:fixed cubriendo toda la pantalla y
 * .modal-dialog con pointer-events:none. El desplegable suelto en el body
 * mide su sitio contra la ventana pero se pinta contra el documento: con
 * la pagina scrolleada el desplegable aparece desplazado y el raton
 * apunta a otro elemento. El buscador se ve, pero no recibe el foco y no
 * se puede escribir en el.
 *
 * Colgandolo del modal, la posicion que mide y la que se pintan usan el
 * mismo origen.
 */
function dropdownParentDe($select) {
    const $modal = $select.closest('.modal');

    return $modal.length ? $modal : $('body');
}

export function iniciarSelect2(contexto = document) {
    if (!$ || !$.fn.select2) {
        return;
    }

    $(contexto).find('select.select2, select[data-select2]').each(function () {
        const $select = $(this);

        // Hay que destruirlo antes de volverlo a inicializar: llamar
        // select2() dos veces sobre el mismo nodo rompe el desplegable.
        if ($select.data('select2') !== undefined) {
            $select.select2('destroy');
        }

        const opciones = opcionesDe($select);

        // Si la vista ya indico un dropdownParent, manda el de la vista.
        if (opciones.dropdownParent === undefined) {
            opciones.dropdownParent = dropdownParentDe($select);
        }

        $select.select2(opciones);

        sincronizarValidacion($select);

        $select.on('change', () => sincronizarValidacion($select));
    });
}

$(function () {
    iniciarSelect2();
});

// Disponible para inicializar sobre contenido cargado despues
window.iniciarSelect2 = iniciarSelect2;

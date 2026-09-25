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
 */
function opcionesDe($select) {
    const extra = $select.data('select2Opciones');

    if (!extra) {
        return { ...opcionesBase };
    }

    try {
        return { ...opcionesBase, ...JSON.parse(extra) };
    } catch (e) {
        console.error('Select2: data-select2-opciones no es JSON valido', e);

        return { ...opcionesBase };
    }
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

        $select.select2(opcionesDe($select));

        sincronizarValidacion($select);

        $select.on('change', () => sincronizarValidacion($select));
    });
}

$(function () {
    iniciarSelect2();
});

// Disponible para inicializar sobre contenido cargado despues
window.iniciarSelect2 = iniciarSelect2;

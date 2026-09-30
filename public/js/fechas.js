/**
 * El calendario de los campos de fecha, en todas las pantallas.
 *
 * El navegador trae un calendario en los campos type="date", pero es el del
 * navegador: enseña la fecha en el orden y el formato que tenga el sistema,
 * que no es el mismo en todos, y en algunos telefonos es incomodo de usar.
 * Con flatpickr la fecha se ve siempre como d/m/Y, que es como se escribe
 * aqui, y el calendario es el mismo en todas partes.
 *
 * ESTE ARCHIVO ES EL QUE HACE EL TRABAJO, Y ESTA EN TODAS LAS PAGINAS.
 *
 * Antes cada pantalla llamaba a flatpickr desde su propio javascript, y eso
 * tenia dos fallos que se ven enseguida al mirar el resultado:
 *
 *  - Las pantallas nuevas se olvidaban. De las que tienen campo de fecha, solo
 *    cuatro lo tenían, y no por decision sino por descuido: la de los
 *    empleados, la de los equipos, la del almacén y las dos nuevas de
 *    configuración no lo tenían porque nadie se acordaba de escribir la
 *    llamada. Un calendario que hay que acordarse de poner es un calendario
 *    que se acaba olvidando otra vez.
 *
 *  - Y no había dónde mirar para saber si un campo lo tenía o no. Ahora se
 *    sabe: este archivo engancha los type="date" y los type="datetime-local"
 *    de toda la pagina, sin preguntar.
 *
 * ASI QUE SE USA ASI:
 *
 * Un campo de fecha se escribe como type="date" y se queda asi. No hay que
 * llamar a flatpickr ni a escribir nada mas: este archivo lo encuentra.
 *
 * Y cuando una pantalla quiere una configuracion que no es la de por
 * defecto —un calendario que no deje escribir a mano, o uno con un minimo que
 * no sea el de hoy— entonces si que lo llama por su cuenta, y marca el
 * campo con data-calendario="propio" para que este archivo lo salte. Ese
 * atributo es lo que evita el fallo mas gordo de todo esto, que explico mas
 * abajo.
 *
 * POR QUE HAY QUE MARCAR LOS PROPIOS, Y NO DEJAR QUE ESTE LOS ENCUENTRE.
 *
 * Flatpickr no se puede llamar dos veces sobre el mismo campo: la segunda vez
 * le pone un segundo campo de texto delante del primero y el usuario acaba
 * escribiendo la fecha en el de atras, en el que no se ve. Es un fallo que
 * no da ningun error —la pagina funciona, el calendario se abre— y que solo
 * se ve mirando la pantalla.
 *
 * Se podria intentar que este archivo lo notara y se apartara, pero no
 * funciona: hay pantallas que montan su calendario al abrir el modal, no al
 * cargar la pagina, y cuando este archivo se ejecuta el campo todavia esta
 * limpio. Este se aparta, y el calendario de la pantalla se monta despues
 * sobre un campo que ya tiene otro. Con el atributo en el campo la coisa es
 * explicita: aqui no se toca, lo monta quien corresponda.
 */
(function ($) {
    'use strict';

    $(function () {
        /*
         * Sin flatpickr no se hace nada, y no es un caso raro: el layout lo
         * carga siempre, pero si un dia falta, un fallo aqui seria una
         * excepcion en todas las pantallas a la vez. Con esta linea, en ese
         * caso lo unico que pasa es que no hay calendario y los campos
         * siguen siendo campos de fecha de verdad, que es lo que eran antes
         * de este archivo.
         */
        if (typeof flatpickr === 'undefined') {
            return;
        }

        /*
         * Los mismos ajustes que usan las pantallas que ya lo tenian, para que
         * un campo con calendario se vea igual en todas partes.
         *
         * dateFormat es lo que va al servidor y altFormat es lo que se ve. No
         * se pueden cambiar: si el value fuera d/m/Y, la base no lo entenderia
         * al guardar, y se guardaria con el dia y el mes cambiados sin que
         * nada lo notara.
         *
         * allowInput deja escribir a mano. Sin el, corregir un dia de papel
         * obligaria a buscarlo en el calendario, y en una fecha de hace tres
         * años eso son quince flechas hacia atras.
         */
        const SOLO_FECHA = {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            locale: 'es',
            allowInput: true,
        };

        /*
         * Los campos que llevan hora y no solo dia.
         *
         * El salto de cinco minutos es el mismo que usa el calendario de las
         * horas de los trabajos de empleado, y no es capricho: en un taller
         * las horas se miran de cinco en cinco, y un calendario de minuto
         * obliga a mover la rueda para poder escribir las 08:15.
         */
        const CON_HORA = {
            dateFormat: 'Y-m-d H:i',
            altInput: true,
            altFormat: 'd/m/Y H:i',
            locale: 'es',
            allowInput: true,
            enableTime: true,
            time_24hr: true,
            minuteIncrement: 5,
        };

        /**
         * Monta el calendario en unos campos.
         */
        function montar(campos, configuracion) {
            Array.from(campos).forEach(function (campo) {
                // Los que lleva su propia pantalla. Ver el comentario de
                // arriba: aqui no se tocan.
                if (campo.dataset.calendario === 'propio') {
                    return;
                }

                // Por si acaso. Un campo con calendario ya montado tiene esto
                // dentro, y montarlo otra vez le pondria un segundo campo de
                // texto delante.
                if (campo._flatpickr) {
                    return;
                }

                // Los que no se escriben no llevan calendario. Un calendario
                // que no se puede usar es ruido, y hay campos que se enseñan
                // deshabilitados para que se vea el valor sin poder cambiarlo.
                if (campo.disabled || campo.readOnly) {
                    return;
                }

                flatpickr(campo, configuracion);
            });
        }

        montar(document.querySelectorAll('input[type="date"]'), SOLO_FECHA);
        montar(document.querySelectorAll('input[type="datetime-local"]'), CON_HORA);
    });
})(jQuery);

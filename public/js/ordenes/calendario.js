/**
 * Calendario de ordenes de trabajo.
 *
 * Los eventos los pide el servidor por tramo de fechas, en vez de llevar
 * todas las ordenes en la pagina: al pulsar "mes siguiente" el navegador
 * pide las de ese mes y no hay que recargar nada. Con los eventos metidos
 * en el HTML, el calendario se veria vacio al cambiar de mes.
 *
 * Al pulsar una orden se abre el resumen en un modal, con un enlace a su
 * ficha. El resumen se llena con lo que el servidor mando en el propio
 * evento, incluido el enlace, para que el javascript no tenga que saber
 * construir urls de la orden.
 */
(function () {
    'use strict';

    const ELEMENTO_CALENDARIO = 'calendar';
    const ELEMENTO_MODAL = 'modalOrdenCalendario';

    function alIniciar() {
        const calendario = document.getElementById(ELEMENTO_CALENDARIO);
        const elementoModal = document.getElementById(ELEMENTO_MODAL);

        if (!calendario) {
            return;
        }

        const modal = elementoModal && window.bootstrap
            ? new bootstrap.Modal(elementoModal)
            : null;

        const calendarioInstancia = new FullCalendar.Calendar(calendario, {
            /*
             * El mes entero, que es donde se leen de un vistazo cuando hay
             * muchas ordenes.
             *
             * Sin initialDate se abre en el mes de hoy, que es lo que se
             * espera. El theme lo traia clavado en el dia 7 del mes en
             * curso, que era cosa de su ejemplo, no de la aplicacion.
             */
            initialView: 'dayGridMonth',

            locale: 'es',
            buttonText: {
                today: 'Hoy',
                month: 'Mes',
                week: 'Semana',
                day: 'Día',
                list: 'Agenda',
            },

            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
            },

            /*
             * La url viene en el elemento, no se escribe aqui: asi el
             * javascript no tiene la ruta de la orden metida dentro, y
             * cambiar de url es cambiar un atributo en la vista.
             */
            events: {
                url: calendario.dataset.eventosUrl,
                method: 'GET',
            },

            /*
             * Cuando un mes tiene muchas ordenes, listarlas todas deja el
             * calendario ilegible. Con esto se muestran las primeras y el
             * resto queda en un "+N mas" que se abre al pulsarlo.
             */
            dayMaxEvents: 3,

            // Que no se fije el alto de la pantalla: en un movil es mejor
            // que la tabla crezca hacia abajo
            height: 'auto',

            eventClick: function (info) {
                info.jsEvent.preventDefault();
                mostrarResumen(modal, info.event);
            },

            // El raton encima da el detalle sin tener que abrir el modal
            eventDidMount: function (info) {
                const p = info.event.extendedProps || {};

                info.el.setAttribute(
                    'title',
                    [
                        p.cliente,
                        p.estado,
                        p.peso,
                    ].filter(Boolean).join(' · ')
                );
            },
        });

        calendarioInstancia.render();
    }

    /**
     * Llena el modal con el resumen de la orden y lo abre.
     */
    function mostrarResumen(modal, evento) {
        if (!modal) {
            return;
        }

        const d = (evento && evento.extendedProps) || {};

        texto('calCodigo', d.codigo || (evento ? evento.title : '') || '—');
        texto('calCliente', d.cliente || '—');
        texto('calFecha', d.fecha || '—');
        texto('calPeso', d.peso || '—');
        texto('calProcesos', d.procesos === undefined ? '—' : d.procesos);
        texto('calDescripcion', d.descripcion || 'Sin descripción');

        // El estado va pintado con el color del evento, para que el modal
        // y el calendario digan lo mismo
        const estado = document.getElementById('calEstado');

        if (estado) {
            estado.innerHTML = '';

            if (d.estado) {
                const insignia = document.createElement('span');
                insignia.className = 'badge fs-6';
                insignia.style.backgroundColor = d.estado_color || '#888ea8';
                insignia.style.color = '#ffffff';
                insignia.textContent = d.estado;
                estado.appendChild(insignia);
            } else {
                estado.textContent = '—';
            }
        }

        const irAVer = document.getElementById('calIrAVer');

        if (irAVer) {
            // El href va en el evento, no se arma aqui: el javascript no
            // necesita saber como son las urls de la orden
            irAVer.setAttribute('href', d.url || '#');
        }

        modal.show();
    }

    function texto(id, valor) {
        const elemento = document.getElementById(id);

        if (elemento) {
            elemento.textContent = valor;
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', alIniciar);
    } else {
        alIniciar();
    }
})();

/**
 * Confirmacion de eliminacion con SweetAlert.
 *
 * Los botones de eliminar son enlaces con data-confirm-delete. Este
 * script intercepta el clic, pide confirmacion y, al aceptarla, envia
 * un DELETE real con el token CSRF. Sin esto el enlace haria un GET a
 * una ruta que solo admite DELETE y el servidor responderia 405.
 *
 * Uso en cualquier boton o enlace:
 *
 *   <a href="{{ route('x.destroy', $id) }}"
 *      data-confirm-delete
 *      data-confirm-title="¿Eliminar?"
 *      data-confirm-text="No se puede deshacer."
 *      data-confirm-button="Sí, eliminar">
 *
 * Se carga una sola vez en components/base-layout.blade.php, despues
 * de @sweetAlert, asi que esta disponible en todas las paginas.
 */
(function () {
    'use strict';

    function obtenerTokenCsrf() {
        if (typeof window.CSRF_TOKEN === 'string') {
            return window.CSRF_TOKEN;
        }

        const meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? meta.getAttribute('content') : '';
    }

    function sweetAlertApi() {
        if (typeof window.Swal !== 'undefined') {
            return window.Swal;
        }

        if (typeof window.sweetAlert !== 'undefined') {
            return window.sweetAlert;
        }

        return null;
    }

    function enviarDelete(url) {
        const token = obtenerTokenCsrf();

        const formulario = document.createElement('form');

        formulario.method = 'POST';
        formulario.action = url;
        formulario.style.display = 'none';

        // Laravel no acepta DELETE nativo en formularios HTML
        const metodo = document.createElement('input');
        metodo.type = 'hidden';
        metodo.name = '_method';
        metodo.value = 'DELETE';
        formulario.appendChild(metodo);

        if (token) {
            const campoToken = document.createElement('input');
            campoToken.type = 'hidden';
            campoToken.name = '_token';
            campoToken.value = token;
            formulario.appendChild(campoToken);
        }

        document.body.appendChild(formulario);
        formulario.submit();
    }

    document.addEventListener('click', function (evento) {
        const disparador = evento.target.closest('[data-confirm-delete]');

        if (!disparador) {
            return;
        }

        const url = disparador.getAttribute('href') || disparador.dataset.url;

        if (!url) {
            return;
        }

        // Siempre contra el servidor, nunca con el enlace
        evento.preventDefault();

        const swal = sweetAlertApi();

        // Si SweetAlert no esta disponible, se borra igual pero se avisa
        if (!swal) {
            if (window.confirm('¿Deseas eliminar este registro? Esta acción no se puede deshacer.')) {
                enviarDelete(url);
            }
            return;
        }

        swal.fire({
            title: disparador.dataset.confirmTitle || '¿Eliminar?',
            text: disparador.dataset.confirmText
                || 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: disparador.dataset.confirmButton || 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d63939',
            cancelButtonColor: '#adb5bd',
            focusCancel: true,
            reverseButtons: true
        }).then(function (resultado) {
            if (resultado.isConfirmed) {
                enviarDelete(url);
            }
        });
    });
})();

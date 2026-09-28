{{--
    Modal del alta y la edicion de un tipo de cambio.

    Es el mismo formulario para las dos cosas: el boton de guardar decide a
    donde se envia, y el javascript pone el action y el method segun si se
    esta creando o editando. El texto del boton cambia para que se sepa en que
    modo se esta, porque los dos se abren desde la misma lista y seria facil
    editar por error en lugar de crear.

    La url de editar lleva __ID__: el javascript lo sustituye por el id del
    tipo de cambio que se esta editando, asi que el mismo modal sirve para
    todos sin regenerar la pagina.

    El selector de moneda lleva buscador. En un taller con dos o tres monedas
    no haria falta, pero la lista de monedas crece con el tiempo y es el
    mismo select que hay en el resto de formularios: mantenerlo igual en
    todas partes vale mas que ahorrar cuatro lineas aqui.
--}}
<div class="modal fade" id="modalTipoCambio" tabindex="-1" aria-labelledby="modalTipoCambioLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <form id="formTipoCambio"
                  method="POST"
                  action="{{ route('configuracion.tipos_cambio.store') }}"
                  data-store-url="{{ route('configuracion.tipos_cambio.store') }}"
                  data-update-url="{{ route('configuracion.tipos_cambio.update', '__ID__') }}">

                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTipoCambioLabel">Nuevo tipo de cambio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">

                    @include('configuracion.tipos_cambio._form', ['tipoCambio' => null])

                    <div id="tipoCambioError" class="alert alert-danger mt-3 d-none"></div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>

                    <button type="submit" class="btn btn-primary" id="btnGuardarTipoCambio">
                        <i class="bi bi-save me-1"></i>
                        <span id="textoGuardarTipoCambio">Guardar tipo de cambio</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

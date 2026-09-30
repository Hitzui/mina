{{--
    Modal del alta y la edicion de una caja.

    Es el mismo formulario para las dos cosas: el boton de guardar decide a donde
    se envia, y el javascript pone el action y el method segun si se esta
    creando o editando. El texto del boton cambia para que se sepa en que modo
    se esta, porque los dos se abren desde la misma lista y seria facil editar
    por error en lugar de crear.

    La url de editar lleva __ID__: el javascript lo sustituye por el id de la
    caja que se esta editando, asi que el mismo modal sirve para todas sin
    regenerar la pagina.

    Y el interruptor de activa es el que mas cuidado pide, asi que lleva el
    texto de al lado que explica que hace. Sin ese texto, "activa" parece que
    quiere decir "el taller la usa ahora mismo", que no es lo mismo: una caja
    inactiva es una caja que sigue existiendo y se puede volver a usar, solo que
    no se ofrece al registrar cobros. Y esa distincion es la que separa
    desactivar de borrar.
--}}
<div class="modal fade" id="modalCaja" tabindex="-1" aria-labelledby="modalCajaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <form id="formCaja"
                  method="POST"
                  action="{{ route('configuracion.cajas.store') }}"
                  data-store-url="{{ route('configuracion.cajas.store') }}"
                  data-update-url="{{ route('configuracion.cajas.update', '__ID__') }}">

                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="modalCajaLabel">Nueva caja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">

                    @include('configuracion.cajas._form', ['caja' => null])

                    <div id="cajaError" class="alert alert-danger mt-3 d-none"></div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>

                    <button type="submit" class="btn btn-primary" id="btnGuardarCaja">
                        <i class="bi bi-save me-1"></i>
                        <span id="textoGuardarCaja">Guardar caja</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

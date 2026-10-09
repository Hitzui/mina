{{--
    Modal del alta y la edicion de un metodo de pago.

    Es el mismo formulario para las dos cosas: el boton de guardar decide a donde
    se envia, y el javascript pone el action y el method segun si se esta
    creando o editando. El texto del boton cambia para que se sepa en que modo
    se esta, porque los dos se abren desde la misma lista y seria facil editar
    por error en lugar de crear.

    La url de editar lleva __ID__: el javascript lo sustituye por el id del
    metodo de pago que se esta editando, asi que el mismo modal sirve para todos
    sin regenerar la pagina.

    Y el interruptor de activo es el que mas cuidado pide, asi que lleva el
    texto de al lado que explica que hace. Sin ese texto, "activo" parece que
    quiere decir "el taller lo acepta ahora mismo", que no es lo mismo: un metodo
    inactivo es un metodo que sigue existiendo y se puede volver a usar, solo que
    no se ofrece al registrar cobros. Y esa distincion es la que separa
    desactivar de borrar.
--}}
<div class="modal fade" id="modalMetodoPago" tabindex="-1" aria-labelledby="modalMetodoPagoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <form id="formMetodoPago"
                  method="POST"
                  action="{{ route('configuracion.metodos_pago.store') }}"
                  data-store-url="{{ route('configuracion.metodos_pago.store') }}"
                  data-update-url="{{ route('configuracion.metodos_pago.update', '__ID__') }}">

                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="modalMetodoPagoLabel">Nuevo método de pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">

                    @include('configuracion.metodos_pago._form', ['metodoPago' => null])

                    <div id="metodoPagoError" class="alert alert-danger mt-3 d-none"></div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>

                    <button type="submit" class="btn btn-primary" id="btnGuardarMetodoPago">
                        <i class="bi bi-save me-1"></i>
                        <span id="textoGuardarMetodoPago">Guardar método</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

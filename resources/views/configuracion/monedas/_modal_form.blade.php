{{--
    Modal del alta y la edicion de una moneda.

    Es el mismo formulario para las dos cosas: el boton de guardar decide a
    donde se envia, y el javascript pone el action y el method segun si se
    esta creando o editando. El texto del boton cambia para que se sepa en que
    modo se esta, porque los dos se abren desde la misma lista y seria facil
    editar por error en lugar de crear.

    La url de editar lleva __ID__: el javascript lo sustituye por el id de la
    moneda que se esta editando, asi que el mismo modal sirve para todas sin
    regenerar la pagina.

    El boton de guardar se desactiva solo mientras va, como en el resto de
    modales. Con cinco campos y un indice unico, pulsar dos veces seguidas
   volver a guardar dos veces, y la segunda revienta con un error de base de
    datos que no le dice a nadie de que se trata.
--}}
<div class="modal fade" id="modalMoneda" tabindex="-1" aria-labelledby="modalMonedaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <form id="formMoneda"
                  method="POST"
                  action="{{ route('configuracion.monedas.store') }}"
                  data-store-url="{{ route('configuracion.monedas.store') }}"
                  data-update-url="{{ route('configuracion.monedas.update', '__ID__') }}">

                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="modalMonedaLabel">Nueva moneda</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">

                    @include('configuracion.monedas._form', ['moneda' => null])

                    <div id="monedaError" class="alert alert-danger mt-3 d-none"></div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>

                    <button type="submit" class="btn btn-primary" id="btnGuardarMoneda">
                        <i class="bi bi-save me-1"></i>
                        <span id="textoGuardarMoneda">Guardar moneda</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{--
    Modal del alta y la edicion de una valoracion.

    Es el mismo formulario para las dos cosas: el boton de guardar decide a donde
    se envia, y el javascript pone el action y el method segun si se esta
    creando o editando. El texto del boton cambia para que se sepa en que modo
    se esta, porque los dos se abren desde la misma lista y seria facil editar
    por error en lugar de crear.

    La url de editar lleva __ID__: el javascript lo sustituye por el id de la
    valoracion que se esta editando, asi que el mismo modal sirve para todas sin
    regenerar la pagina.

    Y dentro no hay un campo de valor. No por descuido: lo que se guarda es lo que
    calcula el servidor con la serie de precios, y un campo de valor a mano
    acabaria teniendo dos cifras distintas para los mismos gramos —la del
    documento y la de la cuenta— sin que ninguna de las dos avisara. En su lugar
    el modal pregunta cuanto sale antes de dejar guardarlo, y lo enseña entero.
--}}
<div class="modal fade" id="modalValoracion" tabindex="-1" aria-labelledby="modalValoracionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-white">
            <form id="formValoracion"
                  method="POST"
                  action="{{ route('procesos.valoraciones_oro.store') }}"
                  data-store-url="{{ route('procesos.valoraciones_oro.store') }}"
                  data-update-url="{{ route('procesos.valoraciones_oro.update', '__ID__') }}"
                  data-calcular-url="{{ route('procesos.valoraciones_oro.calcular') }}">

                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="modalValoracionLabel">Nueva valoración de oro</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">

                    @include('procesos.valoraciones_oro._form', ['valoracion' => null])

                    <div id="valoracionError" class="alert alert-danger mt-3 d-none"></div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>

                    <button type="submit" class="btn btn-primary" id="btnGuardarValoracion">
                        <i class="bi bi-save me-1"></i>
                        <span id="textoGuardarValoracion">Guardar valoración</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

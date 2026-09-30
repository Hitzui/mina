{{--
    Modal del alta y la edicion de una recuperacion.

    Es el mismo formulario para las dos cosas: el boton de guardar decide a
    donde se envia, y el javascript pone el action y el method segun si se
    esta creando o editando. El texto del boton cambia para que se sepa en que
    modo se esta, porque los dos se abren desde la misma lista y seria facil
    editar por error en lugar de crear.

    La url de editar lleva __ID__: el javascript lo sustituye por el id de la
    recuperacion que se esta editando, asi que el mismo modal sirve para todas
    sin regenerar la pagina.

    El error de orden cerrada sale pegado al selector de la orden, que es donde
    el usuario lo ha elegido, y no suelto en un bloque de arriba. Es el mismo
    texto que usan las otras seis pantallas que cuelgan de una orden, y sale de
    ahi y no de aqui: el aviso tiene que decir lo mismo este y alli, porque si
    difieren no se sabe cual de los dos es el bueno.
--}}
<div class="modal fade" id="modalRecuperacion" tabindex="-1" aria-labelledby="modalRecuperacionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <form id="formRecuperacion"
                  method="POST"
                  action="{{ route('procesos.recuperaciones.store') }}"
                  data-store-url="{{ route('procesos.recuperaciones.store') }}"
                  data-update-url="{{ route('procesos.recuperaciones.update', '__ID__') }}">

                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="modalRecuperacionLabel">Nueva recuperación de oro</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">

                    @include('procesos.recuperaciones._form', ['recuperacion' => null])

                    <div id="recuperacionError" class="alert alert-danger mt-3 d-none"></div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>

                    <button type="submit" class="btn btn-primary" id="btnGuardarRecuperacion">
                        <i class="bi bi-save me-1"></i>
                        <span id="textoGuardarRecuperacion">Guardar recuperación</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

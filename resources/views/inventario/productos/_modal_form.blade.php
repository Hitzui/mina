{{--
    Modal del alta y la edicion de un material.

    Es el mismo formulario para las dos cosas: el boton de guardar decide a
    donde se envia, y el javascript se encarga de poner el action y el
    method segun si se esta creando o editando. El texto del boton cambia
    para que se sepa en que modo se esta.

    Las urls llevan __ID__ en la de editar: el javascript lo sustituye por el
    id del material que se esta editando, asi que el mismo modal sirve para
    todos sin que haya que regenerar la pagina.
--}}
<div class="modal fade" id="modalProducto" tabindex="-1" aria-labelledby="modalProductoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <form id="formProducto"
                  method="POST"
                  action="{{ route('inventario.productos.store') }}"
                  data-store-url="{{ route('inventario.productos.store') }}"
                  data-update-url="{{ route('inventario.productos.update', '__ID__') }}">

                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="modalProductoLabel">Nuevo material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">

                    @include('inventario.productos._form')

                    {{-- Los errores del servidor, para cuando el alta viene de otra pantalla --}}
                    <div id="productoError" class="alert alert-danger mt-3 d-none"></div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>

                    <button type="submit" class="btn btn-primary" id="btnGuardarProducto">
                        <i class="bi bi-save me-1"></i>
                        <span id="textoGuardarProducto">Crear material</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

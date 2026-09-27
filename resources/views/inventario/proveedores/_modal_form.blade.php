{{--
    Modal del alta y la edicion de un proveedor.

    Es el mismo formulario para las dos cosas: el boton de guardar decide a
    donde se envia, y el javascript pone el action y el method segun si se
    esta creando o editando. El texto del boton cambia para que se sepa en que
    modo se esta.

    La url de editar lleva __ID__: el javascript lo sustituye por el id del
    proveedor que se esta editando, asi que el mismo modal sirve para todos
    sin regenerar la pagina.
--}}
<div class="modal fade" id="modalProveedor" tabindex="-1" aria-labelledby="modalProveedorLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <form id="formProveedor"
                  method="POST"
                  action="{{ route('inventario.proveedores.store') }}"
                  data-store-url="{{ route('inventario.proveedores.store') }}"
                  data-update-url="{{ route('inventario.proveedores.update', '__ID__') }}">

                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="modalProveedorLabel">Nuevo proveedor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">

                    @include('inventario.proveedores._form')

                    <div id="proveedorError" class="alert alert-danger mt-3 d-none"></div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>

                    <button type="submit" class="btn btn-primary" id="btnGuardarProveedor">
                        <i class="bi bi-save me-1"></i>
                        <span id="textoGuardarProveedor">Crear proveedor</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

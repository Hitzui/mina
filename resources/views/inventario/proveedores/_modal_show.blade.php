{{--
    Ficha del proveedor, en modal.

    Los valores los rellena el javascript con lo que devuelve la ficha en
    JSON. Los ids llevan el prefijo "show" para no chocar con los del
    formulario de edicion, que esta en la misma pagina y usa los mismos
    nombres de campo.
--}}
<div class="modal fade" id="modalShowProveedor" tabindex="-1" aria-labelledby="modalShowProveedorLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">

            <div class="modal-header">
                <h5 class="modal-title" id="modalShowProveedorLabel">Ficha del proveedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">

                <div class="row g-3">

                    <div class="col-md-8">
                        <label class="form-label text-muted">Proveedor</label>
                        <div class="form-control bg-light fw-semibold" id="showNombre">—</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Código</label>
                        <div class="form-control bg-light font-monospace" id="showCodigo">—</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Persona de contacto</label>
                        <div class="form-control bg-light" id="showContacto">—</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Teléfono</label>
                        <div class="form-control bg-light" id="showTelefono">—</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Correo</label>
                        <div class="form-control bg-light" id="showEmail">—</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Estado</label>
                        <div class="pt-2" id="showEstado">—</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Compras registradas</label>
                        <div class="form-control bg-light" id="showCompras">—</div>
                    </div>

                    <div class="col-12" id="showAvisoContenedor"></div>

                    <div class="col-12">
                        <label class="form-label text-muted">Dirección</label>
                        <div class="form-control bg-light" id="showDireccion">—</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label text-muted">Observaciones</label>
                        <div class="form-control bg-light" id="showObservaciones">—</div>
                    </div>

                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
            </div>

        </div>
    </div>
</div>

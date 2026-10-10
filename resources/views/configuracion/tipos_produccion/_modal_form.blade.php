<div class="modal fade" id="modalTipoProduccion" tabindex="-1" aria-labelledby="modalTipoProduccionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <form id="formTipoProduccion" method="POST"
                  action="{{ route('configuracion.tipos_produccion.store') }}"
                  data-store-url="{{ route('configuracion.tipos_produccion.store') }}"
                  data-update-url="{{ route('configuracion.tipos_produccion.update', '__ID__') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTipoProduccionLabel">Nuevo tipo de producción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="tipoProduccionNombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input id="tipoProduccionNombre" name="nombre" class="form-control" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-5 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input type="checkbox" class="form-check-input" id="tipoProduccionEstado" checked>
                                <label class="form-check-label" for="tipoProduccionEstado">Activo</label>
                                <input type="hidden" name="estado" value="1" id="tipoProduccionEstadoValor">
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="tipoProduccionDescripcion" class="form-label">Descripción</label>
                            <input id="tipoProduccionDescripcion" name="descripcion" class="form-control" maxlength="255">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div id="tipoProduccionError" class="alert alert-danger mt-3 d-none"></div>
                    <div class="alert alert-light border mt-3 mb-0 small">
                        Un tipo usado en producciones no se puede borrar. Desactívelo para retirarlo de las nuevas producciones sin alterar el historial.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarTipoProduccion">Guardar tipo</button>
                </div>
            </form>
        </div>
    </div>
</div>

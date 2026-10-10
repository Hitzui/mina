<div class="modal fade" id="modalTipoParticipacion" tabindex="-1" aria-labelledby="modalTipoParticipacionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <form id="formTipoParticipacion" method="POST"
                  action="{{ route('configuracion.tipos_participacion.store') }}"
                  data-store-url="{{ route('configuracion.tipos_participacion.store') }}"
                  data-update-url="{{ route('configuracion.tipos_participacion.update', '__ID__') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTipoParticipacionLabel">Nuevo tipo de participación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="tipoParticipacionNombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input id="tipoParticipacionNombre" name="nombre" class="form-control" maxlength="100" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-5 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input type="checkbox" class="form-check-input" id="tipoParticipacionEstado" checked>
                                <label class="form-check-label" for="tipoParticipacionEstado">Activo</label>
                                <input type="hidden" name="estado" value="1" id="tipoParticipacionEstadoValor">
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="tipoParticipacionDescripcion" class="form-label">Descripción</label>
                            <input id="tipoParticipacionDescripcion" name="descripcion" class="form-control" maxlength="255">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div id="tipoParticipacionError" class="alert alert-danger mt-3 d-none"></div>
                    <div class="alert alert-light border mt-3 mb-0 small">
                        Un tipo asociado a acuerdos no se puede borrar. Desactívelo para retirarlo de nuevos acuerdos sin alterar el historial.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarTipoParticipacion">Guardar tipo</button>
                </div>
            </form>
        </div>
    </div>
</div>

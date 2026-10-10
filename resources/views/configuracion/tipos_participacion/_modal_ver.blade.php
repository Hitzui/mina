<div class="modal fade" id="modalVerTipoParticipacion" tabindex="-1" aria-labelledby="modalVerTipoParticipacionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <div class="modal-header">
                <h5 class="modal-title" id="modalVerTipoParticipacionLabel">Tipo de participación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="tipoParticipacionFichaError" class="alert alert-danger d-none"></div>
                <div class="row g-3">
                    <div class="col-md-7"><div class="small text-muted">Nombre</div><div class="fs-5" id="fichaTipoParticipacionNombre">—</div></div>
                    <div class="col-md-5"><div class="small text-muted">Estado</div><div id="fichaTipoParticipacionEstado">—</div></div>
                    <div class="col-12"><div class="small text-muted">Descripción</div><div id="fichaTipoParticipacionDescripcion">—</div></div>
                </div>
                <hr>
                <div class="text-muted small mb-2">Uso registrado</div>
                <div id="fichaTipoParticipacionUsos"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                @can('configuracion.tipos_participacion.edit')
                    <button type="button" class="btn btn-primary" id="btnEditarTipoParticipacion"><i class="bi bi-pencil me-1"></i>Editar</button>
                @endcan
            </div>
        </div>
    </div>
</div>

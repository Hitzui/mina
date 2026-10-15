{{--
    Detalle de un uso de equipo. El js lo llena con lo que devuelve la
    ruta show en JSON.
--}}
<div class="modal fade" id="modalShowUsoEquipo" tabindex="-1" aria-labelledby="modalShowUsoEquipoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <div class="modal-header">
                <h5 class="modal-title" id="modalShowUsoEquipoLabel">Uso del equipo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label text-muted">Equipo</label>
                        <input type="text" id="showUsoEquipo" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Código</label>
                        <input type="text" id="showUsoCodigo" class="form-control font-monospace" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Proceso</label>
                        <input type="text" id="showUsoProceso" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Días de uso</label>
                        <input type="text" id="showUsoDias" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Inicio</label>
                        <input type="text" id="showUsoInicio" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Fin</label>
                        <input type="text" id="showUsoFin" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Depreciación por día</label>
                        <input type="text" id="showUsoTasa" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Depreciación del periodo</label>
                        <input type="text" id="showUsoDepreciacion" class="form-control fw-semibold" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Energía estimada (kWh)</label>
                        <input type="text" id="showUsoEnergia" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Costo de energía (NIO)</label>
                        <input type="text" id="showUsoCostoEnergia" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Costo total del uso (NIO)</label>
                        <input type="text" id="showUsoCostoTotal" class="form-control fw-semibold" value="" readonly>
                    </div>
                    <div class="col-md-6"><label class="form-label text-muted">Horas efectivas</label><input type="text" id="showUsoHoras" class="form-control" readonly></div>
                    <div class="col-md-6"><label class="form-label text-muted">Insumos consumidos (NIO)</label><input type="text" id="showUsoInsumos" class="form-control" readonly></div>
                    <div class="col-12 small text-muted" id="showUsoEstado"></div>

                    <div class="col-12">
                        <label class="form-label text-muted">Observaciones</label>
                        <textarea id="showUsoObservaciones" class="form-control" rows="2" readonly></textarea>
                    </div>

                    <div class="col-12">
                        <div class="alert alert-light border mb-0 small">
                            <i class="bi bi-info-circle me-1"></i>
                            Los costos y la tarifa reflejan los datos que se aplicaron al registrar el uso;
                            las asignaciones existentes conservan su historial.
                        </div>
                    </div>

                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

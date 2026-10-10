<div class="modal fade" id="modalLiquidacionOrden" tabindex="-1" aria-labelledby="modalLiquidacionOrdenLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <form id="formLiquidacionOrden" method="POST"
                  action="{{ route('procesos.ordenes_trabajo.liquidaciones.store', $ordenTrabajo) }}"
                  data-store-url="{{ route('procesos.ordenes_trabajo.liquidaciones.store', $ordenTrabajo) }}"
                  data-update-url="{{ route('procesos.ordenes_trabajo.liquidaciones.update', [$ordenTrabajo, '__ID__']) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalLiquidacionOrdenLabel">Nueva liquidación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="liquidacionFecha" class="form-label">Fecha <span class="text-danger">*</span></label>
                            <input type="date" id="liquidacionFecha" name="fecha" class="form-control" required value="{{ $fechaPorDefecto }}">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="liquidacionGramos" class="form-label">Gramos del lote <span class="text-danger">*</span></label>
                            <input type="number" id="liquidacionGramos" name="gramos_recuperados" class="form-control"
                                   min="0.0001" max="99999999.9999" step="0.0001" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-12 acuerdo-liquidacion-selector">
                            <label for="liquidacionAcuerdo" class="form-label">Acuerdo porcentual aplicado <span class="text-danger">*</span></label>
                            <select id="liquidacionAcuerdo" name="acuerdo_id" class="form-select" required>
                                <option value="">Seleccione un acuerdo</option>
                                @foreach($acuerdosLiquidables as $acuerdo)
                                    <option value="{{ $acuerdo->id }}"
                                            data-cliente="{{ $acuerdo->porcentaje_cliente }}"
                                            data-empresa="{{ $acuerdo->porcentaje_empresa }}">
                                        {{ $acuerdo->tipo_participacion?->nombre ?? 'Participación' }}
                                        ({{ number_format((float) $acuerdo->porcentaje_cliente, 4, '.', ',') }}% / {{ number_format((float) $acuerdo->porcentaje_empresa, 4, '.', ',') }}%)
                                    </option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                            @if($acuerdosLiquidables->isEmpty())
                                <div class="form-text text-danger">Agregue un acuerdo porcentual activo antes de liquidar; las tarifas no se aplican al reparto de gramos.</div>
                            @endif
                        </div>
                        <div class="col-12">
                            <div class="alert alert-light border mb-0">
                                <div class="small text-muted mb-1">Reparto calculado por el sistema</div>
                                <div id="liquidacionPorcentajesPreview">Seleccione un acuerdo porcentual.</div>
                                <div id="liquidacionGramosPreview" class="fw-semibold mt-1"></div>
                                <div id="liquidacionPorcentajesHistoricos" class="d-none"></div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="liquidacionObservaciones" class="form-label">Observaciones</label>
                            <textarea id="liquidacionObservaciones" name="observaciones" class="form-control" rows="3" maxlength="5000"></textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div id="liquidacionOrdenError" class="alert alert-danger mt-3 d-none"></div>
                    <div class="alert alert-light border mt-3 mb-0 small">
                        La liquidación guarda los porcentajes como fotografía del acuerdo. Corregir los gramos no modifica las condiciones pactadas.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarLiquidacion">Guardar liquidación</button>
                </div>
            </form>
        </div>
    </div>
</div>

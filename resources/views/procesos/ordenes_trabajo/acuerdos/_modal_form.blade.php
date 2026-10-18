<div class="modal fade" id="modalAcuerdoOrden" tabindex="-1" aria-labelledby="modalAcuerdoOrdenLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <form id="formAcuerdoOrden" method="POST"
                  action="{{ route('procesos.ordenes_trabajo.acuerdos.store', $ordenTrabajo) }}"
                  data-store-url="{{ route('procesos.ordenes_trabajo.acuerdos.store', $ordenTrabajo) }}"
                  data-update-url="{{ route('procesos.ordenes_trabajo.acuerdos.update', [$ordenTrabajo, '__ID__']) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAcuerdoOrdenLabel">Nuevo acuerdo de participación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="acuerdoTipo" class="form-label">Tipo de participación <span class="text-danger">*</span></label>
                            <select id="acuerdoTipo" name="tipo_participacion_id" class="form-select" required>
                                <option value="">Seleccione un tipo</option>
                                @foreach($tiposParticipacion as $tipo)
                                    <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-5 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input type="checkbox" class="form-check-input" id="acuerdoEstado" checked>
                                <label class="form-check-label" for="acuerdoEstado">Activo</label>
                                <input type="hidden" name="estado" value="1" id="acuerdoEstadoValor">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label d-block">Modalidad del acuerdo <span class="text-danger">*</span></label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="modalidad" id="acuerdoModalidadPorcentaje" value="porcentaje" checked>
                                <label class="form-check-label" for="acuerdoModalidadPorcentaje">Porcentajes de participación</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="modalidad" id="acuerdoModalidadTarifa" value="tarifa">
                                <label class="form-check-label" for="acuerdoModalidadTarifa">Tarifa de servicio</label>
                            </div>
                        </div>
                        <div class="col-md-6 campos-porcentaje-acuerdo">
                            <label for="acuerdoPorcentajeCliente" class="form-label">Porcentaje del cliente</label>
                            <div class="input-group">
                                <input id="acuerdoPorcentajeCliente" name="porcentaje_cliente" type="number" class="form-control"
                                       min="0" max="100" step="0.0001" placeholder="0.0000">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6 campos-porcentaje-acuerdo">
                            <label for="acuerdoPorcentajeEmpresa" class="form-label">Porcentaje de la empresa</label>
                            <div class="input-group">
                                <input id="acuerdoPorcentajeEmpresa" name="porcentaje_empresa" type="number" class="form-control"
                                       min="0" max="100" step="0.0001" placeholder="0.0000">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6 campos-tarifa-acuerdo d-none">
                            <label for="acuerdoTarifa" class="form-label">Tarifa de servicio</label>
                            <input id="acuerdoTarifa" name="tarifa_servicio" type="number" class="form-control" min="0.01" step="0.01">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6 campos-tarifa-acuerdo d-none">
                            <label for="acuerdoMoneda" class="form-label">Moneda <span class="text-danger">*</span></label>
                            <select id="acuerdoMoneda" name="moneda_id" class="form-select" required>
                                <option value="">Seleccione una moneda</option>
                                @foreach($monedas as $moneda)
                                    <option value="{{ $moneda->id }}">{{ $moneda->codigo }} — {{ $moneda->nombre }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <input type="hidden" name="moneda_id" id="acuerdoMonedaBase"
                               value="{{ $monedas->firstWhere('es_moneda_base', true)?->id ?? '' }}">
                        <div class="col-12">
                            <label for="acuerdoObservaciones" class="form-label">Observaciones</label>
                            <textarea id="acuerdoObservaciones" name="observaciones" class="form-control" rows="3" maxlength="5000"></textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-12 border-top pt-3">
                            <h6>Lama que queda después del servicio</h6>
                            <p class="small text-muted">Este acuerdo es independiente del reparto del oro original y de la tarifa. 1. Indique quién conserva el material. 2. Defina el reparto del oro que se recupere de esa lama. 3. Registre sus lotes desde «Lama y recuperación posterior».</p>
                            <label for="acuerdoLamaDestino" class="form-label">¿Quién conserva la lama?</label>
                            <select class="form-select" id="acuerdoLamaDestino" name="lama_destino"><option value="sin_definir">Todavía no se ha acordado</option><option value="empresa">La empresa conserva el material</option><option value="cliente">El cliente conserva el material</option></select>
                            <label for="acuerdoLamaEmpresa" class="form-label mt-2">Oro recuperado de la lama: porcentaje de la empresa</label>
                            <input class="form-control" id="acuerdoLamaEmpresa" name="lama_porcentaje_empresa" type="number" min="0" max="100" step="0.0001">
                            <p class="small text-muted mt-1">100 = todo para la empresa; 0 = todo para el cliente. El resto hasta 100% es del cliente. No se generan ingresos ni gramos al guardar el acuerdo. Los lotes ya registrados conservan sus condiciones originales.</p>
                        </div>
                    </div>
                    <div id="acuerdoOrdenError" class="alert alert-danger mt-3 d-none"></div>
                    <div class="alert alert-light border mt-3 mb-0 small">
                        En modalidad de porcentajes, cliente y empresa deben sumar 100% y el sistema conserva la moneda base. En tarifas se selecciona la moneda del servicio. Las modalidades son excluyentes.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarAcuerdoOrden">Guardar acuerdo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalProduccion" tabindex="-1" aria-labelledby="modalProduccionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <form id="formProduccion" method="POST"
                  action="{{ route('procesos.producciones.store') }}"
                  data-store-url="{{ route('procesos.producciones.store') }}"
                  data-update-url="{{ route('procesos.producciones.update', '__ID__') }}"
                  data-procesos-url="{{ route('procesos.producciones.procesos') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalProduccionLabel">Nueva producción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="produccionOrden" class="form-label">Orden de trabajo <span class="text-danger">*</span></label>
                            <select id="produccionOrden" name="orden_trabajo_id" class="form-select select2" required>
                                <option value="">Elija la orden</option>
                                @foreach ($ordenes as $orden)
                                    <option value="{{ $orden->id }}" data-cerrada="{{ $orden->estaCerrada() ? '1' : '0' }}">
                                        {{ $orden->codigo }} — {{ $orden->cliente?->nombre ?? 'sin cliente' }}
                                        @if ($orden->estaCerrada()) ({{ $orden->estadoTexto() }}) @endif
                                    </option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                            <div class="form-text">Las órdenes cerradas solo permiten corregir producciones existentes.</div>
                        </div>
                        <div class="col-md-5">
                            <label for="produccionFecha" class="form-label">Fecha <span class="text-danger">*</span></label>
                            <input id="produccionFecha" name="fecha" type="date" class="form-control" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-7">
                            <label for="produccionProceso" class="form-label">Proceso</label>
                            <select id="produccionProceso" name="proceso_orden_id" class="form-select select2" disabled>
                                <option value="">Producción de la orden completa</option>
                            </select>
                            <div class="invalid-feedback"></div>
                            <div class="form-text">Opcional. Si corresponde a una etapa concreta, elija el proceso de esa orden.</div>
                        </div>
                        <div class="col-md-5">
                            <label for="produccionTipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select id="produccionTipo" name="tipo_produccion_id" class="form-select select2" required>
                                <option value="">Elija el tipo</option>
                                @foreach ($tipos as $tipo)
                                    <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-5">
                            <label for="produccionCantidad" class="form-label">Cantidad <span class="text-danger">*</span></label>
                            <input id="produccionCantidad" name="cantidad" type="number" min="0" step="0.0001" class="form-control" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-7">
                            <label for="produccionUnidad" class="form-label">Unidad de medida <span class="text-danger">*</span></label>
                            <select id="produccionUnidad" name="unidad_medida_id" class="form-select select2" required>
                                <option value="">Elija la unidad</option>
                                @foreach ($unidadesMedida as $unidad)
                                    <option value="{{ $unidad->id }}">{{ $unidad->nombre }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-12">
                            <label for="produccionDescripcion" class="form-label">Descripción</label>
                            <input id="produccionDescripcion" name="descripcion" type="text" maxlength="255" class="form-control">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-12">
                            <label for="produccionObservaciones" class="form-label">Observaciones</label>
                            <textarea id="produccionObservaciones" name="observaciones" rows="3" maxlength="5000" class="form-control"></textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div id="produccionError" class="alert alert-danger mt-3 d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarProduccion">Guardar producción</button>
                </div>
            </form>
        </div>
    </div>
</div>

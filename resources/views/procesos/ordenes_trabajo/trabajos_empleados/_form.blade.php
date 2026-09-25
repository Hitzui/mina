<div class="row g-3">
    <input type="hidden" name="empleado_id" id="trabajoEmpleadoId">

    <div class="col-md-8">
        <label class="form-label">Empleado <span class="text-danger">*</span></label>
        <div class="input-group">
            <input type="text" id="trabajoEmpleadoNombre" class="form-control" placeholder="Seleccione un empleado" readonly>
            <button type="button" class="btn btn-outline-primary" id="btnSeleccionarEmpleado">
                <i class="bi bi-search"></i>
            </button>
        </div>
    </div>

    <div class="col-md-4">
        <label for="trabajoFecha" class="form-label">Fecha <span class="text-danger">*</span></label>
        <input type="date" name="fecha" id="trabajoFecha" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
    </div>

    <div class="col-md-6">
        <label for="trabajoTipoPago" class="form-label">Tipo de pago <span class="text-danger">*</span></label>
        <select name="tipo_pago_id" id="trabajoTipoPago" class="form-select" required disabled>
            <option value="">Seleccione...</option>
            @foreach($tiposPago as $tipoPago)
                <option value="{{ $tipoPago->id }}">{{ $tipoPago->nombre }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label for="trabajoProceso" class="form-label">Proceso</label>
        <select name="proceso_orden_id" id="trabajoProceso" class="form-select">
            <option value="">Trabajo general de la OT</option>
            @foreach($procesos as $proceso)
                <option value="{{ $proceso->id }}">
                    {{ $proceso->codigo }}{{ $proceso->etapa ? ' - '.$proceso->etapa->nombre : '' }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label for="trabajoHoraInicio" class="form-label">Hora inicio</label>
        <input type="time" name="hora_inicio" id="trabajoHoraInicio" class="form-control">
    </div>

    <div class="col-md-3">
        <label for="trabajoHoraFin" class="form-label">Hora fin</label>
        <input type="time" name="hora_fin" id="trabajoHoraFin" class="form-control">
    </div>

    <div class="col-md-3">
        <label for="trabajoCantidad" class="form-label">Cantidad <span class="text-danger">*</span></label>
        <input type="number" name="cantidad" id="trabajoCantidad" class="form-control" value="0" min="0" step="0.01" required>
    </div>

    <div class="col-md-3">
        <label for="trabajoUnidad" class="form-label">Unidad <span class="text-danger">*</span></label>
        <select name="unidad" id="trabajoUnidad" class="form-select" required>
            <option value="hora">Hora</option>
            <option value="día">Día</option>
            <option value="unidad">Unidad</option>
            <option value="tarea">Tarea</option>
        </select>
    </div>

    <div class="col-md-4">
        <label for="trabajoTarifa" class="form-label">Tarifa</label>
        <div class="input-group">
            <input type="number" id="trabajoTarifa" class="form-control" value="0" step="0.01" readonly>
            <span class="input-group-text" id="trabajoMoneda">—</span>
        </div>
        <small class="text-muted" id="trabajoVigencia"></small>
    </div>

    <div class="col-md-4">
        <label for="trabajoTipoCambio" class="form-label">Tipo de cambio</label>
        <input type="number" id="trabajoTipoCambio" class="form-control" value="1" step="0.0001" readonly>
    </div>

    <div class="col-md-4">
        <label for="trabajoTarifaNio" class="form-label">Tarifa NIO</label>
        <div class="input-group">
            <input type="number" id="trabajoTarifaNio" class="form-control" value="0" step="0.0001" readonly>
            <span class="input-group-text">NIO</span>
        </div>
    </div>

    <div class="col-md-4">
        <label for="trabajoTotal" class="form-label">Total</label>
        <input type="number" id="trabajoTotal" class="form-control fw-semibold" value="0" step="0.01" readonly>
    </div>

    <div class="col-md-4">
        <label for="trabajoTotalNio" class="form-label">Total NIO</label>
        <input type="number" id="trabajoTotalNio" class="form-control fw-semibold" value="0" step="0.01" readonly>
    </div>

    <div class="col-12">
        <label for="trabajoDescripcion" class="form-label">Descripción</label>
        <input type="text" name="descripcion" id="trabajoDescripcion" class="form-control" maxlength="255">
    </div>

    <div class="col-12">
        <label for="trabajoObservaciones" class="form-label">Observaciones</label>
        <textarea name="observaciones" id="trabajoObservaciones" class="form-control" rows="2"></textarea>
    </div>
</div>

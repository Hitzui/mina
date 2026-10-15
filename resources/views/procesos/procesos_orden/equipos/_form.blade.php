<div class="row g-3">

    {{-- Equipo --}}
    <div class="col-md-6">
        <label for="usoEquipo" class="form-label">
            Equipo <span class="text-danger">*</span>
        </label>

        <select name="equipo_id" id="usoEquipo" class="form-select select2" required>
            <option value="">Seleccione...</option>
            @foreach($equiposDisponibles as $disponible)
                <option value="{{ $disponible->id }}" data-codigo="{{ $disponible->codigo }}">
                    {{ $disponible->codigo }} — {{ $disponible->nombre }}
                </option>
            @endforeach
        </select>

        <small class="text-muted" id="usoEquipoAyuda">
            Solo equipos activos. Un equipo no puede trabajar en dos procesos
            a la vez: si el periodo se cruza con otro uso, no se guarda.
        </small>
    </div>

    {{--
        Inicio y fin llevan hora y minuto, no solo fecha: un molino puede
        pasar de un proceso a otro el mismo dia. Dejar el fin vacio
        significa que el equipo sigue asignado a este proceso.
    --}}
    <div class="col-md-3">
        <label for="usoFechaInicio" class="form-label">
            Inicio del uso <span class="text-danger">*</span>
        </label>

        <input
            type="datetime-local"
            name="fecha_inicio"
            id="usoFechaInicio"
            class="form-control"
            value="{{ $fechaInicioPorDefecto }}"
            required
        >
    </div>

    <div class="col-md-3">
        <label for="usoFechaFin" class="form-label">
            Fin del uso
        </label>

        <input
            type="datetime-local"
            name="fecha_fin"
            id="usoFechaFin"
            class="form-control"
        >

        <div class="form-check mt-1">
            <input
                class="form-check-input"
                type="checkbox"
                id="usoSigueAsignado"
            >
            <label class="form-check-label small" for="usoSigueAsignado">
                Sigue asignado
            </label>
        </div>
    </div>

    <div class="col-md-4">
        <label class="form-label" for="usoHorasEfectivas">Horas efectivas de trabajo</label>
        <input type="number" id="usoHorasEfectivas" name="horas_efectivas" class="form-control" min="0" max="999999" step="0.0001" placeholder="Pendiente de confirmar">
        <div class="form-text">Total trabajado, no horas asignadas. Puede completarlo después.</div>
    </div>
    <div class="col-md-4"><label class="form-label" for="usoHorometroInicial">Horómetro inicial (opcional)</label><input type="number" id="usoHorometroInicial" name="horometro_inicial" class="form-control" min="0" step="0.0001"></div>
    <div class="col-md-4"><label class="form-label" for="usoHorometroFinal">Horómetro final (opcional)</label><input type="number" id="usoHorometroFinal" name="horometro_final" class="form-control" min="0" step="0.0001"></div>
    <div class="col-12 small text-muted">Los recursos entregados, consumidos y devueltos se gestionan desde «Trabajo efectivo y consumos» en las acciones del equipo.</div>
    <div class="col-md-3">
        <label class="form-label text-muted">Días de uso</label>
        <input type="text" id="usoDias" class="form-control" value="—" readonly>
    </div>

    <div class="col-md-3">
        <label class="form-label text-muted">Depreciación por día</label>
        <input type="text" id="usoTasaDiaria" class="form-control" value="—" readonly>
    </div>

    <div class="col-md-3">
        <label class="form-label text-muted">Depreciación del periodo</label>
        <input type="text" id="usoDepreciacion" class="form-control fw-semibold" value="—" readonly>
    </div>

    <div class="col-md-3">
        <label class="form-label text-muted">Energía estimada (kWh)</label>
        <input type="text" id="usoEnergiaKwh" class="form-control" value="—" readonly>
    </div>

    <div class="col-md-3">
        <label class="form-label text-muted">Costo de energía (NIO)</label>
        <input type="text" id="usoCostoEnergia" class="form-control" value="—" readonly>
    </div>

    <div class="col-md-3">
        <label class="form-label text-muted">Costo total de uso (NIO)</label>
        <input type="text" id="usoCostoTotal" class="form-control fw-semibold" value="—" readonly>
    </div>
    <div class="col-12 small text-muted" id="usoEstadoCosteo"></div>

    <div class="col-md-3">
        <label class="form-label text-muted">Datos del equipo</label>
        <div class="input-group">
            <input
                type="text"
                id="usoCodigoEquipo"
                class="form-control font-monospace"
                value="—"
                readonly
                title="Código y valor de adquisición del equipo"
            >
            <span class="input-group-text" id="usoVidaUtil">—</span>
        </div>
    </div>

    {{-- Aviso de solapamiento o de equipo ya totalmente depreciado --}}
    <div class="col-12">
        <div class="alert d-none mb-0" id="usoAviso"></div>
    </div>

    <div class="col-12">
        <label for="usoObservaciones" class="form-label">Observaciones</label>
        <textarea
            name="observaciones"
            id="usoObservaciones"
            class="form-control"
            rows="2"
            maxlength="255"
        ></textarea>
    </div>
</div>

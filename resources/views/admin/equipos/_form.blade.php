@php
    /*
     * Este partial lo usan create (sin equipo) y edit (con equipo). Aqui
     * se deja definida la variable siempre: el operador ?-> evita el error
     * de leer una propiedad de null, pero sigue evaluando la variable, y
     * si no existe avisa igual.
     */
    $esEdicion = isset($equipo) && $equipo->exists;
    $equipo ??= null;
@endphp

<form
    action="{{ $esEdicion
        ? route('admin.equipos.update', $equipo)
        : route('admin.equipos.store') }}"
    method="POST"
    id="formEquipo"
>
    @csrf

    @if($esEdicion)
        @method('PUT')
    @endif

    <div class="row">

        {{-- Código --}}
        <div class="col-md-4 mb-3">
            <label for="codigo" class="form-label">
                Código <span class="text-danger">*</span>
            </label>

            <input
                type="text"
                name="codigo"
                id="codigo"
                class="form-control font-monospace @error('codigo') is-invalid @enderror"
                value="{{ old('codigo', $equipo->codigo ?? '') }}"
                maxlength="30"
                placeholder="MOL-001"
                required
            >

            @error('codigo')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror
        </div>

        {{-- Nombre --}}
        <div class="col-md-8 mb-3">
            <label for="nombre" class="form-label">
                Nombre <span class="text-danger">*</span>
            </label>

            <input
                type="text"
                name="nombre"
                id="nombre"
                class="form-control @error('nombre') is-invalid @enderror"
                value="{{ old('nombre', $equipo->nombre ?? '') }}"
                maxlength="150"
                placeholder="Molino de bolas"
                required
            >

            @error('nombre')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror
        </div>

        {{-- Descripción --}}
        <div class="col-12 mb-3">
            <label for="descripcion" class="form-label">
                Descripción
            </label>

            <textarea
                name="descripcion"
                id="descripcion"
                rows="3"
                class="form-control @error('descripcion') is-invalid @enderror"
                maxlength="255"
            >{{ old('descripcion', $equipo->descripcion ?? '') }}</textarea>

            @error('descripcion')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror
        </div>

        {{-- Fecha de adquisición --}}
        <div class="col-md-4 mb-3">
            <label for="fecha_adquisicion" class="form-label">
                Fecha de adquisición
            </label>

            <input
                type="date"
                name="fecha_adquisicion"
                id="fecha_adquisicion"
                class="form-control @error('fecha_adquisicion') is-invalid @enderror"
                value="{{ old('fecha_adquisicion', $equipo?->fecha_adquisicion?->format('Y-m-d') ?? '') }}"
            >

            @error('fecha_adquisicion')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror
        </div>

        {{-- Valor de adquisición --}}
        <div class="col-md-4 mb-3">
            <label for="valor_adquisicion" class="form-label">
                Valor de adquisición <span class="text-danger">*</span>
            </label>

            <input
                type="number"
                name="valor_adquisicion"
                id="valor_adquisicion"
                class="form-control @error('valor_adquisicion') is-invalid @enderror"
                value="{{ old('valor_adquisicion', $equipo->valor_adquisicion ?? '0.00') }}"
                min="0"
                step="0.01"
                required
            >

            @error('valor_adquisicion')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror
        </div>

        {{-- Valor residual --}}
        <div class="col-md-4 mb-3">
            <label for="valor_residual" class="form-label">
                Valor residual <span class="text-danger">*</span>
            </label>

            <input
                type="number"
                name="valor_residual"
                id="valor_residual"
                class="form-control @error('valor_residual') is-invalid @enderror"
                value="{{ old('valor_residual', $equipo->valor_residual ?? '0.00') }}"
                min="0"
                step="0.01"
                required
            >

            @error('valor_residual')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror
        </div>

        {{-- Vida útil --}}
        <div class="col-md-4 mb-3">
            <label for="vida_util_meses" class="form-label">
                Vida útil (meses) <span class="text-danger">*</span>
            </label>

            <input
                type="number"
                name="vida_util_meses"
                id="vida_util_meses"
                class="form-control @error('vida_util_meses') is-invalid @enderror"
                value="{{ old('vida_util_meses', $equipo->vida_util_meses ?? 60) }}"
                min="1"
                max="600"
                step="1"
                required
            >

            @error('vida_util_meses')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror
        </div>

        {{--
            La depreciación diaria no se guarda: se calcula con estos tres
            datos. Se muestra en vivo para que se vea qué va a costar el
            equipo por día antes de guardarlo.
        --}}
        <div class="col-md-8 mb-3">
            <label class="form-label text-muted">
                Depreciación diaria
            </label>

            <div class="form-control bg-light fw-semibold" id="depreciacionDiariaPreview">
                {{ number_format(
                    isset($equipo) ? $equipo->depreciacionDiaria() : 0,
                    4
                ) }}
            </div>

            <div class="form-text" id="depreciacionDiariaExplicacion">
                (valor de adquisición − valor residual) ÷ (vida útil × 30 días)
            </div>
        </div>

        {{-- Estado --}}
        <div class="col-md-4 mb-3">
            <label class="form-label d-block">
                Estado
            </label>

            <input type="hidden" name="estado" value="0">

            <div class="form-check form-switch">
                <input
                    type="checkbox"
                    name="estado"
                    id="estado"
                    value="1"
                    class="form-check-input @error('estado') is-invalid @enderror"
                    @checked(old('estado', $equipo->estado ?? true))
                >

                <label class="form-check-label" for="estado">
                    Activo
                </label>
            </div>

            @error('estado')
            <div class="text-danger small mt-1">
                {{ $message }}
            </div>
            @enderror
        </div>

    </div>

    <div class="mt-4 d-flex justify-content-end gap-2">
        <a
            href="{{ $esEdicion
                ? route('admin.equipos.show', $equipo)
                : route('admin.equipos.index') }}"
            class="btn btn-light"
        >
            <i class="bi bi-arrow-return-left me-1"></i> Volver
        </a>

        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-1"></i>
            {{ $esEdicion ? 'Actualizar Equipo' : 'Guardar Equipo' }}
        </button>
    </div>

</form>

{{--
    El calculo de la depreciacion vive en el modelo, pero aqui se
    previsualiza mientras se escribe. Se repiten los mismos 30 días por
    mes que usa Equipo::DIAS_POR_MES para no mostrar una cifra que luego
    sea otra.
--}}
<script>
    (function () {
        const form = document.getElementById('formEquipo');

        if (!form) {
            return;
        }

        const vAdquisicion = document.getElementById('valor_adquisicion');
        const vResidual = document.getElementById('valor_residual');
        const vMeses = document.getElementById('vida_util_meses');
        const preview = document.getElementById('depreciacionDiariaPreview');
        const explicacion = document.getElementById('depreciacionDiariaExplicacion');

        const DIAS_POR_MES = 30;

        function recalcular() {
            const a = parseFloat(vAdquisicion.value) || 0;
            const r = parseFloat(vResidual.value) || 0;
            const m = parseInt(vMeses.value, 10) || 0;

            let tasa = 0;

            if (m > 0 && (a - r) > 0) {
                tasa = (a - r) / (m * DIAS_POR_MES);
            }

            preview.textContent = tasa.toLocaleString('es-NI', {
                minimumFractionDigits: 4,
                maximumFractionDigits: 4
            });

            if (m > 0 && (a - r) > 0) {
                explicacion.innerHTML =
                    '(' + a.toLocaleString('es-NI') + ' − ' + r.toLocaleString('es-NI')
                    + ') ÷ (' + m + ' × ' + DIAS_POR_MES + ' días)';
            } else {
                explicacion.textContent = m <= 0
                    ? 'Indique una vida útil de al menos 1 mes para calcular la depreciación.'
                    : 'El valor residual no puede ser mayor que el valor de adquisición.';
            }
        }

        [vAdquisicion, vResidual, vMeses].forEach((campo) => {
            campo.addEventListener('input', recalcular);
        });

        recalcular();
    })();
</script>

@php
    $esEdicion = $modo === 'edit';
@endphp

<form
    id="formProcesoOrden"
    method="POST"
    action="{{ $esEdicion
        ? route(
            'procesos.ordenes_trabajo.procesos.update',
            [
                'ordenTrabajo' => $ordenTrabajo,
                'procesoOrden' => $procesoOrden,
            ]
        )
        : route(
            'procesos.ordenes_trabajo.procesos.store',
            $ordenTrabajo
        )
    }}"
    data-modo="{{ $modo }}"
>

    @csrf

    @if($esEdicion)
        @method('PUT')
    @endif


    {{-- ============================================================
         INFORMACIÓN DE LA ORDEN
    ============================================================= --}}

    <input
        type="hidden"
        name="orden_trabajo_id"
        value="{{ $ordenTrabajo->id }}"
    >


    <div class="row g-3">


        {{-- ========================================================
             CÓDIGO
        ========================================================= --}}

        <div class="col-md-4">

            <label class="form-label">
                Código
            </label>

            <input
                type="text"
                class="form-control"
                value="{{ $procesoOrden?->codigo ?? 'Se generará automáticamente' }}"
                readonly
            >

            @if(!$esEdicion)
                <div class="form-text">
                    El código será generado automáticamente.
                </div>
            @endif

        </div>


        {{-- ========================================================
             ORDEN DE TRABAJO
        ========================================================= --}}

        <div class="col-md-4">

            <label class="form-label">
                Orden de Trabajo
            </label>

            <input
                type="text"
                class="form-control"
                value="{{ $ordenTrabajo->codigo }}"
                readonly
            >

        </div>


        {{-- ========================================================             ESTADO        ========================================================= --}}        {{--            El estado no se escribe: sale de las fechas del proceso, que es            lo que de verdad se teclea. Antes habia un switch aqui con los            cuatro estados a mano y de solo lectura, y con la Cancelada en            el 4 cuando en la orden es el 0.            La unica marca manual es la de cancelado, y es necesaria: un            proceso abandonado tiene las mismas fechas que uno que se termino            a tiempo, asi que no hay forma de deducirlo de las fechas. Se            guarda en la columna estado con el 0, que es la unica marca que            el calculo respeta.        --}}        @php            $procesoParaEstado = $procesoOrden ?? new \App\Models\ProcesosOrden();            $canceladoActual = $esEdicion && $procesoOrden->estaCancelado();        @endphp        <div class="col-md-4">            <label class="form-label">                Estado            </label>            <div class="form-control bg-light d-flex align-items-center">                @if($esEdicion)                    {!! $procesoParaEstado->estadoEtiqueta() !!}                @else                    <span class="badge bg-secondary">Pendiente</span>                @endif            </div>            <div class="form-text">                Se deduce de las fechas del proceso: sin fecha de inicio es                pendiente, con ella y sin fecha de fin alcanzada esta en                proceso, y cuando la fecha de fin ya paso, finalizado.            </div>            <input type="hidden" name="cancelado" value="0">            <div class="form-check form-switch mt-2">                <input                    type="checkbox"                    class="form-check-input"                    id="cancelado"                    name="cancelado"                    value="1"                    @checked(old('cancelado', $canceladoActual ? '1' : null))                >                <label class="form-check-label" for="cancelado">                    Marcar como cancelado                </label>            </div>            <div class="form-text">                Para un proceso que se abandono a medio hacer. Un proceso                cancelado ya no admite trabajos, costos ni consumos, porque su                costo no significa nada.            </div>        </div>        {{-- ========================================================
             ETAPA
        ========================================================= --}}

        <div class="col-md-6">

            <label for="etapa_id" class="form-label">
                Etapa
                <span class="text-danger">*</span>
            </label>

            <select
                id="etapa_id"
                name="etapa_id"
                class="form-select select2 @error('etapa_id') is-invalid @enderror"
                data-select2-opciones='{"placeholder":"Buscar etapa...","allowClear":true}'
                required
            >

                <option value="">
                    Seleccione una etapa
                </option>

                @foreach($etapas as $etapa)

                    <option
                        value="{{ $etapa->id }}"
                        @selected(
                            old(
                                'etapa_id',
                                $procesoOrden?->etapa_id
                            ) == $etapa->id
                        )
                    >
                        {{ $etapa->orden }}. {{ $etapa->nombre }}
                    </option>

                @endforeach

            </select>

            @error('etapa_id')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror

        </div>


        {{-- ========================================================
             FECHA INICIO
        ========================================================= --}}

        <div class="col-md-3">

            <label for="fecha_inicio" class="form-label">
                Fecha de inicio
            </label>

            <input
                type="text"
                id="fecha_inicio"
                name="fecha_inicio"
                class="form-control @error('fecha_inicio') is-invalid @enderror"
                value="{{ old(
                    'fecha_inicio',
                    $procesoOrden?->fecha_inicio?->format('Y-m-d H:i')
                ) }}"
                autocomplete="off"
                placeholder="Seleccione fecha y hora"
            >

            @error('fecha_inicio')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror

        </div>


        {{-- ========================================================
             FECHA FIN
        ========================================================= --}}

        <div class="col-md-3">

            <label for="fecha_fin" class="form-label">
                Fecha de finalización
            </label>

            <input
                type="text"
                id="fecha_fin"
                name="fecha_fin"
                class="form-control @error('fecha_fin') is-invalid @enderror"
                value="{{ old(
                    'fecha_fin',
                    $procesoOrden?->fecha_fin?->format('Y-m-d H:i')
                ) }}"
                autocomplete="off"
                placeholder="Seleccione fecha y hora"
            >

            @error('fecha_fin')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror

        </div>


        {{-- ========================================================
             PESO ENTRADA
        ========================================================= --}}

        <div class="col-md-6">

            <label for="peso_entrada" class="form-label">
                Peso de entrada
            </label>

            <input
                type="number"
                id="peso_entrada"
                name="peso_entrada"
                class="form-control @error('peso_entrada') is-invalid @enderror"
                value="{{ old(
                    'peso_entrada',
                    $procesoOrden?->peso_entrada
                ) }}"
                min="0"
                step="0.0001"
                placeholder="0.0000"
            >

            @error('peso_entrada')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror

        </div>


        {{-- ========================================================
             PESO SALIDA
        ========================================================= --}}

        <div class="col-md-6">

            <label for="peso_salida" class="form-label">
                Peso de salida
            </label>

            <input
                type="number"
                id="peso_salida"
                name="peso_salida"
                class="form-control @error('peso_salida') is-invalid @enderror"
                value="{{ old(
                    'peso_salida',
                    $procesoOrden?->peso_salida
                ) }}"
                min="0"
                step="0.0001"
                placeholder="0.0000"
            >

            @error('peso_salida')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror

        </div>


        {{-- ========================================================
             OBSERVACIONES
        ========================================================= --}}

        <div class="col-12">

            <label for="observaciones" class="form-label">
                Observaciones
            </label>

            <textarea
                id="observaciones"
                name="observaciones"
                rows="4"
                class="form-control @error('observaciones') is-invalid @enderror"
                placeholder="Ingrese observaciones del proceso..."
            >{{ old(
                'observaciones',
                $procesoOrden?->observaciones
            ) }}</textarea>

            @error('observaciones')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror

        </div>

    </div>


    {{-- ============================================================
         BOTONES
    ============================================================= --}}

    <div class="d-flex justify-content-end gap-2 mt-4">

        <a
            href="{{ route(
                'procesos.ordenes_trabajo.show',
                $ordenTrabajo
            ) }}"
            class="btn btn-light"
        >
            <i class="bi bi-x me-1"></i>
            Cancelar
        </a>

        <button
            type="submit"
            class="btn btn-primary"
        >
            <i class="bi bi-save me-1"></i>

            {{ $esEdicion
                ? 'Actualizar proceso'
                : 'Guardar proceso'
            }}

        </button>

    </div>

</form>

@php
    $esEdicion = isset($ordenTrabajo) && $ordenTrabajo->exists;

    /*
     * Determina si la OT ya tiene operaciones/procesos registrados.
     *
     * Si tiene procesos:
     * - Cliente no editable
     * - Peso no editable
     * - Unidad no editable
     *
     * Descripción y fecha continúan siendo editables.
     */
    $tieneOperaciones = $esEdicion
        && $ordenTrabajo->procesos_ordenes()->exists();

    /*
     * Cliente actual.
     */
    $clienteId = old(
        'cliente_id',
        $esEdicion ? $ordenTrabajo->cliente_id : ''
    );

    $clienteNombre = old(
        'cliente_nombre',
        $esEdicion ? ($ordenTrabajo->cliente?->nombre ?? '') : ''
    );
@endphp

<form
    action="{{ $esEdicion
        ? route('procesos.ordenes_trabajo.update', $ordenTrabajo)
        : route('procesos.ordenes_trabajo.store') }}"
    method="POST"
    data-modo="{{ $esEdicion ? 'edit' : 'create' }}"
    data-tiene-operaciones="{{ $tieneOperaciones ? '1' : '0' }}"
    id="formOrdenTrabajo">

    @csrf

    @if($esEdicion)
        @method('PUT')
    @endif


    {{-- ============================================================
         INFORMACIÓN GENERAL
    ============================================================= --}}

    <div class="row">

        {{-- Código --}}
        <div class="col-md-4 mb-4">

            <label class="form-label">
                Código
            </label>

            <input
                type="text"
                class="form-control"
                value="{{ $esEdicion ? $ordenTrabajo->codigo : 'Se generará automáticamente' }}"
                readonly
            >

            @if(!$esEdicion)
                <div class="form-text">
                    El código será generado automáticamente al guardar.
                </div>
            @endif

        </div>


        {{-- Fecha --}}
        <div class="col-md-4 mb-4">

            <label for="fecha" class="form-label">
                Fecha <span class="text-danger">*</span>
            </label>

            <input
                type="text"
                class="form-control"
                id="fecha"
                name="fecha"
                value="{{ old(
                    'fecha',
                    $esEdicion && $ordenTrabajo->fecha
                        ? $ordenTrabajo->fecha->format('Y-m-d')
                        : now()->format('Y-m-d')
                ) }}"
                placeholder="Seleccione una fecha"
                autocomplete="off"
                required
            >

            @error('fecha')
            <div class="text-danger small mt-1">
                {{ $message }}
            </div>
            @enderror

        </div>


        {{-- Estado --}}
        <div class="col-md-4 mb-4">

            <label class="form-label">
                Estado
            </label>

            <div class="form-control bg-light">

                @if($esEdicion)

                    @switch($ordenTrabajo->estado)

                        @case(1)
                            <span class="badge bg-primary">
                                Pendiente
                            </span>
                            @break

                        @case(2)
                            <span class="badge bg-warning">
                                En proceso
                            </span>
                            @break

                        @case(3)
                            <span class="badge bg-success">
                                Finalizada
                            </span>
                            @break

                        @case(4)
                            <span class="badge bg-danger">
                                Cancelada
                            </span>
                            @break

                        @default
                            <span class="badge bg-secondary">
                                Desconocido
                            </span>

                    @endswitch

                @else

                    <span class="badge bg-primary">
                        Pendiente
                    </span>

                @endif

            </div>

            <div class="form-text">
                El estado se administra mediante el flujo de la orden.
            </div>

        </div>

    </div>


    {{-- ============================================================
         CLIENTE
    ============================================================= --}}

    <div class="row">

        <div class="col-md-8 mb-4">

            <label for="cliente_nombre" class="form-label">
                Cliente <span class="text-danger">*</span>
            </label>

            <div class="input-group">

                <input
                    type="text"
                    class="form-control"
                    id="cliente_nombre"
                    value="{{ $clienteNombre }}"
                    placeholder="Seleccione un cliente"
                    readonly
                    required
                >

                @if(!$tieneOperaciones)

                    <button
                        type="button"
                        class="btn btn-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#modalSeleccionarCliente">

                        <i class="fa-solid fa-search me-1"></i>
                        {{ $esEdicion ? 'Cambiar' : 'Seleccionar' }}

                    </button>

                @else

                    <span class="input-group-text">
                        <i class="fa-solid fa-lock"></i>
                    </span>

                @endif

            </div>

            {{-- ID real que será enviado al controlador --}}
            <input
                type="hidden"
                name="cliente_id"
                id="cliente_id"
                value="{{ $clienteId }}"
            >

            @if($tieneOperaciones)

                <div class="form-text">
                    El cliente no puede modificarse porque la orden
                    ya tiene operaciones registradas.
                </div>

            @else

                <div class="form-text">
                    Seleccione el cliente mediante el buscador.
                </div>

            @endif

            @error('cliente_id')
            <div class="text-danger small mt-1">
                {{ $message }}
            </div>
            @enderror

        </div>

    </div>


    {{-- ============================================================
         PESO DEL MINERAL
    ============================================================= --}}

    <div class="row">

        <div class="col-md-6 mb-4">

            <label for="peso_mineral" class="form-label">
                Peso del mineral <span class="text-danger">*</span>
            </label>

            <input
                type="number"
                class="form-control"
                id="peso_mineral"
                name="peso_mineral"
                value="{{ old(
                    'peso_mineral',
                    $esEdicion ? $ordenTrabajo->peso_mineral : ''
                ) }}"
                min="0"
                step="0.0001"
                placeholder="Ingrese el peso"
                {{ $tieneOperaciones ? 'readonly' : '' }}
                required
            >

            @if($tieneOperaciones)
                <div class="form-text">
                    El peso no puede modificarse porque la orden
                    ya tiene operaciones registradas.
                </div>
            @endif

            @error('peso_mineral')
            <div class="text-danger small mt-1">
                {{ $message }}
            </div>
            @enderror

        </div>


        {{-- Unidad --}}
        <div class="col-md-6 mb-4">

            <label for="unidad_peso" class="form-label">
                Unidad de peso <span class="text-danger">*</span>
            </label>

            <select
                class="form-select"
                id="unidad_peso"
                name="unidad_peso"
                {{ $tieneOperaciones ? 'disabled' : '' }}
                required
            >

                <option value="">
                    Seleccione una unidad
                </option>

                <option
                    value="toneladas"
                    @selected(old(
                        'unidad_peso',
                        $esEdicion ? $ordenTrabajo->unidad_peso : ''
                    ) === 'toneladas')>
                    Toneladas
                </option>

                <option
                    value="kg"
                    @selected(old(
                        'unidad_peso',
                        $esEdicion ? $ordenTrabajo->unidad_peso : ''
                    ) === 'kg')>
                    Kilogramos
                </option>

                <option
                    value="gramos"
                    @selected(old(
                        'unidad_peso',
                        $esEdicion ? $ordenTrabajo->unidad_peso : ''
                    ) === 'gramos')>
                    Gramos
                </option>

            </select>

            {{-- Cuando está disabled no se envía.
                 Mandamos el valor mediante hidden. --}}
            @if($tieneOperaciones)

                <input
                    type="hidden"
                    name="unidad_peso"
                    value="{{ $ordenTrabajo->unidad_peso }}"
                >

                <div class="form-text">
                    La unidad no puede modificarse porque la orden
                    ya tiene operaciones registradas.
                </div>

            @endif

            @error('unidad_peso')
            <div class="text-danger small mt-1">
                {{ $message }}
            </div>
            @enderror

        </div>

    </div>


    {{-- ============================================================
         DESCRIPCIÓN
    ============================================================= --}}

    <div class="row">

        <div class="col-12 mb-4">

            <label for="descripcion" class="form-label">
                Descripción
            </label>

            <textarea
                class="form-control"
                id="descripcion"
                name="descripcion"
                rows="4"
                maxlength="1000"
                placeholder="Ingrese una descripción u observaciones de la orden de trabajo"
            >{{ old(
                'descripcion',
                $esEdicion ? $ordenTrabajo->descripcion : ''
            ) }}</textarea>

            @error('descripcion')
            <div class="text-danger small mt-1">
                {{ $message }}
            </div>
            @enderror

        </div>

    </div>


    {{-- ============================================================
         BOTONES
    ============================================================= --}}

    <div class="d-flex justify-content-end gap-2 mt-3">

        <a
            href="{{ $esEdicion
                ? route('procesos.ordenes_trabajo.show', $ordenTrabajo)
                : route('procesos.ordenes_trabajo.index') }}"
            class="btn btn-light">

            <i class="fa-solid fa-xmark me-1"></i>
            Cancelar

        </a>

        <button
            type="submit"
            class="btn btn-primary">

            <i class="fa-solid fa-floppy-disk me-1"></i>

            {{ $esEdicion ? 'Actualizar Orden' : 'Guardar Orden' }}

        </button>

    </div>

</form>


{{-- ================================================================
     MODAL DE SELECCIÓN DE CLIENTE
================================================================= --}}

@include('admin.clientes.partial._modal')

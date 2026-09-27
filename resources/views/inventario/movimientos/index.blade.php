<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Almacén' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    @php
        // Los botones del filtro. La palabra "Todos" va aparte porque es la
        // unica que no es un tipo de movimiento.
        $filtros = [
            '' => 'Todo',
            \App\Models\MovimientosInventario::TIPO_ENTRADA => 'Entradas',
            \App\Models\MovimientosInventario::TIPO_SALIDA => 'Salidas',
            \App\Models\MovimientosInventario::TIPO_AJUSTE_POSITIVO => 'Ajustes a favor',
            \App\Models\MovimientosInventario::TIPO_AJUSTE_NEGATIVO => 'Ajustes en contra',
        ];
    @endphp

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">

            <div class="widget-content widget-content-area br-8">

                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

                    <div>
                        <h4 class="mb-1">
                            <i class="bi bi-list-ul me-2"></i>
                            Almacén
                        </h4>

                        <p class="text-muted mb-0">
                            Todo lo que ha entrado y salido del almacén. Es el
                            historial: el saldo de cada material sale de aquí,
                            y el costo de un proceso se calcula con las salidas
                            que se le imputaron.
                        </p>
                    </div>

                    <div class="d-flex gap-2">
                        <a
                            href="{{ route('inventario.productos.index') }}"
                            class="btn btn-light"
                        >
                            <i class="bi bi-box-seam me-1"></i>
                            Materiales
                        </a>

                        <a
                            href="{{ route('inventario.movimientos.create') }}"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-plus me-1"></i>
                            Registrar entrada
                        </a>
                    </div>

                </div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach($filtros as $valor => $texto)
                        <a
                            href="{{ route('inventario.movimientos.index', array_filter(['tipo' => $valor])) }}"
                            class="btn btn-sm {{ ($tipo ?? '') === $valor ? 'btn-primary' : 'btn-light' }}"
                        >
                            {{ $texto }}
                        </a>
                    @endforeach
                </div>

                <div class="table-responsive">

                    {!! $dataTable->table([
                        'class' => 'table table-hover table-bordered w-100',
                    ]) !!}

                </div>

            </div>

        </div>

    </div>

    <x-slot:footerFiles>

        {!! $dataTable->scripts() !!}

    </x-slot>

</x-base-layout>

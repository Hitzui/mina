<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Materiales' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">

            <div class="widget-content widget-content-area br-8">

                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

                    <div>
                        <h4 class="mb-1">
                            <i class="bi bi-box-seam me-2"></i>
                            Materiales
                        </h4>

                        <p class="text-muted mb-0">
                            El catálogo del almacén. Aquí se da de alta el cemento,
                            los químicos y los reactivos; lo que hay de cada uno se
                            actualiza solo con cada entrada y cada consumo.
                        </p>
                    </div>

                    <div class="d-flex gap-2">
                        <a
                            href="{{ route('inventario.movimientos.index') }}"
                            class="btn btn-light"
                        >
                            <i class="bi bi-list-ul me-1"></i>
                            Ver el kardex
                        </a>

                        <a
                            href="{{ route('inventario.productos.create') }}"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-plus me-1"></i>
                            Nuevo Material
                        </a>
                    </div>

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

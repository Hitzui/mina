<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Categorías de Costos' }}
    </x-slot>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">

            <div class="widget-content widget-content-area br-8">

                {{-- Encabezado --}}
                <div class="d-flex justify-content-between align-items-center mb-4">

                    <div>
                        <h4 class="mb-1">
                            <i class="bi bi-tags me-2"></i>
                            Categorías de Costos
                        </h4>

                        <p class="text-muted mb-0">
                            Administración de las categorías utilizadas para clasificar los costos.
                        </p>
                    </div>

                    <div>
                        <a
                            href="{{ route('configuracion.categorias_costos.create') }}"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-plus me-1"></i>
                            Nueva Categoría
                        </a>
                    </div>

                </div>

                {{-- Tabla --}}
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

<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title ?? 'Tipos de producción' }}</x-slot:pageTitle>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles></x-slot:headerFiles>

    <div class="row layout-top-spacing">
        <div class="col-12">
            <div class="widget-content widget-content-area br-8">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1"><i class="bi bi-box-seam me-2"></i>Tipos de producción</h4>
                        <p class="text-muted mb-0">Catálogo de productos y resultados que se registran en producción.</p>
                    </div>
                    @can('configuracion.tipos_produccion.create')
                        <button type="button" class="btn btn-primary" id="btnNuevoTipoProduccion">
                            <i class="bi bi-plus me-1"></i>Nuevo tipo
                        </button>
                    @endcan
                </div>

                <div class="table-responsive">
                    {!! $dataTable->table(['class' => 'table table-hover table-bordered w-100']) !!}
                </div>
            </div>
        </div>
    </div>

    @include('configuracion.tipos_produccion._modal_form')
    @include('configuracion.tipos_produccion._modal_ver')

    <x-slot:footerFiles>
        {!! $dataTable->scripts() !!}
        <script src="{{ asset('js/configuracion/tipos_produccion.js') }}"></script>
    </x-slot>
</x-base-layout>

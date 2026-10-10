<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title ?? 'Producciones' }}</x-slot:pageTitle>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles>
        @vite(['resources/scss/light/plugins/select2/custom-select2.scss'])
    </x-slot:headerFiles>

    <div class="row layout-top-spacing">
        <div class="col-12">
            <div class="widget-content widget-content-area br-8">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1"><i class="bi bi-box-seam me-2"></i>Producciones</h4>
                        <p class="text-muted mb-0">Registro de productos y cantidades obtenidas por orden y proceso.</p>
                    </div>
                    @can('producciones.create')
                        <button type="button" class="btn btn-primary" id="btnNuevaProduccion">
                            <i class="bi bi-plus me-1"></i>Nueva producción
                        </button>
                    @endcan
                </div>

                <div class="table-responsive">
                    {!! $dataTable->table(['class' => 'table table-hover table-bordered w-100']) !!}
                </div>
            </div>
        </div>
    </div>

    @include('procesos.producciones._modal_form')

    <x-slot:footerFiles>
        {!! $dataTable->scripts() !!}
        @vite(['resources/assets/js/select2/select2-init.js'])
        <script src="{{ asset('js/procesos/producciones.js') }}"></script>
    </x-slot>
</x-base-layout>

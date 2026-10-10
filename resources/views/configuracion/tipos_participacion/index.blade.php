<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title ?? 'Tipos de participación' }}</x-slot:pageTitle>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles></x-slot:headerFiles>

    <div class="row layout-top-spacing">
        <div class="col-12">
            <div class="widget-content widget-content-area br-8">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1"><i class="bi bi-people me-2"></i>Tipos de participación</h4>
                        <p class="text-muted mb-0">Catálogo de modalidades de participación que pueden asociarse a los acuerdos de una orden.</p>
                    </div>
                    @can('configuracion.tipos_participacion.create')
                        <button type="button" class="btn btn-primary" id="btnNuevoTipoParticipacion">
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

    @include('configuracion.tipos_participacion._modal_form')
    @include('configuracion.tipos_participacion._modal_ver')

    <x-slot:footerFiles>
        {!! $dataTable->scripts() !!}
        <script src="{{ asset('js/configuracion/tipos_participacion.js') }}"></script>
    </x-slot>
</x-base-layout>

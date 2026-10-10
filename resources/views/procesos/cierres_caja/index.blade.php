<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title }}</x-slot:pageTitle>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles></x-slot>

    <div class="row layout-top-spacing">
        <div class="col-12">
            <div class="widget-content widget-content-area br-8">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1"><i class="bi bi-safe2 me-2"></i>Cierres de caja</h4>
                        <p class="text-muted mb-0">Compara los cobros finalizados con el dinero contado, por método de pago y moneda.</p>
                    </div>
                    <a href="{{ route('procesos.cierres_caja.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus me-1"></i>Nuevo cierre
                    </a>
                </div>
                <div class="table-responsive">
                    {!! $dataTable->table(['class' => 'table table-hover table-bordered w-100']) !!}
                </div>
            </div>
        </div>
    </div>

    <x-slot:footerFiles>{!! $dataTable->scripts() !!}</x-slot>
</x-base-layout>

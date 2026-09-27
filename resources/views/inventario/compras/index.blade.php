<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Compras' }}
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
                            <i class="bi bi-bag-plus me-2"></i>
                            Compras
                        </h4>

                        <p class="text-muted mb-0">
                            Lo que se le compra a cada proveedor. El material entra
                            al almacén solo cuando la compra queda
                            <strong>Finalizada</strong>; mientras esté pendiente,
                            es un papel.
                        </p>
                    </div>

                    <a
                        href="{{ route('inventario.compras.create') }}"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-plus me-1"></i>
                        Nueva Compra
                    </a>

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

<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Proveedores' }}
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
                            <i class="bi bi-truck me-2"></i>
                            Proveedores
                        </h4>

                        <p class="text-muted mb-0">
                            Las empresas y personas que venden al taller. El
                            código lo pone el sistema; lo demás se llena una vez
                            y ya está.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="btn btn-primary"
                        id="btnNuevoProveedor"
                    >
                        <i class="bi bi-plus me-1"></i>
                        Nuevo Proveedor
                    </button>

                </div>

                <div class="table-responsive">

                    {!! $dataTable->table([
                        'class' => 'table table-hover table-bordered w-100',
                    ]) !!}

                </div>

            </div>

        </div>

    </div>

    {{-- El alta, la edicion y la ficha van en modal, sobre la lista --}}
    @include('inventario.proveedores._modal_form')
    @include('inventario.proveedores._modal_show')

    <x-slot:footerFiles>

        {!! $dataTable->scripts() !!}

        <script src="{{ asset('js/inventario/proveedores.js') }}"></script>

    </x-slot>

</x-base-layout>

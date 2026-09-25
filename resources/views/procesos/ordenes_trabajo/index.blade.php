<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Órdenes de Trabajo' }}
    </x-slot>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>


    {{-- ============================================================
         ENCABEZADO
    ============================================================= --}}

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-sm-12">

            <div class="d-flex justify-content-between align-items-center mb-4">

                <div>

                    <h4 class="mb-1">
                        Órdenes de Trabajo
                    </h4>

                    <p class="text-muted mb-0">
                        Gestión y seguimiento de las órdenes de trabajo.
                    </p>

                </div>


                <div class="d-flex gap-2">

                    {{-- Calendario --}}
                    <a
                        href="{{ route('procesos.ordenes_trabajo.calendario') }}"
                        class="btn btn-outline-primary">

                        <i class="fa-solid fa-calendar-days me-1"></i>
                        Calendario

                    </a>


                    {{-- Nueva OT --}}
                    <a
                        href="{{ route('procesos.ordenes_trabajo.create') }}"
                        class="btn btn-primary">

                        <i class="fa-solid fa-plus me-1"></i>
                        Nueva Orden de Trabajo

                    </a>

                </div>

            </div>

        </div>

    </div>


    {{-- ============================================================
         DATATABLE
    ============================================================= --}}

    <div class="row">

        <div class="col-xl-12 col-lg-12 col-sm-12">

            <div class="card">

                <div class="card-body">

                    <div class="table-responsive">

                        {{ $dataTable->table() }}

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- ============================================================
         SCRIPTS
    ============================================================= --}}

    <x-slot:footerFiles>

        {{ $dataTable->scripts() }}

    </x-slot>

</x-base-layout>

<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Equipos' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="card">
        <div class="card-header">
            <i class="bi bi-gear-wide-connected me-1"></i>
            Administrar Equipos
        </div>

        <div class="card-body">

            <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
                <i class="bi bi-info-circle fs-5"></i>
                <div>
                    El valor de adquisición, el valor residual y la vida útil
                    determinan la depreciación diaria del equipo. Esa
                    depreciación se cobra a cada proceso donde se use el equipo,
                    según los días que estuvo asignado.
                </div>
            </div>

            <a href="{{ route('admin.equipos.create') }}" class="btn btn-outline-info">
                <i class="bi bi-plus-lg me-1"></i> Ingresar Nuevo Equipo
            </a>

            <p>&nbsp;</p>

            <div class="table-responsive">
                {{ $dataTable->table() }}
            </div>
        </div>
    </div>

    <x-slot:footerFiles>
        {{ $dataTable->scripts() }}
    </x-slot>

</x-base-layout>

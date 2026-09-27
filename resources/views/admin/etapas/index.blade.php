<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Sistema de Laboratorio' }}
    </x-slot>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles>
    </x-slot>

    <div class="card">
        <div class="card-header">Administrar Etapas de OT</div>
        <div class="card-body">
            <a href="{{ route('admin.etapas.create') }}" class="btn btn-outline-info">
                <i class="bi bi-person-plus"></i> Ingresar Nueva Etapa
            </a>
            <p>&nbsp;</p>
            <div class="table-responsive">
                {{ $dataTable->table() }}
            </div>
        </div>
    </div>

    <x-slot:footerFiles>
        {{ $dataTable->scripts() }}
        <script>

        </script>
    </x-slot>

</x-base-layout>

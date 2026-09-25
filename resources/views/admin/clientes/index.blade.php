<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Sistema de Laboratorio' }}
    </x-slot>

    <x-slot:headerFiles>
    </x-slot>

    <x-breadcrumb :items="$breadcrumbs"/>

    <div class="card">
        <div class="card-header">Administrar Clientes</div>
        <div class="card-body">
            <a href="{{ route('admin.clientes.create') }}" class="btn btn-outline-info">
                <i class="fa-solid fa-user-plus"></i> Ingresar Nuevo Cliente
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

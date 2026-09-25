<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Sistema de Laboratorio' }}
    </x-slot>

    <x-breadcrumb :items="$breadcrumbs"/>

    <div class="card">

        <div class="card-header">
            <h5 class="mb-0">
                Editar Cliente
            </h5>
        </div>

        <div class="card-body">

            <form
                action="{{ route('admin.clientes.update', $cliente) }}"
                method="POST"
            >
                @csrf
                @method('PUT')

                @include('admin.clientes._form')
            </form>

        </div>

    </div>

    <x-slot name="headerFiles">
    </x-slot>

    <x-slot name="footerFiles">
    </x-slot>

</x-base-layout>

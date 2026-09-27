<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Editar Material' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-8 col-lg-10 col-sm-12 layout-spacing">

            <div class="widget-content widget-content-area br-8">

                <div class="mb-4">
                    <h5 class="mb-1">
                        <i class="bi bi-pencil me-1"></i>
                        Editar Material
                    </h5>

                    <p class="text-muted mb-0">
                        {{ $producto->nombre }}. Lo que hay en almacén y su costo
                        promedio no se cambian aquí: los mueve cada movimiento
                        del kardex.
                    </p>
                </div>

                <form
                    method="POST"
                    action="{{ route('inventario.productos.update', $producto) }}"
                >
                    @csrf
                    @method('PUT')

                    @include('inventario.productos._form')
                </form>

            </div>

        </div>

    </div>

    <x-slot:footerFiles>
    </x-slot>

</x-base-layout>

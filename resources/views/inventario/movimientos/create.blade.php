<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Registrar Entrada de Material' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-8 col-lg-10 col-sm-12 layout-spacing">

            <div class="widget-content widget-content-area br-8">

                <div class="mb-4">
                    <h5 class="mb-1">
                        <i class="bi bi-box-arrow-in-down me-1"></i>
                        Registrar movimiento de material
                    </h5>

                    <p class="text-muted mb-0">
                        Aquí entra el material al almacén. Al entrar se mezclan
                        las existencias con lo nuevo y el costo promedio se
                        recalcula solo; al salir, el material se vale a ese
                        promedio, no al precio que se escriba aquí.
                    </p>
                </div>

                @if($productos->isEmpty())

                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Todavía no hay ningún material dado de alta.
                        <a href="{{ route('inventario.productos.create') }}">
                            Cree el primero
                        </a>
                        para poder registrar entradas.
                    </div>

                @else

                    <form
                        method="POST"
                        action="{{ route('inventario.movimientos.store') }}"
                    >
                        @csrf

                        @include('inventario.movimientos._form')
                    </form>

                @endif

            </div>

        </div>

    </div>

    <x-slot:footerFiles>
    </x-slot>

</x-base-layout>

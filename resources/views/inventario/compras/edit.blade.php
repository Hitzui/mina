{{--
    Pantalla de edicion de una compra.

    Comparte el formulario con el alta. Lo que cambia es que hay que avisar de
    dos cosas que solo importan al corregir una compra ya guardada:

    - Si esta finalizada, su material esta en el almacen, y guardar aqui
      deshace lo que habia y mete lo nuevo. Si el material ya se consumio en
      un proceso, el guardado se va a negar.
    - Si se le cambia el estado para sacarla de Finalizada, el material sale
      del almacen.
--}}
<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Editar compra' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-10 col-lg-12 col-sm-12 layout-spacing">

            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">
                        <i class="bi bi-pencil me-1"></i>
                        Editar {{ $compra->codigo }}
                    </h4>

                    <p class="text-muted mb-0">
                        Al guardar, el almacén se corrige: si la compra está
                        finalizada, su material entra con las líneas nuevas.
                    </p>
                </div>
            </div>

            @if($compra->esFinalizada())
                {{--
                    Se avisa de que esta compra esta en el almacen. Guardar
                    cambios deshace las entradas viejas y mete las nuevas, y
                    eso se nota en el kardex como un par de movimientos mas.
                    Conviene que quede dicho antes de que se pulse guardar, y
                    no solo despues, cuando ya se hizo.
                --}}
                <div class="alert alert-warning" role="alert">
                    <h6 class="alert-heading">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Esta compra está finalizada
                    </h6>
                    <p class="mb-0">
                        Su material ({{ $compra->detalles->count() }}
                        {{ $compra->detalles->count() === 1 ? 'línea' : 'líneas' }})
                        está en el almacén. Si cambias las cantidades, quitas
                        líneas o dejas la compra en otro estado, al guardar el
                        material sale y vuelve a entrar con lo que se guarde.
                        Si ese material ya se consumió en un proceso, el
                        guardado se negará y habrá que deshacer esos consumos
                        primero.
                    </p>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger" role="alert">
                    <h6 class="alert-heading">Hay algo que corregir</h6>
                    <ul class="mb-0">
                        @foreach($errors->all() as $mensaje)
                            <li>{{ $mensaje }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="widget-content widget-content-area br-8">

                @include('inventario.compras._form')

            </div>

        </div>

    </div>

    {{--
        El modal de elegir proveedor va fuera del formulario, a proposito: un
        formulario dentro de un modal dentro de un formulario es un formulario
        anidado, y el navegador no sabe a cual de los dos pertenece un campo.
    --}}
    @include('inventario.compras._modal_selector_proveedor')

    <x-slot:footerFiles>

        <script src="{{ asset('js/inventario/compras.js') }}"></script>

        {{--
            La tabla del selector va con los scripts del DataTable, y no
            dentro de la vista del modal: los scripts tienen que salir
            despues del modal en el html, y blade no tiene forma de
            decidir eso desde dentro del include.
        --}}
        {{ $selectorProveedor->html()->scripts() }}

        <script src="{{ asset('js/inventario/selector_proveedor.js') }}"></script>

    </x-slot>

</x-base-layout>

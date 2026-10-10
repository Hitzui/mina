{{--
    Pantalla de edicion de un cobro.

    El formulario va en _form, que es el mismo para alta y edicion: lo que cambia
    entre las dos son los botones y la accion, y duplicar la pantalla entera haria
    que corregir una cosa en un sitio se olvidara en el otro.

    Y aqui no hay ninguna restriccion por estado: un cobro Finalizada se puede
    editar igual. El dinero se entrego igual, y lo que se equivoco fue al apuntarlo
    —una linea con el ingreso equivocado, un importe mal puesto—; bloquearlo
    dejaria el error sin salida. Lo que si se bloquea es cambiar de estado a
    Finalizada con las lineas sin cuadrar, y eso lo comprueba el servidor.
--}}
<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Editar cobro' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
        @vite(['resources/scss/light/plugins/select2/custom-select2.scss'])
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-10 col-lg-12 col-sm-12 layout-spacing">

            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">
                        <i class="bi bi-pencil me-1"></i>
                        Editar {{ $cobro->codigo }}
                    </h4>

                    {{--
                        El estado de la cabecera, porque se esta corrigiendo un
                        documento que ya existe y hay que saber cual es. Y con su
                        color: un cobro cancelado y uno finalizado se editan igual,
                        pero no son lo mismo, y el texto solo no lo dice.
                    --}}
                    <p class="text-muted mb-0 d-flex align-items-center gap-2 flex-wrap">
                        <span>Cliente: <strong>{{ $cobro->cliente?->nombre ?? 'Cliente eliminado' }}</strong></span>
                        <span class="badge {{ $cobro->estadoClase() }}">{{ $cobro->estadoTexto() }}</span>
                        <span>Total actual: <strong>{{ number_format($cobro->total(), 2) }}</strong></span>
                    </p>
                </div>

                <a href="{{ route('procesos.cobros.show', $cobro) }}" class="btn btn-light">
                    <i class="bi bi-eye me-1"></i>
                    Ver el cobro
                </a>
            </div>

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

            {{--
                Y el aviso de que el cambio de estado tiene efecto, que es lo que
                no se ve mirando un desplegable.

                Poner el cobro en Finalizada es decir que ese dinero esta confirmado
                en la caja, y a partir de ahi los ingresos aparecen como cobrados.
                Cancelarlo hace lo contrario, y eso no lo deshace solo un cambio de
                estado: hay que volver a Finalizada y corregir las lineas.
            --}}
            @if ($cobro->estaCancelado())
                <div class="alert alert-warning" role="alert" style="font-size: .85rem;">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Este cobro está <strong>cancelado</strong>: sus
                    {{ $cobro->cantidad_lineas }} {{ $cobro->cantidad_lineas === 1 ? 'línea no cuenta' : 'líneas no cuentan' }}
                    como dinero cobrado. Si lo vuelve a poner en Pendiente o
                    Finalizada, volverán a contar.
                </div>
            @endif

            <div class="widget-content widget-content-area br-8">

                @include('procesos.cobros._form')

            </div>

        </div>

    </div>

    <x-slot:footerFiles>

        @vite(['resources/assets/js/select2/select2-init.js'])

        <script src="{{ asset('js/procesos/cobros.js') }}"></script>

    </x-slot>

</x-base-layout>
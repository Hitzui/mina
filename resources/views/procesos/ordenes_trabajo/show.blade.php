<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title ?? 'Información de Orden de Trabajo' }}</x-slot:pageTitle>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">
        <div class="col-xl-12 col-lg-12 col-sm-12">
            <div class="card">
                <div class="card-body">
                    @include('procesos.ordenes_trabajo._aviso_orden_cerrada')

                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <h4 class="mb-0">{{ $ordenTrabajo->codigo }}</h4>
                                {{--
                                    El estado sale del modelo y no de un
                                    switch aqui: el catalogo esta en
                                    OrdenesTrabajo y lo usan tambien el
                                    listado y el calendario, para que no
                                    se pinten de tres maneras distintas.
                                --}}
                                {!! $ordenTrabajo->estadoEtiqueta() !!}
                            </div>
                            <p class="text-muted mb-0">Información de la Orden de Trabajo</p>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('procesos.ordenes_trabajo.edit', $ordenTrabajo) }}" class="btn btn-primary">
                                <i class="bi bi-pencil-square me-1"></i> Editar
                            </a>
                            {{--
                                El boton va associado al formulario oculto con el
                                atributo HTML "form". La libreria de confirmacion
                                resuelve el borrado en este orden:

                                - si el elemento tiene href, arma un formulario y lo
                                  envia a esa url (patron de las filas del listado);
                                - si el elemento pertenece a un formulario, lo envia.

                                Con "form=" el boton pertenece al formulario aunque
                                este mas abajo en el DOM, asi que funciona el segundo
                                caso. Antes se usaba data-form-delete, que esa
                                libreria no soporta: sin href ni formulario la
                                libreria salia en silencio y el boton no hacia nada.
                            --}}
                            <button type="submit" form="formEliminarOrden" class="btn btn-danger">
                                <i class="bi bi-trash me-1"></i> Eliminar
                            </button>
                            <a href="{{ route('procesos.ordenes_trabajo.index') }}" class="btn btn-light">
                                <i class="bi bi-arrow-left me-1"></i> Volver
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-6 col-lg-6 col-md-6 mt-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="mb-4"><i class="bi bi-person me-2"></i>Cliente</h5>
                    <label class="text-muted small">Nombre</label>
                    <div class="fw-semibold">{{ $ordenTrabajo->cliente?->nombre ?? '-' }}</div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-lg-6 col-md-6 mt-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="mb-4"><i class="bi bi-file-text me-2"></i>Datos de la Orden</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Fecha</label>
                            <div class="fw-semibold">{{ $ordenTrabajo->fecha?->format('d/m/Y') ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Peso del mineral</label>
                            <div class="fw-semibold">
                                {{ number_format($ordenTrabajo->peso_mineral, 4, '.', ',') }}
                                {{ ucfirst($ordenTrabajo->unidad_peso) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($ordenTrabajo->descripcion)
        <div class="row">
            <div class="col-12 mt-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3"><i class="bi bi-text-left me-2"></i>Descripción</h5>
                        <div class="text-muted">{!! nl2br(e($ordenTrabajo->descripcion)) !!}</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-12 mt-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h5 class="mb-1"><i class="bi bi-gear me-2"></i>Procesos</h5>
                            <p class="text-muted mb-0">Procesos asociados a esta orden de trabajo.</p>
                        </div>
                        <a href="{{ route('procesos.ordenes_trabajo.procesos.create', $ordenTrabajo->id) }}"
                           class="btn btn-primary"
                           @disabled($ordenTrabajo->estaCerrada())
                           title="{{ $ordenTrabajo->estaCerrada() ? 'La orden está cerrada: no se pueden agregar procesos' : '' }}">
                            <i class="bi bi-plus me-1"></i> Nuevo proceso
                        </a>
                    </div>

                    <div class="table-responsive">
                        {{ $dataTable->html()->table() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{--
        Ingresos de la orden: el dinero que entra.

        VA ENCIMA DE LOS COSTOS, Y PORQUE.

        En una ficha de orden, lo primero que se pregunta es cuanto se le cobró al
        cliente, y lo segundo es cuanto costó. Aqui va al reves que en la base, que
        es donde esta lo que sale, porque el orden de la base no dice nada —es el
        orden en que se crearon las tablas— y el de la ficha lo dice todo: primero
        lo que entra, despues lo que sale.

        Y NO HAY UN TOTAL QUE DIGA LO QUE SOBRA. El pie de la tabla dice lo
        facturado en córdobas y nada mas. Restarlo de los costos daria un margen, y
        un margen aqui seria un numero sin sentido: en un taller de joyeria la mano
        de obra no se le cobra al cliente como un porcentaje del oro, y la cuenta
        que importa no sale de restar estas dos tablas. El que quiera ver el margen
        lo saca, y con las dos sumas a la vista puede hacerlo sin que el sistema le
        de un numero que luego hay que explicar.

        EL TOTAL SE CALCULA EN EL SERVIDOR, JUNTO CON LA PAGINA, y no en el pie
        de la tabla a mano. La tabla se pide por ajax y su pie se pinta en el
        navegador, que es donde no se puede sumar: los ingresos de una orden
        pueden estar en dólares y en córdobas, y para sumarlos haria falta el
        tipo de cambio de cada fila. Si se traen ya se ha hecho la cuenta
        entera. Con la cuenta hecha en el servidor, la suma sale con las mismas
        cifras que hay guardadas en las filas.

        Y CUENTA CUANTOS INGRESOS QUEDARON SIN EQUIVALENTE, que son los que no
        entran en la suma. Sin ese numero, un ingreso sin tipo de cambio parece
        no existir y la suma se lee como completa cuando no lo esta.
    --}}
    <div class="row">
        <div class="col-12 mt-4">
            <div class="card">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1">
                                <i class="bi bi-cash-coin me-2"></i>
                                Ingresos de la orden
                            </h5>
                            <p class="text-muted mb-0">
                                Lo que se le ha cobrado al cliente por esta orden.
                            </p>
                        </div>

                        <button type="button"
                                class="btn btn-primary"
                                id="btnNuevoIngreso"
                                @disabled($ordenTrabajo->estaCerrada())
                                title="{{ $ordenTrabajo->estaCerrada() ? 'La orden está cerrada: no se puede registrar un ingreso' : '' }}">
                            <i class="bi bi-plus-lg me-1"></i>
                            Registrar ingreso
                        </button>
                    </div>

                    <div class="alert alert-light border d-flex align-items-start gap-2">
                        <i class="bi bi-info-circle fs-5"></i>
                        <div>
                            Aquí va el servicio de procesamiento, la participación en
                            oro, la venta de oro: todo lo que entra por la orden. Los
                            gastos van en su propia tabla de abajo, y no se restan de
                            estos.
                        </div>
                    </div>

                    <div class="table-responsive">
                        {{ $ingresosDataTable->html()->table() }}
                    </div>

                    {{--
                        El total, que lo calcula el servidor y llega con la pagina.

                        Va a la derecha y no en el pie de la tabla porque la tabla
                        se pide por ajax y el pie se pinta en el navegador, que
                        es donde no se puede sumar: los ingresos de una orden
                        pueden estar en dolares y en cordoba, y para sumarlos
                        haria falta el tipo de cambio de cada fila. Sumando aqui,
                        con la cuenta ya hecha, sale con las mismas cifras que
                        hay guardadas en las filas de arriba.
                    --}}
                    <div class="d-flex justify-content-end mt-3">
                        <div class="text-end">
                            <div class="text-muted small">
                                Total facturado en córdobas
                            </div>

                            <div class="fs-4 fw-bold font-monospace">
                                {{-- Un guion si no hay ingresos. El cero se
                                     reserva para una orden a la que se le ha
                                    cobrado y le ha costado cero, que son dos
                                 cosas distintas. --}}
                                {{ $ingresosCantidad === 0
                                    ? '—'
                                    : number_format($ingresosTotalNio, 2, '.', ',') }}
                            </div>

                            <div class="text-muted small">
                                @if ($ingresosCantidad === 0)
                                    Sin ingresos registrados.
                                @else
                                    {{ $ingresosCantidad }}
                                    {{ $ingresosCantidad === 1 ? 'ingreso' : 'ingresos' }}.

                                    {{--
                                        Y los que se quedaron fuera de la suma.
                                        Va con aviso y no en gris: si hay alguno,
                                        el total de arriba esta mas bajo de lo
                                        que se ha facturado, y eso hay que verlo
                                        entrando al ingreso uno por uno.
                                    --}}
                                    @if ($ingresosSinEquivalente > 0)
                                        <span class="text-warning-emphasis">
                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                            {{ $ingresosSinEquivalente }}
                                            {{ $ingresosSinEquivalente === 1 ? 'se quedó' : 'se quedaron' }}
                                            sin equivalente en córdobas y no
                                            {{ $ingresosSinEquivalente === 1 ? 'cuenta' : 'cuentan' }}
                                            en el total.
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{--
        Los trabajos de los empleados ya no se listan aqui: cuelgan de
        cada proceso, asi que se ven entrando al proceso. La tabla de
        procesos de arriba muestra cuanto cuesta cada uno.

        Lo mismo con los costos que si son de un proceso: se registran y se
        ven alli. Los de aqui son los que no son de ninguno.
    --}}

    <div class="row">
        <div class="col-12 mt-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1">
                                <i class="bi bi-cash-stack me-2"></i>
                                Costos generales de la orden
                            </h5>
                            <p class="text-muted mb-0">
                                Los gastos de toda la orden, sin proceso concreto.
                            </p>
                        </div>

                        <button type="button"
                                class="btn btn-primary"
                                id="btnNuevoCostoOrden"
                                @disabled($ordenTrabajo->estaCerrada())
                                title="{{ $ordenTrabajo->estaCerrada() ? 'La orden está cerrada: no se puede registrar un costo' : '' }}">
                            <i class="bi bi-plus-lg me-1"></i>
                            Registrar costo
                        </button>
                    </div>

                    <div class="alert alert-light border d-flex align-items-start gap-2">
                        <i class="bi bi-info-circle fs-5"></i>
                        <div>
                            Aquí van alquiler, transporte, un insumo suelto: lo
                            que no pertenece a un proceso en concreto. Los
                            consumos de cada proceso (energía, agua, materia
                            prima) se registran en la pantalla de ese proceso,
                            y su costo se ve en la columna de la tabla de
                            procesos de arriba.
                        </div>
                    </div>

                    <div class="table-responsive">
                        {{ $costosDataTable->html()->table() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{--
        La confirmacion va en el formulario, no en el boton: asi la libreria
        lo pide antes de enviarlo y si se cancela no se envia nada.
    --}}
    <form id="formEliminarOrden"
          action="{{ route('procesos.ordenes_trabajo.destroy', $ordenTrabajo) }}"
          method="POST"
          class="d-none"
          data-confirm-delete
          data-confirm-title="¿Eliminar la orden de trabajo?"
          data-confirm-text="Esta acción no se puede deshacer."
          data-confirm-button="Sí, eliminar">
        @csrf
        @method('DELETE')
    </form>

    <x-slot:footerFiles>
        {{ $dataTable->html()->scripts() }}
        {{ $costosDataTable->html()->scripts() }}
        {{ $ingresosDataTable->html()->scripts() }}
        @vite(['resources/assets/js/select2/select2-init.js'])
        {{--
            El de los ingresos va antes que el de los costos: los dos montan
            modales sobre la misma pagina y el orden en que se cargan es el
            orden en que se enganchan. Cada uno busca sus propias clases, asi
            que no se pisan, pero cargarlos al reves obligaria a comprobar que
            el otro ya esta, y esa dependencia no existe.
            --}}
        <script src="{{ asset('js/ordenes_trabajo/ingresos.js') }}"></script>
        <script src="{{ asset('js/ordenes_trabajo/costos.js') }}"></script>
    </x-slot>

    @include('procesos.ingresos._modal_form')
    @include('procesos.ingresos._modal_show')

    @include('procesos.ordenes_trabajo.costos._modal_form')
    @include('procesos.ordenes_trabajo.costos._modal_show')

</x-base-layout>

<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Información del Proceso' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
        @vite(['resources/scss/light/plugins/select2/custom-select2.scss'])
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-sm-12">

            <div class="widget-content widget-content-area br-8">

                {{-- Datos del proceso y lo que se le paga --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label text-muted">Código</label>
                        <div class="form-control bg-light font-monospace">
                            {{ $procesoOrden->codigo ?: '—' }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Etapa</label>
                        <div class="form-control bg-light">
                            {{ $procesoOrden->etapa?->nombre ?? '—' }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Estado</label>
                        <div class="pt-2">
                            {{--
                                La etiqueta sale del modelo. Aqui hubo un
                                if que miraba la columna estado como si fuera
                                un booleano y decia "Activo" o "Inactivo",
                                que no es lo mismo que el "Pendiente" que
                                decia el listado para el mismo proceso. El
                                estado ahora se deduce de las fechas.
                            --}}
                            {!! $procesoOrden->estadoEtiqueta() !!}
                        </div>

                        <div class="form-text">
                            @if($procesoOrden->estadoCalculado() === \App\Models\ProcesosOrden::ESTADO_PENDIENTE)
                                Se marca solo cuando tenga fecha de inicio.
                            @elseif($procesoOrden->estaCancelado())
                                Marcado como cancelado. Para volver atrás, quita
                                la marca de cancelación en el formulario del proceso.
                            @elseif($procesoOrden->estadoCalculado() === \App\Models\ProcesosOrden::ESTADO_FINALIZADO)
                                Finalizado: su fecha de fin ya pasó.
                            @else
                                En proceso: la fecha de fin aún no llega.
                            @endif
                        </div>
                    </div>

                    {{--
                        El costo se calcula al momento sumando los trabajos
                        del proceso, por eso no hay que mantenerlo sincronizado.
                    --}}
                    {{--
                        Los tres componentes del costo van en una fila de
                        tres, y el total ocupa la fila entera: es el número
                        que se viene a mirar, y esconderlo en una esquina de
                        la rejilla lo dejaria como uno más de cuatro.
                    --}}
                    <div class="col-md-3">
                        <label class="form-label text-muted">Costo de mano de obra</label>
                        <div class="form-control bg-light fw-semibold fs-5">
                            {{ number_format($procesoOrden->costo_empleados, 2) }}
                        </div>
                        <div class="form-text">
                            Suma de los
                            {{ $cantidadTrabajos }}
                            trabajo(s) registrados en este proceso.
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted">Depreciación de equipos</label>
                        <div class="form-control bg-light fw-semibold fs-5">
                            {{ number_format($procesoOrden->costo_equipos, 2) }}
                        </div>
                        <div class="form-text">
                            Suma de la depreciación de los
                            {{ $cantidadEquipos }}
                            equipo(s) asignados a este proceso.
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted">Otros costos</label>
                        <div class="form-control bg-light fw-semibold fs-5">
                            {{ number_format($procesoOrden->costo_otros, 2) }}
                        </div>
                        <div class="form-text">
                            Energía, agua, materia prima y demás conceptos
                            registrados a mano en este proceso.
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted">
                            <i class="bi bi-calculator me-1"></i>
                            Costo total del proceso
                        </label>
                        <div class="form-control bg-light fw-semibold fs-4 text-primary">
                            {{ number_format($procesoOrden->costo_total, 2) }}
                        </div>
                        <div class="form-text">
                            Mano de obra, depreciación y los demás costos.
                            Abajo está el desglose de dónde sale cada parte.
                        </div>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label text-muted">Fechas</label>
                        <div class="form-control bg-light">
                            {{ $procesoOrden->fecha_inicio?->format('d/m/Y') ?? '—' }}
                            &nbsp;a&nbsp;
                            {{ $procesoOrden->fecha_fin?->format('d/m/Y') ?? 'sin fecha de fin' }}
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="mb-0">
                        <i class="bi bi-people me-2"></i>
                        Trabajos de los empleados
                    </h5>

                    <button type="button" class="btn btn-primary" id="btnNuevoTrabajoEmpleado">
                        <i class="bi bi-plus-lg me-1"></i>
                        Nuevo trabajo
                    </button>
                </div>

                {!! $trabajosDataTable->html()->table(['class' => 'table table-hover'], true) !!}

                <hr class="my-4">

                {{--
                    Los equipos del proceso. El uso de un equipo cuelga del
                    proceso, igual que el trabajo de un empleado, y su
                    depreciacion se carga al costo del proceso.
                --}}
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="mb-0">
                        <i class="bi bi-gear-wide-connected me-2"></i>
                        Equipos del proceso
                    </h5>

                    <button type="button" class="btn btn-primary" id="btnNuevoUsoEquipo">
                        <i class="bi bi-plus-lg me-1"></i>
                        Asignar equipo
                    </button>
                </div>

                <div class="alert alert-light border d-flex align-items-start gap-2">
                    <i class="bi bi-info-circle fs-5"></i>
                    <div>
                        Un equipo puede trabajar en varios procesos seguidos,
                        nunca en el mismo instante. Por eso el periodo lleva
                        fecha <strong>y hora</strong>, y no se puede asignar un
                        equipo a este proceso si ya está asignado a otro en esas
                        fechas.
                    </div>
                </div>

                <div class="table-responsive">
                    {!! $equiposDataTable->html()->table(['class' => 'table table-hover'], true) !!}
                </div>

                <hr class="my-4">

                {{--
                    Los costos del proceso: energia, agua, materia prima y
                    demas conceptos que se cargan a mano. El detalle va en su
                    propia tabla y arriba, en el desglose, se ve de donde
                    sale cada numero del total.
                --}}
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="mb-0">
                        <i class="bi bi-cash-coin me-2"></i>
                        Costos del proceso
                    </h5>

                    <button type="button" class="btn btn-primary" id="btnNuevoCosto">
                        <i class="bi bi-plus-lg me-1"></i>
                        Registrar costo
                    </button>
                </div>

                <div class="alert alert-light border d-flex align-items-start gap-2">
                    <i class="bi bi-info-circle fs-5"></i>
                    <div>
                        Aquí van los consumos de este proceso: energía, agua y
                        demás conceptos. La mano de obra, la depreciación y la
                        <strong>materia prima</strong> <strong>no</strong> se
                        registran aquí: las calcula el sistema desde los
                        trabajos de los empleados, desde los equipos asignados y
                        desde el almacén, y escribirlas también las contaría
                        dos veces.
                    </div>
                </div>

                {!! $costosDataTable->html()->table(['class' => 'table table-hover'], true) !!}

                <hr class="my-4">

                {{--
                    La materia prima del proceso. Va aparte de los costos de
                    arriba y no es solo estetico: estos consumos salen del
                    almacen, mueven las existencias y se valuan al costo
                    promedio. Registrarlos como un costo mas haria que el
                    material se descontara dos veces, una del stock y otra del
                    costo del proceso.
                --}}
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="mb-0">
                        <i class="bi bi-box-seam me-2"></i>
                        Materia prima consumida
                    </h5>

                    <button type="button" class="btn btn-primary" id="btnNuevoMaterial">
                        <i class="bi bi-plus-lg me-1"></i>
                        Registrar consumo
                    </button>
                </div>

                <div class="alert alert-light border d-flex align-items-start gap-2">
                    <i class="bi bi-info-circle fs-5"></i>
                    <div>
                        Cemento, químicos, reactivos: lo que se gasta en este
                        proceso. Al registrar el consumo el material
                        <strong>se descuenta del almacén</strong> y su costo
                        entra al total de este proceso con el valor del costo
                        promedio del almacén, no con el precio que se escriba
                        aquí. Si el material aún no tiene costo cargado, el
                        consumo sumará cero.
                    </div>
                </div>

                @if($cantidadMateriales > 0)
                    <p class="form-text">
                        {{ $cantidadMateriales }} consumo(s) registrado(s),
                        por {{ number_format($procesoOrden->costo_materia_prima, 2) }}.
                    </p>
                @endif

                <div class="table-responsive">
                    {!! $materialesDataTable->html()->table(['class' => 'table table-hover'], true) !!}
                </div>

                <hr class="my-4">

                {{--
                    De donde sale el total. Sin esto la pantalla diria
                    cuanto cuesta el proceso pero no por que, que es justo
                    lo que hace inutil un total.
                --}}
                <h5 class="mb-3">
                    <i class="bi bi-diagram-2 me-2"></i>
                    De dónde sale el costo
                </h5>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Concepto</th>
                                <th>De dónde viene</th>
                                <th class="text-end">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($costosDesglosados as $rubro)
                                <tr class="{{ $rubro['importe'] == 0 ? 'text-muted' : '' }}">
                                    <td>
                                        @if($rubro['automatico'])
                                            <i class="bi bi-magic me-1"
                                               title="Lo calcula el sistema"></i>
                                        @else
                                            <i class="bi bi-pencil-square me-1"
                                               title="Se carga a mano"></i>
                                        @endif
                                        {{ $rubro['nombre'] }}
                                    </td>
                                    <td class="small">{{ $rubro['origen'] }}</td>
                                    <td class="text-end {{ $rubro['importe'] == 0 ? '' : 'fw-semibold' }}">
                                        {{ number_format($rubro['importe'], 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2" class="text-end">
                                    Costo total del proceso
                                </th>
                                <th class="text-end fs-6">
                                    {{ number_format($procesoOrden->costo_total, 2) }}
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <p class="form-text mt-2">
                    <i class="bi bi-info-circle me-1"></i>
                    Los importes marcados con <i class="bi bi-magic"></i> los calcula
                    el sistema y no se pueden escribir a mano; los que llevan
                    <i class="bi bi-pencil-square"></i> se cargan desde las
                    tablas de arriba. Todos se calculan al momento, así que
                    nunca quedan desfasados de los movimientos que los originan.
                </p>

            </div>

        </div>

    </div>

    <x-slot:footerFiles>
        {{ $trabajosDataTable->html()->scripts() }}
        {{ $equiposDataTable->html()->scripts() }}
        {{ $costosDataTable->html()->scripts() }}
        {{ $materialesDataTable->html()->scripts() }}
        {{ $empleadosSelectorDataTable->html()->scripts() }}
        @vite(['resources/assets/js/select2/select2-init.js'])
        <script src="{{ asset('js/ordenes_trabajo/trabajos_empleados.js') }}"></script>
        <script src="{{ asset('js/ordenes_trabajo/proceso_equipos.js') }}"></script>
        <script src="{{ asset('js/ordenes_trabajo/costos.js') }}"></script>
        <script src="{{ asset('js/ordenes_trabajo/materiales.js') }}"></script>
    </x-slot>

    @include('procesos.ordenes_trabajo.trabajos_empleados._modal_form')
    @include('admin.empleados._modal_empleado')
    @include('procesos.ordenes_trabajo.trabajos_empleados._modal_show')
    @include('procesos.procesos_orden.equipos._modal_form')
    @include('procesos.procesos_orden.equipos._modal_show')
    @include('procesos.procesos_orden.costos._modal_form')
    @include('procesos.procesos_orden.costos._modal_show')
    @include('procesos.procesos_orden.materiales._modal_form')

</x-base-layout>

<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Calendario de Órdenes de Trabajo' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
        <link href="{{ asset('plugins/fullcalendar/skeleton.css') }}" rel="stylesheet" />
        <link href="{{ asset('plugins/fullcalendar/bootstrap.theme.css') }}" rel="stylesheet" />
        @vite(['resources/scss/light/assets/components/modal.scss'])
        @vite(['resources/scss/dark/plugins/fullcalendar/custom-fullcalendar.scss'])
        @vite(['resources/scss/dark/assets/components/modal.scss'])
    </x-slot:headerFiles>

    <div class="row layout-top-spacing">
        <div class="col-xl-12 col-lg-12 col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1">
                                <i class="bi bi-calendar3 me-2"></i>
                                Calendario de órdenes
                            </h5>
                            <p class="text-muted mb-0">
                                Cada orden aparece el día de su fecha. Al pulsarla
                                se abre el resumen, con un enlace a su ficha.
                            </p>
                        </div>

                        <a href="{{ route('procesos.ordenes_trabajo.index') }}" class="btn btn-light">
                            <i class="bi bi-list-ul me-1"></i> Ver como listado
                        </a>
                    </div>

                    {{--
                        El calendario lo pinta el js a partir de los
                        eventos. La url va en el elemento para que el
                        javascript no la tenga escrita dentro.
                    --}}
                    <div class="calendar-container">
                        <div id="calendar"
                             data-eventos-url="{{ route('procesos.ordenes_trabajo.eventos') }}"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{--
        Resumen de la orden al pulsarla en el calendario.

        Es a proposito corto: lo justo para reconocerla y decidir si se
        entra. Lo demas (procesos, costos, trabajos) esta en la ficha, que
        es donde se puede modificar, no aqui.
    --}}
    <div class="modal fade" id="modalOrdenCalendario" tabindex="-1"
         aria-labelledby="modalOrdenCalendarioLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-white">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalOrdenCalendarioLabel">Orden de trabajo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-7">
                            <label class="form-label text-muted">Código</label>
                            <div class="fw-semibold font-monospace" id="calCodigo">—</div>
                        </div>

                        <div class="col-5">
                            <label class="form-label text-muted">Estado</label>
                            <div id="calEstado">—</div>
                        </div>

                        <div class="col-7">
                            <label class="form-label text-muted">Cliente</label>
                            <div class="fw-semibold" id="calCliente">—</div>
                        </div>

                        <div class="col-5">
                            <label class="form-label text-muted">Fecha</label>
                            <div id="calFecha">—</div>
                        </div>

                        <div class="col-6">
                            <label class="form-label text-muted">Peso del mineral</label>
                            <div id="calPeso">—</div>
                        </div>

                        <div class="col-6">
                            <label class="form-label text-muted">Procesos</label>
                            <div id="calProcesos">—</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-muted">Descripción</label>
                            <div class="text-muted" id="calDescripcion">—</div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>

                    <a href="#" class="btn btn-primary" id="calIrAVer">
                        <i class="bi bi-box-arrow-up-right me-1"></i>
                        Ir a ver la orden
                    </a>
                </div>
            </div>
        </div>
    </div>

    <x-slot:footerFiles>
        <script src="{{ asset('plugins/fullcalendar/fullcalendar.global.js') }}"></script>
        <script src="{{ asset('plugins/fullcalendar/bootstrap.global.js') }}"></script>
        {{--
            El bundle de locales se carga despues del core porque se
            engancha a el: sin este orden, FullCalendar.Shared no existe
            todavia y el archivo avienta al cargarse.
        --}}
        <script src="{{ asset('plugins/fullcalendar/locales-all/global.js') }}"></script>
        <script src="{{ asset('js/ordenes/calendario.js') }}"></script>
    </x-slot:footerFiles>

</x-base-layout>

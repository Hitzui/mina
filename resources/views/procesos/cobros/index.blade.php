<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Cobros' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">

            <div class="widget-content widget-content-area br-8">

                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

                    <div>
                        <h4 class="mb-1">
                            <i class="bi bi-cash-coin me-2"></i>
                            Cobros
                        </h4>

                        {{--
                            El texto explica lo que el estado significa, que es lo que
                            no se ve mirando la tabla: un cobro pendiente no es un
                            cobro equivocado, es dinero que se tiene que recibir. Y un
                            cobro se reparte entre ingresos, no entre ordenes —el
                            cobro es por cliente—, que es lo que explica el boton de
                            "ver" en vez de un enlace a la orden.
                        --}}
                        <p class="text-muted mb-0">
                            El dinero que el cliente entrega. Cada cobro se reparte
                            entre los <strong>ingresos</strong> que salda, y puede
                            repartir un ingreso en varios cobros porque un taller
                            factura y va cobrando a plazos. Un cobro
                            <strong>Finalizado</strong> es dinero confirmado en la
                            caja; <strong>Pendiente</strong> es que se anotó pero
                            todavía no llegó.
                        </p>
                    </div>

                    <a
                        href="{{ route('procesos.cobros.create') }}"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-plus me-1"></i>
                        Nuevo Cobro
                    </a>

                </div>

                {{--
                    Y un aviso cuando falta alguno de los tres desplegables.

                    Es un caso real, no hipotetico: la tabla de metodos de pago estuvo
                    vacia hasta hace dos dias. El sintoma sin aviso es el peor —el
                    boton de "nuevo" lleva a una pantalla con tres desplegables
                    vacios y no dice por que, y el usuario busca el error en el
                    formulario cuando el problema es de catalogo—, asi que el aviso
                    va arriba del todo y dice que hacer.

                    Y no bloquea: se puede entrar igual. Puede que el usuario solo
                    este mirando la lista, y taparle el boton por un problema de
                    configuracion es peor que avisarle.
                --}}
                @if ($faltanDesplegables !== [])
                    <div class="alert alert-warning d-flex align-items-start gap-2 mb-4"
                         style="font-size: .85rem;">
                        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                        <div>
                            <strong>No se puede registrar un cobro todavía.</strong>
                            {{ implode(' ', $faltanDesplegables) }}
                            Van en Configuración, y en cuanto exista alguno ya se
                            puede registrar.
                        </div>
                    </div>
                @endif

                <div class="table-responsive">

                    {!! $dataTable->table([
                        'class' => 'table table-hover table-bordered w-100',
                    ]) !!}

                </div>

            </div>

        </div>

    </div>

    <x-slot:footerFiles>

        {!! $dataTable->scripts() !!}

    </x-slot>

</x-base-layout>

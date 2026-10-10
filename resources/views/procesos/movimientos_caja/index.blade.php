<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title }}</x-slot:pageTitle>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles>
        <style>
            .resumen-saldo-card {
                min-height: 138px;
                border: 0;
                border-radius: 12px;
                color: #fff;
                overflow: hidden;
                box-shadow: 0 5px 16px rgba(31, 45, 61, .12);
            }

            .resumen-saldo-card .card-body {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 1.25rem;
            }

            .resumen-saldo-card .saldo-etiqueta {
                margin-bottom: .55rem;
                color: rgba(255, 255, 255, .88);
                font-size: .9rem;
                font-weight: 600;
            }

            .resumen-saldo-card .saldo-monto {
                margin: 0;
                color: #fff;
                font-size: clamp(1.25rem, 2vw, 1.75rem);
                font-weight: 700;
                line-height: 1.2;
                overflow-wrap: anywhere;
            }

            .resumen-saldo-card .saldo-icono {
                display: grid;
                width: 48px;
                height: 48px;
                flex: 0 0 48px;
                place-items: center;
                border-radius: 50%;
                background: rgba(255, 255, 255, .2);
                font-size: 1.45rem;
            }

            .resumen-saldo-inicial { background: linear-gradient(135deg, #4361ee, #2952cc); }
            .resumen-saldo-entradas { background: linear-gradient(135deg, #00ab55, #008a45); }
            .resumen-saldo-salidas { background: linear-gradient(135deg, #e7515a, #cf3540); }
            .resumen-saldo-final { background: linear-gradient(135deg, #805dca, #6241a8); }
        </style>
    </x-slot>

    <div class="row layout-top-spacing">
        <div class="col-12">
            <div class="widget-content widget-content-area br-8">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1"><i class="bi bi-arrow-left-right me-2"></i>Movimientos de caja</h4>
                        <p class="text-muted mb-0">Ingresos, egresos y transferencias; los cobros finalizados se registran automáticamente.</p>
                    </div>
                    @can('movimientos_caja.create')
                        <a href="{{ route('procesos.movimientos_caja.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus me-1"></i>Registrar movimiento
                        </a>
                    @endcan
                </div>

                <h5 class="mb-3">Resumen de saldos por caja y moneda</h5>
                @if ($saldos->isEmpty())
                    <div class="alert alert-info">Todavía no hay movimientos registrados.</div>
                @else
                    <div class="row g-4 mb-4">
                        @foreach ($saldos as $saldo)
                            <div class="col-12">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <h6 class="mb-0 fw-bold">{{ $saldo->caja }}</h6>
                                    <span class="badge bg-light-primary text-primary">{{ $saldo->moneda }}</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-12 col-sm-6 col-xl-3">
                                        <div class="card resumen-saldo-card resumen-saldo-inicial h-100">
                                            <div class="card-body">
                                                <div>
                                                    <div class="saldo-etiqueta">Saldo inicial</div>
                                                    <p class="saldo-monto">{{ number_format((float) $saldo->saldo_inicial, 2, '.', ',') }}</p>
                                                </div>
                                                <span class="saldo-icono"><i class="bi bi-wallet2"></i></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6 col-xl-3">
                                        <div class="card resumen-saldo-card resumen-saldo-entradas h-100">
                                            <div class="card-body">
                                                <div>
                                                    <div class="saldo-etiqueta">Entradas</div>
                                                    <p class="saldo-monto">{{ number_format((float) $saldo->entradas, 2, '.', ',') }}</p>
                                                </div>
                                                <span class="saldo-icono"><i class="bi bi-arrow-down-left"></i></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6 col-xl-3">
                                        <div class="card resumen-saldo-card resumen-saldo-salidas h-100">
                                            <div class="card-body">
                                                <div>
                                                    <div class="saldo-etiqueta">Salidas</div>
                                                    <p class="saldo-monto">{{ number_format((float) $saldo->salidas, 2, '.', ',') }}</p>
                                                </div>
                                                <span class="saldo-icono"><i class="bi bi-arrow-up-right"></i></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6 col-xl-3">
                                        <div class="card resumen-saldo-card resumen-saldo-final h-100">
                                            <div class="card-body">
                                                <div>
                                                    <div class="saldo-etiqueta">Saldo final</div>
                                                    <p class="saldo-monto">{{ number_format((float) $saldo->saldo_final, 2, '.', ',') }}</p>
                                                </div>
                                                <span class="saldo-icono"><i class="bi bi-cash-stack"></i></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="table-responsive">
                    {!! $dataTable->table(['class' => 'table table-hover table-bordered w-100']) !!}
                </div>
            </div>
        </div>
    </div>

    <x-slot:footerFiles>{!! $dataTable->scripts() !!}</x-slot>
</x-base-layout>

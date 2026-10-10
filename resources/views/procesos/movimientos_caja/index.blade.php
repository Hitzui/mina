<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title }}</x-slot:pageTitle>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles></x-slot>

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
                    <div class="row g-3 mb-4">
                        @foreach ($saldos as $saldo)
                            <div class="col-12 col-xl-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="text-muted mb-3">{{ $saldo->caja }} · {{ $saldo->moneda }}</h6>
                                    <div class="row g-3">
                                        <div class="col-6 col-xxl-3">
                                            <div class="text-muted small">Saldo inicial</div>
                                            <div class="fw-semibold">{{ number_format((float) $saldo->saldo_inicial, 2, '.', ',') }}</div>
                                        </div>
                                        <div class="col-6 col-xxl-3">
                                            <div class="text-muted small">Entradas</div>
                                            <div class="fw-semibold text-success">{{ number_format((float) $saldo->entradas, 2, '.', ',') }}</div>
                                        </div>
                                        <div class="col-6 col-xxl-3">
                                            <div class="text-muted small">Salidas</div>
                                            <div class="fw-semibold text-danger">{{ number_format((float) $saldo->salidas, 2, '.', ',') }}</div>
                                        </div>
                                        <div class="col-6 col-xxl-3">
                                            <div class="text-muted small">Saldo final</div>
                                            <div class="fs-5 fw-semibold">{{ number_format((float) $saldo->saldo_final, 2, '.', ',') }}</div>
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

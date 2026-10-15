<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Equipos' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="card">
        <div class="card-header">
            <i class="bi bi-gear-wide-connected me-1"></i>
            Administrar Equipos
        </div>

        <div class="card-body">

            <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
                <i class="bi bi-info-circle fs-5"></i>
                <div>
                    Configure los componentes de cada equipo: depreciación, electricidad y recursos de almacén.
                    Los costos por tiempo usan horas efectivas. Los insumos se cargan al proceso al confirmar su consumo.
                </div>
            </div>

            <div class="card border mb-4">
                <div class="card-body">
                    <form action="{{ route('admin.equipos.tarifa-energia') }}" method="POST" class="row align-items-end g-3">
                        @csrf
                        @method('PUT')
                        <div class="col-md-5">
                            <label for="tarifa_kwh_nio" class="form-label fw-semibold">Tarifa general de energía (NIO/kWh)</label>
                            <input type="number" id="tarifa_kwh_nio" name="tarifa_kwh_nio" class="form-control @error('tarifa_kwh_nio') is-invalid @enderror" value="{{ old('tarifa_kwh_nio', $tarifaEnergia?->tarifa_kwh_nio) }}" min="0.000001" step="0.000001" required>
                            @error('tarifa_kwh_nio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Tarifa vigente: <strong>{{ $tarifaEnergia ? number_format($tarifaEnergia->tarifa_kwh_nio, 6) . ' NIO/kWh' : 'Sin configurar' }}</strong></div>
                            <div class="small text-muted">Las nuevas asignaciones guardan la tarifa aplicada.</div>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-lightning-charge me-1"></i>Actualizar tarifa</button>
                        </div>
                    </form>
                </div>
            </div>

            <a href="{{ route('admin.equipos.create') }}" class="btn btn-outline-info">
                <i class="bi bi-plus-lg me-1"></i> Ingresar Nuevo Equipo
            </a>

            <p>&nbsp;</p>

            <div class="table-responsive">
                {{ $dataTable->table() }}
            </div>
        </div>
    </div>

    <x-slot:footerFiles>
        {{ $dataTable->scripts() }}
    </x-slot>

</x-base-layout>

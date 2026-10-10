<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title }}</x-slot:pageTitle>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles></x-slot>

    <div class="row layout-top-spacing">
        <div class="col-xl-9 col-lg-12">
            <div class="widget-content widget-content-area br-8">
                <h4 class="mb-1"><i class="bi bi-arrow-left-right me-2"></i>Registrar movimiento de caja</h4>
                <p class="text-muted mb-4">Los movimientos registrados no se editan ni eliminan; si hay un error, se corrige con un movimiento compensatorio.</p>

                @if ($errors->any())
                    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif

                <form method="POST" action="{{ route('procesos.movimientos_caja.store') }}" class="row g-3">
                    @csrf
                    <div class="col-12 col-md-6">
                        <label for="accion" class="form-label">Tipo de movimiento <span class="text-danger">*</span></label>
                        <select id="accion" name="accion" class="form-select" required>
                            <option value="entrada" @selected(old('accion') === 'entrada')>Entrada manual</option>
                            <option value="salida" @selected(old('accion') === 'salida')>Egreso</option>
                            <option value="saldo_inicial" @selected(old('accion') === 'saldo_inicial')>Saldo inicial</option>
                            <option value="transferencia" @selected(old('accion') === 'transferencia')>Transferencia entre cajas</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="fecha" class="form-label">Fecha <span class="text-danger">*</span></label>
                        <input type="text" id="fecha" name="fecha" class="form-control flatpickr-fecha" data-calendario="propio" value="{{ old('fecha', today()->format('Y-m-d')) }}" required>
                    </div>

                    <div class="col-12 col-md-6 campo-caja-unica">
                        <label for="caja_id" class="form-label">Caja <span class="text-danger">*</span></label>
                        <select id="caja_id" name="caja_id" class="form-select">
                            <option value="">Seleccione una caja activa</option>
                            @foreach ($cajas->where('estado', true) as $caja)
                                <option value="{{ $caja->id }}" @selected(old('caja_id') == $caja->id)>{{ $caja->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6 campo-transferencia d-none">
                        <label for="caja_origen_id" class="form-label">Caja de origen <span class="text-danger">*</span></label>
                        <select id="caja_origen_id" name="caja_origen_id" class="form-select" disabled>
                            <option value="">Seleccione caja origen</option>
                            @foreach ($cajas->where('estado', true) as $caja)
                                <option value="{{ $caja->id }}" @selected(old('caja_origen_id') == $caja->id)>{{ $caja->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6 campo-transferencia d-none">
                        <label for="caja_destino_id" class="form-label">Caja de destino <span class="text-danger">*</span></label>
                        <select id="caja_destino_id" name="caja_destino_id" class="form-select" disabled>
                            <option value="">Seleccione caja destino</option>
                            @foreach ($cajas->where('estado', true) as $caja)
                                <option value="{{ $caja->id }}" @selected(old('caja_destino_id') == $caja->id)>{{ $caja->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="metodo_pago_id" class="form-label">Método de pago <span class="text-danger">*</span></label>
                        <select id="metodo_pago_id" name="metodo_pago_id" class="form-select" required>
                            <option value="">Seleccione método</option>
                            @foreach ($metodos as $metodo)
                                <option value="{{ $metodo->id }}" @selected(old('metodo_pago_id') == $metodo->id)>{{ $metodo->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="moneda_id" class="form-label">Moneda <span class="text-danger">*</span></label>
                        <select id="moneda_id" name="moneda_id" class="form-select" required>
                            <option value="">Seleccione moneda</option>
                            @foreach ($monedas as $moneda)
                                <option value="{{ $moneda->id }}" @selected(old('moneda_id') == $moneda->id)>{{ $moneda->codigo }} — {{ $moneda->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="monto" class="form-label">Importe <span class="text-danger">*</span></label>
                        <input type="number" id="monto" name="monto" class="form-control text-end" min="0.01" step="0.01" value="{{ old('monto') }}" required>
                    </div>
                    <div class="col-12 campo-descripcion">
                        <label for="descripcion" class="form-label">Concepto <span class="text-danger">*</span></label>
                        <input type="text" id="descripcion" name="descripcion" class="form-control" maxlength="255" value="{{ old('descripcion') }}" placeholder="Ej.: compra de suministros, fondo inicial...">
                    </div>

                    <div class="col-12 d-flex gap-2 flex-wrap mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar movimiento</button>
                        <a href="{{ route('procesos.movimientos_caja.index') }}" class="btn btn-light">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <x-slot:footerFiles>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof flatpickr !== 'undefined') {
                    flatpickr('#fecha', { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y', locale: 'es' });
                }

                const accion = document.getElementById('accion');
                const cajaUnica = document.querySelector('.campo-caja-unica');
                const campoCaja = document.getElementById('caja_id');
                const descripcion = document.getElementById('descripcion');

                function actualizarCampos() {
                    const transferencia = accion.value === 'transferencia';
                    document.querySelectorAll('.campo-transferencia').forEach(function (campo) {
                        campo.classList.toggle('d-none', !transferencia);
                        campo.querySelector('select').disabled = !transferencia;
                        campo.querySelector('select').required = transferencia;
                    });
                    cajaUnica.classList.toggle('d-none', transferencia);
                    campoCaja.disabled = transferencia;
                    campoCaja.required = !transferencia;
                    document.querySelector('.campo-descripcion').classList.toggle('d-none', transferencia);
                    descripcion.disabled = transferencia;
                    descripcion.required = !transferencia;
                    if (accion.value === 'saldo_inicial' && !descripcion.value) {
                        descripcion.value = 'Saldo inicial';
                    }
                }

                accion.addEventListener('change', actualizarCampos);
                actualizarCampos();
            });
        </script>
    </x-slot>
</x-base-layout>

<form method="POST" action="{{ route('procesos.movimientos_caja.store') }}" id="formMovimientoCaja">
    @csrf
    <div class="modal-body">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="alert alert-light border d-flex align-items-center gap-3 mb-4">
            <i class="bi bi-safe2 fs-3 text-primary"></i>
            <div>
                <div class="small text-muted">Caja asignada a su usuario</div>
                <strong>{{ $caja?->nombre ?? 'Sin caja asignada' }}</strong>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label for="accion" class="form-label">Tipo de movimiento <span class="text-danger">*</span></label>
                <select id="accion" name="accion" class="form-select" required>
                    <option value="entrada" @selected(old('accion', 'entrada') === 'entrada')>Ingreso</option>
                    <option value="salida" @selected(old('accion') === 'salida')>Egreso</option>
                    <option value="saldo_inicial" @selected(old('accion') === 'saldo_inicial')>Apertura de caja (saldo inicial)</option>
                    <option value="transferencia" @selected(old('accion') === 'transferencia')>Transferencia a otra caja</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="fecha" class="form-label">Fecha <span class="text-danger">*</span></label>
                <input type="date" id="fecha" name="fecha" class="form-control" value="{{ old('fecha', today()->format('Y-m-d')) }}" required>
            </div>

            <div class="col-12 campo-transferencia d-none">
                <label for="caja_destino_id" class="form-label">Caja de destino <span class="text-danger">*</span></label>
                <select id="caja_destino_id" name="caja_destino_id" class="form-select" disabled>
                    <option value="">Seleccione caja destino</option>
                    @foreach ($cajasDestino as $cajaDestino)
                        <option value="{{ $cajaDestino->id }}" @selected(old('caja_destino_id') == $cajaDestino->id)>{{ $cajaDestino->nombre }}</option>
                    @endforeach
                </select>
                @error('caja_destino_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label for="metodo_pago_id" class="form-label">Método de pago <span class="text-danger">*</span></label>
                <select id="metodo_pago_id" name="metodo_pago_id" class="form-select" required>
                    <option value="">Seleccione método</option>
                    @foreach ($metodos as $metodo)
                        <option value="{{ $metodo->id }}" @selected(old('metodo_pago_id') == $metodo->id)>{{ $metodo->nombre }}</option>
                    @endforeach
                </select>
                @error('metodo_pago_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="moneda_id" class="form-label">Moneda <span class="text-danger">*</span></label>
                <select id="moneda_id" name="moneda_id" class="form-select" required>
                    <option value="">Seleccione moneda</option>
                    @foreach ($monedas as $moneda)
                        <option value="{{ $moneda->id }}" data-codigo="{{ $moneda->codigo }}" @selected(old('moneda_id') == $moneda->id)>{{ $moneda->codigo }} — {{ $moneda->nombre }}</option>
                    @endforeach
                </select>
                @error('moneda_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="monto" class="form-label">Importe <span class="text-danger">*</span></label>
                <input type="number" id="monto" name="monto" class="form-control text-end" min="0.01" step="0.01" value="{{ old('monto') }}" required>
                <div id="saldoDisponibleMovimiento" class="form-text" aria-live="polite"></div>
                @error('monto')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 campo-descripcion">
                <label for="descripcion" class="form-label">Concepto <span class="text-danger">*</span></label>
                <input type="text" id="descripcion" name="descripcion" class="form-control" maxlength="255" value="{{ old('descripcion') }}" placeholder="Ej.: compra de suministros, fondo inicial...">
                @error('descripcion')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
        </div>
        <p class="text-muted small mt-3 mb-0">Los movimientos no se editan ni eliminan; si hay un error, se corrige con un movimiento compensatorio.</p>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar movimiento</button>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const formulario = document.getElementById('formMovimientoCaja');
        if (!formulario) return;

        const accion = formulario.querySelector('#accion');
        const destino = formulario.querySelector('#caja_destino_id');
        const descripcion = formulario.querySelector('#descripcion');
        const campoDescripcion = formulario.querySelector('.campo-descripcion');
        const metodo = formulario.querySelector('#metodo_pago_id');
        const moneda = formulario.querySelector('#moneda_id');
        const monto = formulario.querySelector('#monto');
        const saldoDisponible = formulario.querySelector('#saldoDisponibleMovimiento');
        const botonGuardar = formulario.querySelector('button[type="submit"]');
        const saldos = @json($saldosDisponibles);

        function actualizarFormulario() {
            const transferencia = accion.value === 'transferencia';
            formulario.querySelectorAll('.campo-transferencia').forEach(campo => {
                campo.classList.toggle('d-none', !transferencia);
            });
            destino.disabled = !transferencia;
            destino.required = transferencia;
            campoDescripcion.classList.toggle('d-none', transferencia);
            descripcion.disabled = transferencia;
            descripcion.required = !transferencia;

            if (accion.value === 'saldo_inicial' && !descripcion.value) {
                descripcion.value = 'Saldo inicial';
            }

            const egreso = accion.value === 'salida' || transferencia;
            if (egreso && metodo.value && moneda.value) {
                const clave = metodo.value + ':' + moneda.value;
                const saldo = Number(saldos[clave] ?? 0);
                const importe = Number(monto.value);
                const codigo = moneda.selectedOptions[0]?.dataset.codigo ?? '';
                const saldoTexto = codigo + ' ' + Math.max(saldo, 0).toLocaleString('es-NI', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });

                if (saldo <= 0) {
                    saldoDisponible.textContent = 'No hay saldo disponible en la caja para este método de pago y moneda (' + saldoTexto + '). Registre un ingreso primero.';
                } else if (monto.value && importe > saldo) {
                    saldoDisponible.textContent = 'El egreso supera el saldo disponible de ' + saldoTexto + '.';
                } else {
                    saldoDisponible.textContent = 'Saldo disponible en la caja asignada: ' + saldoTexto;
                }

                // Evita el aviso nativo de number/max, que solo decía que 0
                // no admite el mínimo 0.01 y no explicaba el saldo de caja.
                monto.removeAttribute('max');
                botonGuardar.disabled = saldo <= 0 || (Boolean(monto.value) && importe > saldo);
            } else {
                saldoDisponible.textContent = '';
                botonGuardar.disabled = false;
            }
        }

        [accion, metodo, moneda].forEach(elemento => elemento.addEventListener('change', actualizarFormulario));
        monto.addEventListener('input', actualizarFormulario);
        monto.addEventListener('change', actualizarFormulario);
        actualizarFormulario();

        @if($errors->any())
            const modalElemento = document.getElementById('modalMovimientoCaja');
            if (modalElemento && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(modalElemento).show();
            }
        @endif
    });
</script>

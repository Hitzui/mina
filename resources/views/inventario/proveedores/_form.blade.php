@php
    $editando = isset($proveedor) && $proveedor->exists;
@endphp

<div class="row g-3">

    {{--
        El codigo no se escribe. Al dar de alta lo pone el sistema con el
        siguiente numero de la serie, y al editar se muestra solo, porque no
        cambia nunca.
    --}}
    <div class="col-md-4">
        <label class="form-label text-muted" for="codigo">
            Código
        </label>

        @if($editando)
            <input
                type="text"
                id="codigo"
                class="form-control font-monospace bg-light"
                value="{{ $proveedor->codigo }}"
                readonly
                tabindex="-1"
            >
            <div class="form-text">
                No se puede cambiar. Es la identidad del proveedor.
            </div>
        @else
            <input
                type="text"
                id="codigo"
                class="form-control font-monospace bg-light"
                value="Se asigna automáticamente"
                readonly
                tabindex="-1"
            >
            <div class="form-text">
                Se asigna solo al guardar. Tendrá la forma
                {{ \App\Models\Proveedore::PREFIJO_CODIGO }}000001.
            </div>
        @endif
    </div>

    <div class="col-md-5">
        <label for="nombre" class="form-label">
            Nombre o razón social <span class="text-danger">*</span>
        </label>
        <input
            type="text"
            id="nombre"
            name="nombre"
            class="form-control @error('nombre') is-invalid @enderror"
            value="{{ old('nombre', $proveedor->nombre ?? '') }}"
            required
            maxlength="120"
        >
        @error('nombre')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3">
        <label for="contacto" class="form-label">
            Persona de contacto
        </label>
        <input
            type="text"
            id="contacto"
            name="contacto"
            class="form-control @error('contacto') is-invalid @enderror"
            value="{{ old('contacto', $proveedor->contacto ?? '') }}"
            maxlength="120"
        >
        <div class="form-text">
            Con quién se habla, si no es la misma empresa.
        </div>
        @error('contacto')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="telefono" class="form-label">
            Teléfono
        </label>
        <input
            type="text"
            id="telefono"
            name="telefono"
            class="form-control @error('telefono') is-invalid @enderror"
            value="{{ old('telefono', $proveedor->telefono ?? '') }}"
            maxlength="30"
        >
        @error('telefono')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="email" class="form-label">
            Correo
        </label>
        <input
            type="email"
            id="email"
            name="email"
            class="form-control @error('email') is-invalid @enderror"
            value="{{ old('email', $proveedor->email ?? '') }}"
            maxlength="120"
        >
        <div class="form-text">
            Es por donde se manda la factura cuando se le compra.
        </div>
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="estado" class="form-label">
            Estado
        </label>

        {{--
            El 0 va por delante a propósito. El interruptor manda 0 o 1, y si
            el 1 estuviera primero, al desmarcarlo se quedaría sin marcar y el
            navegador no mandaría nada: el proveedor se guardaría con el valor
            por defecto de la base en vez de inactivo.
        --}}
        <input type="hidden" name="estado" value="0">

        <div class="form-check form-switch mt-2">
            <input
                type="checkbox"
                class="form-check-input @error('estado') is-invalid @enderror"
                id="estado"
                name="estado"
                value="1"
                @checked(old('estado', $proveedor->estado ?? true))
            >
            <label class="form-check-label" for="estado">
                Activo
            </label>
        </div>

        <div class="form-text">
            Un proveedor inactivo no aparece en los listados de compra.
        </div>
        @error('estado')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="direccion" class="form-label">
            Dirección
        </label>
        <input
            type="text"
            id="direccion"
            name="direccion"
            class="form-control @error('direccion') is-invalid @enderror"
            value="{{ old('direccion', $proveedor->direccion ?? '') }}"
            maxlength="180"
        >
        @error('direccion')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="observaciones" class="form-label">
            Observaciones
        </label>
        <textarea
            id="observaciones"
            name="observaciones"
            class="form-control @error('observaciones') is-invalid @enderror"
            rows="2"
            maxlength="1000"
        >{{ old('observaciones', $proveedor->observaciones ?? '') }}</textarea>
        @error('observaciones')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

</div>

{{--
    El formulario de un metodo de pago.

    Va dentro de un modal, en la pantalla de la lista. Son tres campos y dos son
    de una frase: una pantalla entera para esto seria mas sitio en blanco que
    otra cosa.

    El nombre es lo unico que no puede faltar y no lleva maxlength en el campo
    para que el navegador no lo esconda: el limite son 100 caracteres y lo avisa
    el servidor con un texto que se puede entender, que es mejor que un aviso del
    navegador que solo dice "maxlength".

    La descripcion es libre y opcional. Sirve para escribir el detalle de como se
    cobra con ese metodo —"talon a 15 dias", "transferencia al banco"—, que es lo
    que ayuda a los seis meses, cuando ya nadie se acuerda de por que se aceptaba
    una cosa y no otra.

    Y el interruptor de activo lleva su texto debajo, porque "activo" por si solo
    no explica la distincion que es la importante: desactivar saca el metodo de
    los desplegables sin tocar los cobros que ya se registraron con el, mientras
    que borrar no se puede en cuanto hay uno. Un metodo inactivo se puede volver
    a activar; uno borrado, no.

    Ojo con la casilla: no es un campo de texto y por eso el javascript la marca
    o la quita al abrir el modal de edicion. Va con hidden=1 para que mande 0
    cuando este desmarcada, porque si no solo mandaria nada y el servidor leeria
    que el campo no vino, que no es lo mismo que "el usuario dijo que no".
--}}
<div class="row g-3">

    <div class="col-md-6">
        <label for="nombre" class="form-label">
            Nombre <span class="text-danger">*</span>
        </label>

        <input
            type="text"
            class="form-control @error('nombre') is-invalid @enderror"
            id="nombre"
            name="nombre"
            value="{{ old('nombre', $metodoPago->nombre ?? '') }}"
            maxlength="100"
            placeholder="Transferencia bancaria"
            required
        >

        @error('nombre')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            Cómo se escribe en la lista y en los cobros, con su tilde.
        </div>
    </div>

    <div class="col-md-6 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
            <input
                class="form-check-input @error('estado') is-invalid @enderror"
                type="checkbox"
                role="switch"
                id="estado"
                name="estado"
                value="1"
                @checked(old('estado', $metodoPago->estado ?? true))
            >

            <input type="hidden" name="estado" value="0" id="estadoCero">

            <label class="form-check-label" for="estado">
                Activo
            </label>

            @error('estado')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-12">
        <label for="descripcion" class="form-label">
            Descripción
        </label>

        <input
            type="text"
            class="form-control @error('descripcion') is-invalid @enderror"
            id="descripcion"
            name="descripcion"
            value="{{ old('descripcion', $metodoPago->descripcion ?? '') }}"
            maxlength="255"
            placeholder="Se recibe por transferencia al banco, con el comprobante"
        >

        @error('descripcion')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            El detalle de cómo se cobra con este método. Opcional.
        </div>
    </div>

    <div class="col-12">
        {{--
            Lo que significa activar y lo que significa borrar, escrito.

            Va debajo de todo y no al lado del interruptor porque son tres frases
            y al lado del interruptor, en una pantalla estrecha, se partirian en
            seis lineas de las que no se entiende ninguna. Ademas el que va a
            leerlo de verdad es el que esta dudando si desactivar es lo mismo que
            borrar, y esa duda se resuelve leyendo entero.
        --}}
        <div class="alert alert-light border mb-0 small">
            <div class="fw-semibold mb-1">Desactivar no es borrar</div>

            <div>
                Desactivar saca el método de los desplegables y lo deja de usar,
                pero <strong>todos los cobros que ya se registraron con él se
                quedan</strong>. Se puede volver a activar cuando vuelva a
                usarse.
            </div>

            <div class="mt-1">
                Borrar es definitivo. En cuanto hay un solo cobro con este método,
                <strong>no se puede borrar</strong>, porque el cobro guarda con
                qué se le pagó. Por eso la lista enseña cuántos cobros lleva cada
                método.
            </div>
        </div>
    </div>

</div>

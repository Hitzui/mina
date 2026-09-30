{{--
    El formulario de un tipo de ingreso.

    Va dentro de un modal, en la pantalla de la lista. Son tres campos y dos son
    de una frase: una pantalla entera para esto seria mas sitio en blanco que
    otra cosa.

    El nombre es lo unico que no puede faltar y no lleva maxlength en el campo
    para que el navegador no lo esconda: el limite son 100 caracteres y lo
    avisa el servidor con un texto que se puede entender, que es mejor que un
    aviso del navegador que solo dice "maxlength".

    La descripcion es libre y opcional. Lo que dice, en vez de lo que significa
    el tipo, es lo que ayuda cuando dentro de seis meses alguien tiene que
    decidir si un ingreso nuevo va con este tipo o con otro.

    Y el interruptor de activo lleva su texto debajo, porque "activo" por si
    solo no explica la distincion que es la importante: desactivar saca el tipo
    de los desplegables sin tocar los ingresos que ya se registraron con el,
    mientras que borrar no se puede en cuanto hay uno. Un tipo inactivo se puede
    volver a activar; uno borrado, no.

    Ojo con la casilla: no es un campo de texto y por eso el javascript la
    marca o la quita al abrir el modal de edicion. Va con hidden=1 para que
    mande 0 cuando este desmarcada, porque si no solo mandaria nada y el
    servidor leeria que el campo no vino, que no es lo mismo que "el usuario
    dijo que no".
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
            value="{{ old('nombre', $tipoIngreso->nombre ?? '') }}"
            maxlength="100"
            placeholder="Servicio de procesamiento"
            required
        >

        @error('nombre')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            Cómo se escribe en la lista y en los ingresos, con su tilde.
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
                @checked(old('estado', $tipoIngreso->estado ?? true))
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
            value="{{ old('descripcion', $tipoIngreso->descripcion ?? '') }}"
            maxlength="255"
            placeholder="Ingreso generado por el servicio prestado al cliente"
        >

        @error('descripcion')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            Qué entra en este tipo. Es lo que ayuda a elegir cuando se registra
            un ingreso dentro de seis meses.
        </div>
    </div>

    <div class="col-12">
        {{--
            Lo que significa activar y lo que significa borrar, escrito.

            Va debajo de todo y no al lado del interruptor porque son tres
            frases y al lado del interruptor, en una pantalla estrecha, se
            partirian en seis lineas de las que no se entiende ninguna. Ademas
            el que va a leerlo de verdad es el que esta dudando si desactivar
            es lo mismo que borrar, y esa duda se resuelve leyendo entero.
        --}}
        <div class="alert alert-light border mb-0 small">
            <div class="fw-semibold mb-1">Desactivar no es borrar</div>

            <div>
                Desactivar saca el tipo de los desplegables y lo deja de usar,
                pero <strong>todos los ingresos que ya se registraron con él se
                quedan</strong>. Se puede volver a activar cuando vuelva a
                usarse.
            </div>

            <div class="mt-1">
                Borrar es definitive. En cuanto hay un solo ingreso con este
                tipo, <strong>no se puede borrar</strong>, porque el ingreso
                guarda qué tipo era. Por eso la lista enseña cuántos ingresos
                lleva cada tipo.
            </div>
        </div>
    </div>

</div>

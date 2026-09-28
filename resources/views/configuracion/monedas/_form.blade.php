{{--
    El formulario de una moneda.

    Va dentro de un modal, en la pantalla de la lista. Son cinco campos y la
    mayoria son de una palabra: una pantalla entera para esto seria mas sitio
    en blanco que otra cosa.

    El codigo va con maxlength="3" y en mayusculas porque la columna es de tres
    caracteres y el indice unico no distingue mayusculas de minusculas: si se
    dejara escribir "eur" se guardaria como "EUR", y escribir "eur" dos veces
    pasaria la validacion y reventaria con un error de indice unico. Lo que no
    se puede es dejar que el navegador acepte mas de tres letras y avise de
    eso: el error de la base no le dice a nadie de que se trata.

    Las dos casillas no son campos de texto y por eso no hay que vaciarlas a
    mano al abrir el modal de edicion: el javascript las marca o las quita.
    Aun asi el formulario avisa de lo que significa cada una, porque "base" y
    "activa" son palabras que no explican nada por si solas.
--}}
<div class="row g-3">

    <div class="col-md-4">
        <label for="codigo" class="form-label">
            Código <span class="text-danger">*</span>
        </label>

        <input
            type="text"
            class="form-control text-uppercase @error('codigo') is-invalid @enderror"
            id="codigo"
            name="codigo"
            value="{{ old('codigo', $moneda->codigo ?? '') }}"
            maxlength="3"
            placeholder="EUR"
            autocomplete="off"
            required
        >

        @error('codigo')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            Tres letras, como NIO, USD o EUR.
        </div>
    </div>

    <div class="col-md-8">
        <label for="nombre" class="form-label">
            Moneda <span class="text-danger">*</span>
        </label>

        <input
            type="text"
            class="form-control @error('nombre') is-invalid @enderror"
            id="nombre"
            name="nombre"
            value="{{ old('nombre', $moneda->nombre ?? '') }}"
            maxlength="50"
            placeholder="Euros"
            required
        >

        @error('nombre')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            Cómo se escribe en el papel, con su tilde.
        </div>
    </div>

    <div class="col-md-4">
        <label for="simbolo" class="form-label">
            Símbolo
        </label>

        <input
            type="text"
            class="form-control @error('simbolo') is-invalid @enderror"
            id="simbolo"
            name="simbolo"
            value="{{ old('simbolo', $moneda->simbolo ?? '') }}"
            maxlength="10"
            placeholder="€"
        >

        @error('simbolo')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            Opcional. Sale al lado del nombre en los desplegables.
        </div>
    </div>

    <div class="col-md-4">
        <div class="form-check form-switch mt-md-4">
            <input
                class="form-check-input @error('es_moneda_base') is-invalid @enderror"
                type="checkbox"
                role="switch"
                name="es_moneda_base"
                id="es_moneda_base"
                value="1"
                @checked(old('es_moneda_base', $moneda->es_moneda_base ?? false))
            >

            <label class="form-check-label" for="es_moneda_base">
                Es la moneda base
            </label>

            @error('es_moneda_base')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-text mt-1">
            Solo puede haber una, y es en la que está el taller. Si se marca
            esta, las demás dejan de serlo.
        </div>
    </div>

    <div class="col-md-4">
        <div class="form-check form-switch mt-md-4">
            <input
                class="form-check-input @error('estado') is-invalid @enderror"
                type="checkbox"
                role="switch"
                name="estado"
                id="estado"
                value="1"
                @checked(old('estado', $moneda->estado ?? true))
            >

            <label class="form-check-label" for="estado">
                Activa
            </label>

            @error('estado')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-text mt-1">
            Una moneda que se usa no se borra: se desactiva, y así deja de salir
            en los desplegables sin tocar lo que ya se registró con ella.
        </div>
    </div>

</div>

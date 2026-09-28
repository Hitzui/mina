{{--
    El formulario de un tipo de cambio.

    Va dentro de un modal, en la pantalla de la lista. Son cuatro campos y
    una pantalla entera para eso seria mas ruido que ayuda: el usuario esta
    mirando una serie de fechas, elige el dia que le falta y lo rellena sin
    salir de ahi.

    El valor no tiene decimales fijos en el html porque depende de la moneda
    y de como venga en el archivo. Se manda con cuatro decimales, que es lo
    que publica el banco, y se deja que el campo acepte mas: hay monedas que
    cotizan a cuatro y hay pares que se mueven en el quinto.

    El campo del valor es numerico y sin el atributo de paso, para que el
    separador decimal lo ponga el navegador segun como esta la maquina y no
    segun como se le antoje al servidor. El servidor acepta coma o punto, y
    el javascript avisa de que solo uno de los dos puede ser el decimal, que
    es donde la gente se equivoca al teclear.
--}}
<div class="row g-3">

    <div class="col-md-6">
        <label for="fecha" class="form-label">
            Día <span class="text-danger">*</span>
        </label>

        <input
            type="date"
            class="form-control @error('fecha') is-invalid @enderror"
            id="fecha"
            name="fecha"
            value="{{ old('fecha', $tipoCambio->fecha ?? '') }}"
            required
        >

        @error('fecha')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            El tipo de cambio de ese día, tal como lo publica el banco.
        </div>
    </div>

    <div class="col-md-6">
        <label for="moneda_id" class="form-label">
            Moneda <span class="text-danger">*</span>
        </label>

        <select
            class="form-select select2 @error('moneda_id') is-invalid @enderror"
            id="moneda_id"
            name="moneda_id"
            required
        >
            <option value="">Elija la moneda</option>

            @foreach($monedas as $moneda)
                <option
                    value="{{ $moneda->id }}"
                    @selected((int) old('moneda_id', $tipoCambio->moneda_id ?? 0) === (int) $moneda->id)
                >
                    {{ $moneda->nombre }} ({{ $moneda->simbolo ?: $moneda->codigo }})
                </option>
            @endforeach
        </select>

        @error('moneda_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            Cuántos córdobas vale una unidad de esta moneda ese día.
        </div>
    </div>

    <div class="col-12">
        <label for="valor" class="form-label">
            Valor del tipo de cambio <span class="text-danger">*</span>
        </label>

        <input
            type="number"
            step="0.0001"
            min="0.0001"
            class="form-control @error('valor') is-invalid @enderror"
            id="valor"
            name="valor"
            value="{{ old('valor', $tipoCambio->valor ?? '') }}"
            placeholder="36.5824"
            required
        >

        @error('valor')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            Con punto o con coma, pero solo uno de los dos como decimal.
            Un tipo de cambio de cero no vale: es la forma de decir que no se
            sabe, y deja las compras sin convertir.
        </div>
    </div>

    <div class="col-12">
        <label for="fuente" class="form-label">
            Fuente
        </label>

        <input
            type="text"
            class="form-control @error('fuente') is-invalid @enderror"
            id="fuente"
            name="fuente"
            value="{{ old('fuente', $tipoCambio->fuente ?? '') }}"
            maxlength="100"
            placeholder="Banco Central de Nicaragua"
        >

        @error('fuente')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="observaciones" class="form-label">
            Observaciones
        </label>

        <input
            type="text"
            class="form-control @error('observaciones') is-invalid @enderror"
            id="observaciones"
            name="observaciones"
            value="{{ old('observaciones', $tipoCambio->observaciones ?? '') }}"
            maxlength="255"
            placeholder="Corrección a mano, día festivo, lo que haga falta"
        >

        @error('observaciones')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

</div>

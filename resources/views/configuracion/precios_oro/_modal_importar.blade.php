{{--
    La importacion del mes del precio del oro.

    Va en un modal y no en una pantalla aparte por lo mismo que en el tipo de
    cambio: son tres campos y un archivo, y la serie esta en la lista de al
    lado. La razon de que sea un modal y no un paso dentro del alta es que leer
    el archivo y escribirlo son dos cosas distintas que pasan en dias
    distintos: hoy se sube el archivo, manana el de octubre.

    El proceso va en dos pasos y el primero no escribe nada:

    1. Se elige el archivo y se pulsa "Ver qué trae". No se guarda nada.
    2. Sale cuantos dias hay, cuantos son nuevos, cuantos cambian de valor y
       de cuanto a cuanto, y cuales eran ceros que no van a pisar un precio que
       ya se sabia. Solo entonces aparece "Importar".

    Sin el paso 1, treinta dias de precios entran sin que nadie los haya visto,
    y un archivo mal leido no se nota hasta que una valoracion sale disparada.
--}}
<div class="modal fade" id="modalImportarPrecioOro" tabindex="-1" aria-labelledby="modalImportarPrecioOroLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">

            <div class="modal-header">
                <h5 class="modal-title" id="modalImportarPrecioOroLabel">Importar el mes del precio del oro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">

                <div class="alert alert-info d-flex align-items-start">
                    <i class="bi bi-info-circle me-2 mt-1"></i>
                    <div>
                        El archivo trae dos columnas: la fecha y el precio. La
                        <strong>unidad</strong> y la <strong>moneda</strong> se
                        eligen aquí, no salen del archivo, porque el archivo no
                        las dice. Elegirlas mal es tener un mes entero de onzas
                        guardadas como si fueran gramos.
                    </div>
                </div>

                <form id="formImportarPrecioOro"
                      method="POST"
                      action="{{ route('configuracion.precios_oro.importar') }}"
                      enctype="multipart/form-data">

                    @csrf

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label for="archivoPrecioOro" class="form-label">
                                Archivo de Excel <span class="text-danger">*</span>
                            </label>

                            <input
                                type="file"
                                class="form-control @error('archivo') is-invalid @enderror"
                                id="archivoPrecioOro"
                                name="archivo"
                                accept=".xlsx,.xls,.csv"
                                required
                            >

                            @error('archivo')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror

                            <div class="form-text">
                                <a href="{{ route('configuracion.precios_oro.plantilla') }}">
                                    <i class="bi bi-download me-1"></i>
                                    Descargar ejemplo
                                </a>
                                — con los días del mes que hay que cargar.
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label for="unidadImportar" class="form-label">
                                Unidad <span class="text-danger">*</span>
                            </label>

                            <select
                                class="form-select @error('unidad') is-invalid @enderror"
                                id="unidadImportar"
                                name="unidad"
                                required
                            >
                                <option value="">Elija</option>

                                @foreach($unidades as $valor => $texto)
                                    <option value="{{ $valor }}" @selected($valor === 'gramo')>
                                        {{ $texto }}
                                    </option>
                                @endforeach
                            </select>

                            @error('unidad')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label for="monedaImportar" class="form-label">
                                Moneda <span class="text-danger">*</span>
                            </label>

                            <select
                                class="form-select select2 @error('moneda_id') is-invalid @enderror"
                                id="monedaImportar"
                                name="moneda_id"
                                required
                            >
                                <option value="">Elija</option>

                                @foreach($monedas as $moneda)
                                    <option
                                        value="{{ $moneda->id }}"
                                        @selected(! $moneda->es_moneda_base)
                                    >
                                        {{ $moneda->nombre }} ({{ $moneda->simbolo ?: $moneda->codigo }})
                                    </option>
                                @endforeach
                            </select>

                            @error('moneda_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="fuenteImportar" class="form-label">
                                Fuente
                            </label>

                            <input
                                type="text"
                                class="form-control @error('fuente') is-invalid @enderror"
                                id="fuenteImportar"
                                name="fuente"
                                maxlength="150"
                                placeholder="Banco Central de Nicaragua"
                            >

                            @error('fuente')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror

                            <div class="form-text">
                                Se escribe en los días nuevos. En los que ya
                                estaban, solo se cambia si lo rellenas aquí: si
                                un día lo corregiste a mano y anotaste de dónde
                                lo sacaste, el archivo no se lleva por delante
                                lo que sabes tú.
                            </div>
                        </div>

                    </div>

                </form>

                <div id="importarError" class="alert alert-danger mt-3 d-none"></div>

                {{-- El resumen de lo que se ha leido. Empieza escondido. --}}
                <div id="importarResumen" class="d-none">

                    <div id="importarAviso" class="alert alert-success"></div>

                    <h6 class="mt-3 mb-2">Días que cambian de precio</h6>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered w-100 align-middle">
                            <thead>
                                <tr>
                                    <th class="font-monospace">Día</th>
                                    <th class="text-end">Lo que hay</th>
                                    <th class="text-end">Lo que trae el archivo</th>
                                </tr>
                            </thead>
                            <tbody id="importarFilas"></tbody>
                        </table>
                    </div>

                    <div id="importarZeros"></div>
                    <div id="importarDescartadas"></div>

                </div>

                <div class="alert alert-warning mt-3 mb-0 d-flex align-items-start">
                    <i class="bi bi-exclamation-triangle me-2 mt-1"></i>
                    <div>
                        Un día con precio en <strong>cero</strong> en el archivo
                        no pisa un precio que ya se sepa. El cero aquí quiere
                        decir «de ese día no se sabe», y si el archivo lo trae es
                        porque la celda venía vacía. Ese día se queda con el
                        precio que tenía y se cuenta aparte, para que se vea.
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>

                <button
                    type="button"
                    class="btn btn-outline-success"
                    id="btnVerPrecioOro"
                >
                    <i class="bi bi-eye me-1"></i>
                    Ver qué trae
                </button>

                <button
                    type="button"
                    class="btn btn-success d-none"
                    id="btnImportarPrecioOro"
                >
                    <i class="bi bi-check-lg me-1"></i>
                    Importar
                </button>
            </div>

        </div>
    </div>
</div>

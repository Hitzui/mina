{{--
    Modal de la importacion del mes.

    Va en dos pasos a proposito, y el segundo no se ve hasta que el primero
    ha leido el archivo:

      1. Se elige el archivo y la moneda, y se pulsa "Ver el mes".
      2. Sale cuantos dias hay, cuantos son nuevos, cuantos ya estaban y
         cuales cambian de valor. Solo entonces aparece "Importar".

    Lo que hace el primer paso es LEER el archivo y contarlo. No escribe
    nada. Importar treinta dias de conversiones sin haber visto ni uno es la
    forma rapida de Carryar el mes entero, y un archivo mal ledo no se nota
    hasta que un cuadre no cuadra, que es cuando ya es caro deshacerlo.

    El boton de plantilla deja un Excel de ejemplo, para que el formato se
    vea en lugar de tener que acordarse. El archivo del banco no se lee
    tal cual: trae encabezados, notas al pie y el tipo de cambio de otras
    monedas de lado, y el usuario lo convierte a dos columnas antes de
    subirlo.

    El aviso sobre el oro va puesto aqui, en la pantalla de la importacion, y
    no en la de ayuda, porque es justo aqui donde el usuario va a decidir si
    el mes esta completo. El precio del oro va en su propia tabla y este
    archivo no lo trae: si lo necesita, se carga aparte.
--}}
<div class="modal fade" id="modalImportarTipoCambio" tabindex="-1" aria-labelledby="modalImportarLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-white">

            <div class="modal-header">
                <h5 class="modal-title" id="modalImportarLabel">
                    <i class="bi bi-file-earmark-excel me-1"></i>
                    Importar el tipo de cambio del mes
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">

                {{--
                    El error va aqui arriba y no pegado a un campo, porque lo
                    que puede fallar es el archivo entero —que no tiene
                    encabezados, que esta protegido, que no es un Excel— y no
                    un dato de un formulario. Ponerlo junto a un campo haria
                    pensar que el campo es lo que esta mal.
                --}}
                <div id="importarError" class="alert alert-danger d-none"></div>

                <form id="formImportarTipoCambio"
                      method="POST"
                      action="{{ route('configuracion.tipos_cambio.importar') }}"
                      enctype="multipart/form-data">

                    @csrf

                    <div class="mb-3">
                        <label for="archivoTipoCambio" class="form-label">
                            Archivo de Excel <span class="text-danger">*</span>
                        </label>

                        <input
                            type="file"
                            class="form-control"
                            id="archivoTipoCambio"
                            name="archivo"
                            accept=".xlsx,.xls,.xlsm,.csv,.ods"
                            required
                        >

                        <div class="form-text">
                            Dos columnas: una de fecha y otra de valor, con el
                            nombre de cada una en la fila de arriba.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="monedaImportar" class="form-label">
                            ¿De qué moneda es este tipo de cambio? <span class="text-danger">*</span>
                        </label>

                        <select
                            class="form-select select2"
                            id="monedaImportar"
                            name="moneda_id"
                            required
                        >
                            <option value="">Elija la moneda</option>

                            @foreach($monedas as $moneda)
                                <option value="{{ $moneda->id }}">
                                    {{ $moneda->nombre }} ({{ $moneda->simbolo ?: $moneda->codigo }})
                                </option>
                            @endforeach
                        </select>

                        <div class="form-text">
                            El archivo no dice de qué moneda es, y equivocarse
                            aquí es tener un mes de dólares guardado como
                            córdobas. Si el archivo fuera del 1 al 31 de
                            septiembre, es el del dólar.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="fuenteImportar" class="form-label">
                            Fuente
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="fuenteImportar"
                            name="fuente"
                            maxlength="100"
                            placeholder="Banco Central de Nicaragua"
                        >

                        <div class="form-text">
                            Se escribe en los días nuevos. En los que ya
                            estaban, solo se cambia si lo rellenas aquí: si un
                            día lo corregiste a mano y anotaste de dónde lo
                            sacaste, el archivo no se lleva por delante lo que
                            sabes tú.
                        </div>
                    </div>

                </form>

                {{-- El resumen de lo que se ha leido. Empieza escondido. --}}
                <div id="importarResumen" class="d-none">

                    <div id="importarAviso" class="alert alert-success"></div>

                    <h6 class="mt-3 mb-2">Días que cambian de valor</h6>

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

                    <div id="importarDescartadas"></div>

                </div>

                <div class="alert alert-info mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Este archivo solo trae el tipo de cambio. El
                    <strong>precio del oro va aparte</strong>, en su propia
                    tabla, y este archivo no lo trae: si necesitas el oro de
                    cada día, hay que cargarlo en su pantalla. Importar aquí
                    no lo deja en cero ni lo pisa.
                </div>

            </div>

            <div class="modal-footer justify-content-between">
                {{--
                    La url va en un data-url porque el boton lo maneja un
                    javascript que el navegador lee tal cual, sin pasar por
                    blade. Si la ruta se escribiera dentro del javascript, un
                    cambio de prefijo daria un 404 en silencio.
                --}}
                <button
                    type="button"
                    class="btn btn-link btn-sm"
                    id="btnPlantillaTipoCambio"
                    data-url="{{ route('configuracion.tipos_cambio.plantilla') }}"
                >
                    <i class="bi bi-download me-1"></i>
                    Descargar ejemplo
                </button>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>

                    <button type="button" class="btn btn-light" id="btnVerTipoCambio">
                        Ver el mes
                    </button>

                    {{--
                        Este boton no aparece hasta que se ha leido el
                        archivo. Antes de eso no hay nada que confirmar, y un
                        boton de "Importar" que ya esta puesto invita a
                        importarlo sin haber mirado.
                    --}}
                    <button type="button" class="btn btn-primary d-none" id="btnImportarTipoCambio">
                        <i class="bi bi-check2 me-1"></i>
                        <span id="textoImportarTipoCambio">Importar</span>
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

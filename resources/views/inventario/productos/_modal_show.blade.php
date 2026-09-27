{{--
    Ficha del material, en modal.

    Los valores los rellena el javascript con lo que devuelve la ficha en
    JSON. Los ids tienen el prefijo "show" para no chocar con los del
    formulario de edicion, que esta en la misma pagina y usa los mismos
    nombres de campo.
--}}
<div class="modal fade" id="modalShowProducto" tabindex="-1" aria-labelledby="modalShowProductoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">

            <div class="modal-header">
                <h5 class="modal-title" id="modalShowProductoLabel">Ficha del material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">

                <div class="row g-3">

                    <div class="col-md-7">
                        <label class="form-label text-muted">Material</label>
                        <div class="form-control bg-light fw-semibold" id="showNombre">—</div>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label text-muted">Código</label>
                        <div class="form-control bg-light font-monospace" id="showCodigo">—</div>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label text-muted">Estado</label>
                        <div class="pt-2" id="showEstado">—</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Unidad</label>
                        <div class="form-control bg-light" id="showUnidad">—</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted">Categoría</label>
                        <div class="form-control bg-light" id="showCategoria">—</div>
                    </div>

                    {{-- Los tres numeros del almacen --}}
                    <div class="col-md-3">
                        <label class="form-label text-muted">Existencia</label>
                        <div class="form-control bg-light fw-semibold fs-5" id="showExistencia">—</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted">Costo promedio</label>
                        <div class="form-control bg-light fw-semibold fs-5" id="showCostoPromedio">—</div>
                        <div class="form-text">
                            Lo que fija el valor de cada salida del almacén.
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted">Valor en almacén</label>
                        <div class="form-control bg-light fw-semibold fs-5" id="showValor">—</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted">Mínimo</label>
                        <div class="form-control bg-light" id="showMinimo">—</div>
                    </div>

                    <div class="col-12" id="showAvisoContenedor"></div>

                    <div class="col-12">
                        <label class="form-label text-muted">Descripción</label>
                        <div class="form-control bg-light" id="showDescripcion">—</div>
                    </div>

                </div>

                <hr class="my-4">

                {{-- El kardex reciente --}}
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Últimos movimientos</h6>
                    <a href="{{ route('inventario.movimientos.index') }}" class="btn btn-sm btn-light">
                        Ver el kardex completo
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="tablaMovimientosProducto">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Total</th>
                                <th>Destino</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">
                                    Cargando...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="form-text">
                    <i class="bi bi-info-circle me-1"></i>
                    Se muestran los 10 últimos movimientos. El historial
                    completo está en la pantalla del almacén.
                </p>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
            </div>

        </div>
    </div>
</div>

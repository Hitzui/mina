{{--
    Detalle de un movimiento de costo. El js lo llena con lo que devuelve
    la ruta show en JSON.
--}}
<div class="modal fade" id="modalShowCosto" tabindex="-1" aria-labelledby="modalShowCostoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <div class="modal-header">
                <h5 class="modal-title" id="modalShowCostoLabel">Detalle del costo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label text-muted">Categoría</label>
                        <input type="text" id="showCostoCategoria" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Fecha</label>
                        <input type="text" id="showCostoFecha" class="form-control" value="" readonly>
                    </div>

                    <div class="col-12">
                        <label class="form-label text-muted">Descripción</label>
                        <input type="text" id="showCostoDescripcion" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Cantidad</label>
                        <input type="text" id="showCostoCantidad" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Costo unitario</label>
                        <input type="text" id="showCostoUnitario" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Total</label>
                        <input type="text" id="showCostoTotal" class="form-control fw-semibold" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Total en NIO</label>
                        <input type="text" id="showCostoTotalNio" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Moneda</label>
                        <input type="text" id="showCostoMoneda" class="form-control" value="" readonly>
                    </div>

                    <div class="col-12">
                        <label class="form-label text-muted">Observaciones</label>
                        <textarea id="showCostoObservaciones" class="form-control" rows="2" readonly></textarea>
                    </div>

                    <div class="col-12">
                        <div class="alert alert-light border mb-0 small">
                            <i class="bi bi-info-circle me-1"></i>
                            El total y su equivalente en NIO se calcularon al
                            registrar el movimiento, con el tipo de cambio de
                            esa fecha. Si después se corrige el tipo de cambio,
                            esta cifra no cambia.
                        </div>
                    </div>

                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

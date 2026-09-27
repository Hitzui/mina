{{--
    Detalle de un costo general de la orden. El js lo llena con lo que
    devuelve la ruta show en JSON.
--}}
<div class="modal fade" id="modalShowCostoOrden" tabindex="-1" aria-labelledby="modalShowCostoOrdenLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <div class="modal-header">
                <h5 class="modal-title" id="modalShowCostoOrdenLabel">Detalle del costo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label text-muted">Categoría</label>
                        <input type="text" id="showCostoOrdenCategoria" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Fecha</label>
                        <input type="text" id="showCostoOrdenFecha" class="form-control" value="" readonly>
                    </div>

                    <div class="col-12">
                        <label class="form-label text-muted">Descrição</label>
                        <input type="text" id="showCostoOrdenDescripcion" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Cantidad</label>
                        <input type="text" id="showCostoOrdenCantidad" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Costo unitario</label>
                        <input type="text" id="showCostoOrdenUnitario" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Total</label>
                        <input type="text" id="showCostoOrdenTotal" class="form-control fw-semibold" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Total en NIO</label>
                        <input type="text" id="showCostoOrdenTotalNio" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Moneda</label>
                        <input type="text" id="showCostoOrdenMoneda" class="form-control" value="" readonly>
                    </div>

                    <div class="col-12">
                        <label class="form-label text-muted">Observaciones</label>
                        <textarea id="showCostoOrdenObservaciones" class="form-control" rows="2" readonly></textarea>
                    </div>

                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

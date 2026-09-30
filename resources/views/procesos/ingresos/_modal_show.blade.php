{{--
    Detalle de un ingreso. El javascript lo llena con lo que devuelve la ruta
    show en JSON.

    Los importes en cordoba van con su tipo de cambio al lado, y eso no es
    adorno: un total en cordoba sin el cambio con el que salio no se puede
    comprobar. Con el, se multiplica y se sabe si la cifra era esa, que es
    justo lo que se pregunta al ver un ingreso viejo cuyo dia ya paso y que
    nadie va a volver a mirar.
--}}
<div class="modal fade" id="modalShowIngreso" tabindex="-1" aria-labelledby="modalShowIngresoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <div class="modal-header">
                <h5 class="modal-title" id="modalShowIngresoLabel">Detalle del ingreso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label text-muted">Tipo de ingreso</label>
                        <input type="text" id="showIngresoTipo" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Fecha</label>
                        <input type="text" id="showIngresoFecha" class="form-control" value="" readonly>
                    </div>

                    <div class="col-12">
                        <label class="form-label text-muted">Descripción</label>
                        <input type="text" id="showIngresoDescripcion" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Cantidad</label>
                        <input type="text" id="showIngresoCantidad" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Unidad</label>
                        <input type="text" id="showIngresoUnidad" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Precio unitario</label>
                        <input type="text" id="showIngresoUnitario" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Total</label>
                        <input type="text" id="showIngresoTotal" class="form-control fw-semibold" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Total en NIO</label>
                        <input type="text" id="showIngresoTotalNio" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Moneda</label>
                        <input type="text" id="showIngresoMoneda" class="form-control" value="" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Observaciones</label>
                        <textarea id="showIngresoObservaciones" class="form-control" rows="2" readonly></textarea>
                    </div>

                    {{--
                        El equivalente en cordoba queda guardado con el tipo de
                        cambio del dia, y se enseña el que se aplico. El ingreso
                        no lo recalcula al abrirse: el tipo de cambio se puede
                        corregir despues y el ingreso es un documento, no una
                        consulta.
                    --}}
                    <div class="col-12" id="showIngresoAviso"></div>

                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

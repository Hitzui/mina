<div class="modal fade" id="modalEmpleadoPago" tabindex="-1" aria-labelledby="modalEmpleadoPagoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="modalEmpleadoPagoLabel">
                        <i class="bi bi-cash-stack me-2"></i> Detalle de tarifa
                    </h5>
                    <div id="empleadoPagoEmpleado" class="text-muted small mt-1">—</div>
                </div>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <div id="empleadoPagoLoading" class="text-center py-4 d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <div class="text-muted mt-2">Cargando información...</div>
                </div>

                <div id="empleadoPagoContenido">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Tipo de pago</div>
                            <div id="empleadoPagoTipo" class="fw-semibold">—</div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Estado</div>
                            <div id="empleadoPagoEstado">—</div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Tarifa</div>
                            <div id="empleadoPagoTarifa" class="fw-semibold fs-5">—</div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Moneda</div>
                            <div id="empleadoPagoMoneda" class="fw-semibold">—</div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Fecha de inicio</div>
                            <div id="empleadoPagoFechaInicio" class="fw-semibold">—</div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Fecha de finalización</div>
                            <div id="empleadoPagoFechaFin" class="fw-semibold">—</div>
                        </div>

                        <div class="col-12">
                            <div class="text-muted small">Observaciones</div>
                            <div id="empleadoPagoObservaciones" class="border rounded p-3">—</div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Creado</div>
                            <div id="empleadoPagoCreado">—</div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Última actualización</div>
                            <div id="empleadoPagoActualizado">—</div>
                        </div>
                    </div>
                </div>

                <div id="empleadoPagoError" class="alert alert-danger d-none mt-3">
                    No fue posible cargar la información de la tarifa.
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i> Cerrar
                </button>

                <a href="#" id="btnEditarEmpleadoPago" class="btn btn-warning">
                    <i class="bi bi-pencil me-1"></i> Editar
                </a>
            </div>

        </div>
    </div>
</div>

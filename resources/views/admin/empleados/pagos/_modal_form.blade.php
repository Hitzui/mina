<div class="modal fade" id="modalEmpleadoPagoForm" tabindex="-1" aria-labelledby="modalEmpleadoPagoFormLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="modalEmpleadoPagoFormLabel">
                        <i class="bi bi-cash-stack me-2"></i>
                        <span id="empleadoPagoFormTitulo">Nueva tarifa</span>
                    </h5>
                    <div id="empleadoPagoFormEmpleado" class="text-muted small mt-1"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <div id="empleadoPagoFormLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <div class="text-muted mt-2">Cargando formulario...</div>
                </div>

                <div id="empleadoPagoFormContenido" class="d-none"></div>

                <div id="empleadoPagoFormError" class="alert alert-danger d-none">
                    No fue posible cargar el formulario.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade"
     id="modalSeleccionarEmpleado"
     tabindex="-1"
     aria-labelledby="modalSeleccionarEmpleadoLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-light-info">
            <div class="modal-header">
                <h5 class="modal-title" id="modalSeleccionarEmpleadoLabel">Seleccionar empleado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    {{ $empleadosSelectorDataTable->html()->table(['class' => 'table table-hover align-middle w-100'], true) }}
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarEmpleado" disabled>
                    <i class="bi bi-check-lg me-1"></i> Seleccionar
                </button>
            </div>
        </div>
    </div>
</div>

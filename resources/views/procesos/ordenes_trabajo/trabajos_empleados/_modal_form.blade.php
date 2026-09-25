<div class="modal fade" id="modalTrabajoEmpleado" tabindex="-1" aria-labelledby="modalTrabajoEmpleadoLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-white">
            <form id="formTrabajoEmpleado"
                  method="POST"
                  action="{{ route('procesos.ordenes_trabajo.trabajos_empleados.store', $ordenTrabajo) }}"
                  data-store-url="{{ route('procesos.ordenes_trabajo.trabajos_empleados.store', $ordenTrabajo) }}"
                  data-update-url="{{ route('procesos.ordenes_trabajo.trabajos_empleados.update', [$ordenTrabajo, '__ID__']) }}"
                  data-tarifa-url="{{ route('procesos.ordenes_trabajo.trabajos_empleados.tarifa', $ordenTrabajo) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTrabajoEmpleadoLabel">Nuevo trabajo de empleado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    @include('procesos.ordenes_trabajo.trabajos_empleados._form')
                    <div id="trabajoEmpleadoError" class="alert alert-danger mt-3 d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarTrabajoEmpleado">
                        <i class="bi bi-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

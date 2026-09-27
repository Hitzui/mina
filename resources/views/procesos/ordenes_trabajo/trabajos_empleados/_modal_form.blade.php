{{--
    Modal del trabajo de empleado.

    Todas las urls llevan el proceso, porque el trabajo cuelga del proceso.
    El js del modal las lee de estos data-*, asi que no hay que tocarlos
    al cambiar de proceso.
--}}
<div class="modal fade" id="modalTrabajoEmpleado" tabindex="-1" aria-labelledby="modalTrabajoEmpleadoLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-white">
            <form id="formTrabajoEmpleado"
                  method="POST"
                  action="{{ route('procesos.ordenes_trabajo.procesos.trabajos_empleados.store', [$ordenTrabajo, $procesoOrden]) }}"
                  data-store-url="{{ route('procesos.ordenes_trabajo.procesos.trabajos_empleados.store', [$ordenTrabajo, $procesoOrden]) }}"
                  data-update-url="{{ route('procesos.ordenes_trabajo.procesos.trabajos_empleados.update', [$ordenTrabajo, $procesoOrden, '__ID__']) }}"
                  data-tarifa-url="{{ route('procesos.ordenes_trabajo.procesos.trabajos_empleados.tarifa', [$ordenTrabajo, $procesoOrden]) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTrabajoEmpleadoLabel">Nuevo trabajo de empleado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    {{--
                        El proceso ya no se elige aqui: viene de la url de la
                        pantalla, asi que se muestra solo como referencia.
                    --}}
                    <div class="alert alert-light border mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-diagram-3 text-primary"></i>
                        <span>
                            Proceso:
                            <strong>{{ $procesoOrden->nombre_completo }}</strong>
                            <span class="text-muted d-block small">
                                El trabajo queda registrado dentro de este proceso.
                            </span>
                        </span>
                    </div>

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

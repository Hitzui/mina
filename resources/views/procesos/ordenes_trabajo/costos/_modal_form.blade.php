{{--
    Modal del costo general de la orden.

    No lleva proceso a proposito: por definicion un costo general no
    pertenece a ninguno, y es lo que distingue esta pantalla de la del
    proceso. Un mismo costo no se puede cargar en las dos.
--}}
<div class="modal fade" id="modalCostoOrden" tabindex="-1" aria-labelledby="modalCostoOrdenLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <form id="formCostoOrden"
                  method="POST"
                  action="{{ route('procesos.ordenes_trabajo.costos.store', $ordenTrabajo) }}"
                  data-store-url="{{ route('procesos.ordenes_trabajo.costos.store', $ordenTrabajo) }}"
                  data-update-url="{{ route('procesos.ordenes_trabajo.costos.update', [$ordenTrabajo, '__ID__']) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCostoOrdenLabel">Registrar costo de la orden</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">

                    <div class="alert alert-light border mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-clipboard2-check text-primary"></i>
                        <span>
                            Orden:
                            <strong>{{ $ordenTrabajo->codigo }}</strong>
                            <span class="text-muted d-block small">
                                Aquí van los costos de toda la orden: alquiler,
                                transporte, un insumo suelto. Los que sí
                                pertenecen a un proceso se registran en la
                                pantalla de ese proceso.
                            </span>
                        </span>
                    </div>

                    @include('procesos.ordenes_trabajo.costos._form')

                    <div id="costoOrdenError" class="alert alert-danger mt-3 d-none"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarCostoOrden">
                        <i class="bi bi-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

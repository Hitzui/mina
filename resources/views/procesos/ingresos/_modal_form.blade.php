{{--
    Modal del alta y la edicion de un ingreso de la orden.

    No lleva la orden en el titulo del boton ni un selector de orden, porque la
    orden es la de la ficha en la que se abre. Es lo mismo que hacen los costos
    generales: la url lleva la orden y el modal no la pide, que si la pidiera
    habria dos caminos para meter un ingreso en una orden y solo uno estaria
    comprobado.
--}}
<div class="modal fade" id="modalIngreso" tabindex="-1" aria-labelledby="modalIngresoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <form id="formIngreso"
                  method="POST"
                  action="{{ route('procesos.ordenes_trabajo.ingresos.store', $ordenTrabajo) }}"
                  data-store-url="{{ route('procesos.ordenes_trabajo.ingresos.store', $ordenTrabajo) }}"
                  data-update-url="{{ route('procesos.ordenes_trabajo.ingresos.update', [$ordenTrabajo, '__ID__']) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalIngresoLabel">Registrar ingreso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">

                    <div class="alert alert-light border mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-cash-coin text-primary"></i>
                        <span>
                            Orden:
                            <strong>{{ $ordenTrabajo->codigo }}</strong>
                            <span class="text-muted d-block small">
                                Aquí entra el dinero de esta orden: lo que se le
                                cobra al cliente. Lo que sale son los costos, y
                                van en su propia tabla de arriba.
                            </span>
                        </span>
                    </div>

                    @include('procesos.ingresos._form')

                    <div id="ingresoError" class="alert alert-danger mt-3 d-none"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarIngreso">
                        <i class="bi bi-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

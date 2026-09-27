{{--
    Modal del uso de un equipo en el proceso.

    Todas las urls llevan el proceso, porque el uso cuelga del proceso.
    El js del modal las lee de estos data-*, asi que no hay que tocarlos
    al cambiar de proceso.
--}}
<div class="modal fade" id="modalUsoEquipo" tabindex="-1" aria-labelledby="modalUsoEquipoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <form id="formUsoEquipo"
                  method="POST"
                  action="{{ route('procesos.ordenes_trabajo.procesos.equipos.store', [$ordenTrabajo, $procesoOrden]) }}"
                  data-store-url="{{ route('procesos.ordenes_trabajo.procesos.equipos.store', [$ordenTrabajo, $procesoOrden]) }}"
                  data-update-url="{{ route('procesos.ordenes_trabajo.procesos.equipos.update', [$ordenTrabajo, $procesoOrden, '__ID__']) }}"
                  data-depreciacion-url="{{ route('procesos.ordenes_trabajo.procesos.equipos.depreciacion', [$ordenTrabajo, $procesoOrden]) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalUsoEquipoLabel">Asignar equipo al proceso</h5>
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
                                El equipo queda registrado dentro de este proceso,
                                y su depreciación entra al costo del proceso.
                            </span>
                        </span>
                    </div>

                    @include('procesos.procesos_orden.equipos._form')

                    <div id="usoEquipoError" class="alert alert-danger mt-3 d-none"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarUsoEquipo">
                        <i class="bi bi-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

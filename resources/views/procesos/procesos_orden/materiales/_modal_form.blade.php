{{--
    Modal del consumo de material de un proceso.

    Solo crea, no edita: corregir un consumo quiere decir borrarlo y
    volverlo a registrar, para que el almacen y el costo del proceso se
    corrigan a la vez. Por eso no hay data-update-url aqui, a diferencia del
    modal de costos.
--}}
<div class="modal fade" id="modalMaterial" tabindex="-1" aria-labelledby="modalMaterialLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">
            <form id="formMaterial"
                  method="POST"
                  action="{{ route('procesos.ordenes_trabajo.procesos.materiales.store', [$ordenTrabajo, $procesoOrden]) }}"
                  data-store-url="{{ route('procesos.ordenes_trabajo.procesos.materiales.store', [$ordenTrabajo, $procesoOrden]) }}">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="modalMaterialLabel">Registrar consumo de material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">

                    <div class="alert alert-light border mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-diagram-3 text-primary"></i>
                        <span>
                            Proceso:
                            <strong>{{ $procesoOrden->nombre_completo }}</strong>
                            <span class="text-muted d-block small">
                                El material sale del almacén y su costo entra a
                                este proceso como concepto automático.
                            </span>
                        </span>
                    </div>

                    @if($productos->isEmpty())

                        {{--
                            Sin materiales no se puede consumir. Se dice aqui y
                            no se deja un desplegable vacio, que se llenaria de
                            errores al enviarlo.
                        --}}
                        <div class="alert alert-warning mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Todavía no hay ningún material dado de alta en el almacén.
                            <a href="{{ route('inventario.productos.create') }}" target="_blank">
                                Cree el primero
                            </a>
                            y registre su entrada para poder consumirlo.
                        </div>

                    @else

                        @include('procesos.procesos_orden.materiales._form')

                        <div id="materialError" class="alert alert-danger mt-3 d-none"></div>

                    @endif

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>

                    @unless($productos->isEmpty())
                        <button type="submit" class="btn btn-primary" id="btnGuardarMaterial">
                            <i class="bi bi-save me-1"></i> Guardar
                        </button>
                    @endunless
                </div>
            </form>
        </div>
    </div>
</div>

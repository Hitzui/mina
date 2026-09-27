{{--
    El boton de eliminar un consumo de material de un proceso.

    El texto va en data-confirm-title, -text y -button. El
    data-confirm-delete a secas es solo la bandera que le dice al
    javascript que el boton pide confirmacion; el mensaje ahi se ignora.

    Borrar un consumo hace dos cosas: devuelve el material al almacen y
    saca la cantidad del costo del proceso. Las dos van en el texto,
    porque una persona que borra por error un consumo de cemento quiere
    saber las dos, no solo que se quita una fila.
--}}
<td>
    <div class="d-flex justify-content-center gap-1">

        <form
            method="POST"
            action="{{ route(
                'procesos.ordenes_trabajo.procesos.materiales.destroy',
                [$ordenTrabajoId, $procesoOrdenId, $movimiento->id]
            ) }}"
            class="d-inline"
            data-confirm-delete
            data-confirm-title="¿Eliminar este consumo de material?"
            data-confirm-text="El material volverá al almacén y el importe se le restará al costo del proceso. Si el consumo se registró por error, esta es la manera de corregirlo."
            data-confirm-button="Sí, eliminar"
        >
            @csrf
            @method('DELETE')

            <button type="submit" class="btn btn-sm btn-light" title="Eliminar">
                <i class="bi bi-trash"></i>
            </button>
        </form>

    </div>
</td>

<td>
    <div class="d-flex justify-content-center gap-1">

        <form
            method="POST"
            action="{{ route(
                'procesos.ordenes_trabajo.procesos.materiales.destroy',
                [$ordenTrabajoId, $procesoOrdenId, $movimiento->id]
            ) }}"
            class="d-inline"
            data-confirm-delete="¿Eliminar este consumo? El material volverá al almacén y el costo del proceso se recalculará."
        >
            @csrf
            @method('DELETE')

            <button type="submit" class="btn btn-sm btn-light" title="Eliminar">
                <i class="bi bi-trash"></i>
            </button>
        </form>

    </div>
</td>

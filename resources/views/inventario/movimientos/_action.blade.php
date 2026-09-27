<td>
    <div class="d-flex justify-content-center gap-1">

        <a
            href="{{ route('inventario.movimientos.show', $movimiento) }}"
            class="btn btn-sm btn-light"
            title="Ver el detalle"
        >
            <i class="bi bi-eye"></i>
        </a>

        @if($movimiento->proceso_orden_id === null)
            {{-- Un consumo de un proceso no se borra desde aqui: se borra
                 desde la pantalla del proceso, que deja el costo al dia. --}}
            <form
                method="POST"
                action="{{ route('inventario.movimientos.destroy', $movimiento) }}"
                class="d-inline"
                data-confirm-delete="¿Eliminar este movimiento? El material volverá al almacén y las existencias se corregirán solas."
            >
                @csrf
                @method('DELETE')

                <button type="submit" class="btn btn-sm btn-light" title="Eliminar">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        @else
            <span
                class="btn btn-sm btn-light disabled"
                title="Se borra desde la pantalla del proceso"
            >
                <i class="bi bi-trash"></i>
            </span>
        @endif

    </div>
</td>

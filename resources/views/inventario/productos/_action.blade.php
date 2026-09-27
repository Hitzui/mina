<td>
    <div class="d-flex justify-content-center gap-1">

        <a
            href="{{ route('inventario.productos.show', $producto) }}"
            class="btn btn-sm btn-light"
            title="Ver la ficha del material"
        >
            <i class="bi bi-eye"></i>
        </a>

        <a
            href="{{ route('inventario.productos.edit', $producto) }}"
            class="btn btn-sm btn-light"
            title="Editar"
        >
            <i class="bi bi-pencil"></i>
        </a>

        <form
            method="POST"
            action="{{ route('inventario.productos.destroy', $producto) }}"
            class="d-inline"
            data-confirm-delete="¿Eliminar el material «{{ $producto->nombre }}»? Si ya tiene movimientos en el almacén se desactivará en vez de borrarse, para no dejar el historial sin origen."
        >
            @csrf
            @method('DELETE')

            <button type="submit" class="btn btn-sm btn-light" title="Eliminar">
                <i class="bi bi-trash"></i>
            </button>
        </form>

    </div>
</td>

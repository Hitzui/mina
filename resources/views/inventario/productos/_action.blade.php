{{--
    Los botones de la fila de materiales.

    Cada uno lleva su url en un data-url y una clase que el javascript
    reconoce. El boton de eliminar va en un formulario aparte porque el
    borrado necesita POST con _method, no se puede con un enlace.
--}}
<td>
    <div class="d-flex justify-content-center gap-1">

        <button
            type="button"
            class="btn btn-sm btn-light btn-show-producto"
            data-url="{{ route('inventario.productos.show', $producto) }}"
            title="Ver la ficha del material"
        >
            <i class="bi bi-eye"></i>
        </button>

        <button
            type="button"
            class="btn btn-sm btn-light btn-edit-producto"
            data-url="{{ route('inventario.productos.edit', $producto) }}"
            title="Editar"
        >
            <i class="bi bi-pencil"></i>
        </button>

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

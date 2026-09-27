{{--
    Los botones de la fila de materiales.

    Cada uno lleva su url en un data-url y una clase que el javascript
    reconoce. El boton de eliminar va en un formulario aparte porque el
    borrado necesita POST con _method, no se puede con un enlace.

    La confirmacion va repartida en tres atributos, y no en el
    data-confirm-delete: ese atributo es solo la bandera que le dice al
    javascript que el boton pide confirmacion, no lleva el mensaje. Si el
    texto se pone ahi, se ignora en silencio y sale el del dialogo por
    defecto.

    El texto dice las dos cosas que pueden pasar, porque dependen de si el
    material tiene movimientos: sin movimientos se borra, con movimientos se
    desactiva. Avisar de las dos evita el susto de ver desaparecer el
    material del catalogo sin entender por que.
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
            data-confirm-delete
            data-confirm-title="Eliminar el material «{{ $producto->nombre }}»?"
            data-confirm-text="Si el material ya tiene movimientos en el almacén no se borrará, sino que se desactivará: el historial del almacén y los costos de los procesos donde se usó necesitan que siga existiendo. Si no tiene ninguno, se eliminará y no se podrá recuperar."
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

{{--
    Los botones de la fila de proveedores.

    Cada uno lleva su url en un data-url y una clase que el javascript
    reconoce. El boton de eliminar va en un formulario aparte porque el borrado
    necesita POST con _method, no se puede con un enlace.

    La confirmacion va repartida en tres atributos, y no en el
    data-confirm-delete: ese atributo es solo la bandera que le dice al
    javascript que el boton pide confirmacion, no lleva el mensaje.

    El texto dice las dos cosas que pueden pasar, porque dependen de si el
    proveedor tiene compras: con compras se desactiva, sin compras se borra.
    Avisar de las dos evita el susto de ver desaparecer el proveedor sin
    entender por que.
--}}
<td>
    <div class="d-flex justify-content-center gap-1">

        <button
            type="button"
            class="btn btn-sm btn-light btn-show-proveedor"
            data-url="{{ route('inventario.proveedores.show', $proveedor) }}"
            title="Ver la ficha del proveedor"
        >
            <i class="bi bi-eye"></i>
        </button>

        <button
            type="button"
            class="btn btn-sm btn-light btn-edit-proveedor"
            data-url="{{ route('inventario.proveedores.edit', $proveedor) }}"
            title="Editar"
        >
            <i class="bi bi-pencil"></i>
        </button>

        <form
            method="POST"
            action="{{ route('inventario.proveedores.destroy', $proveedor) }}"
            class="d-inline"
            data-confirm-delete
            data-confirm-title="Eliminar el proveedor «{{ $proveedor->nombre }}»?"
            data-confirm-text="Si el proveedor ya tiene compras registradas no se borrará, sino que se desactivará: el historial de las compras necesita que siga existiendo. Si no tiene ninguna, se eliminará y no se podrá recuperar."
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

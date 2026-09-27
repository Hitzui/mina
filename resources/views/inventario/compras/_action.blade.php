{{--
    Los botones de la fila de compras.

    Compras no van en modal, asi que los botones son enlaces a paginas: la
    ficha y la edicion son pantallas enteras, porque una compra lleva un
    numero cualquiera de lineas y una rejilla de esas no cabe en una ventana
    pequena.

    El boton de eliminar va en un formulario aparte porque el borrado necesita
    POST con _method, no se puede con un enlace.

    El texto de la confirmacion avisa de lo que pasa con el almacen, que es lo
    que no se ve mirando el boton: borrar una compra finalizada saca su
    material del almacen. Si ese material ya se consumio en un proceso, el
    borrado se va a negar y conviene saberlo antes de intentarlo.
--}}
<td>
    <div class="d-flex justify-content-center gap-1">

        <a
            href="{{ route('inventario.compras.show', $compra) }}"
            class="btn btn-sm btn-light"
            title="Ver la compra"
        >
            <i class="bi bi-eye"></i>
        </a>

        <a
            href="{{ route('inventario.compras.edit', $compra) }}"
            class="btn btn-sm btn-light"
            title="Editar"
        >
            <i class="bi bi-pencil"></i>
        </a>

        <form
            method="POST"
            action="{{ route('inventario.compras.destroy', $compra) }}"
            class="d-inline"
            data-confirm-delete
            data-confirm-title="Eliminar la compra {{ $compra->codigo }}?"
            @if($compra->esFinalizada())
                data-confirm-text="Esta compra está finalizada, así que su material está en el almacén. Al eliminarla, el material saldrá del almacén. Si ya se consumió en algún proceso, no se va a dejar hacer: primero hay que deshacer esos consumos. Esto no se puede recuperar."
            @else
                data-confirm-text="Esta compra no ha entrado material al almacén, así que borrarla solo quita el documento. Esto no se puede recuperar."
            @endif
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

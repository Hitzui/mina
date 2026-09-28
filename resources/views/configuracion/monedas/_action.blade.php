{{--
    Los botones de la fila de una moneda.

    Tres botones: ver, editar y eliminar. El de eliminar lleva su url en un
    data-url y su confirmacion repartida en atributos, que es lo que lee el
    javascript de la pagina.

    El boton de eliminar no se esconde cuando la moneda esta en uso, y esta es
    una decision que conviene dejar escrita. Podria esconderse con un "no se
    puede borrar" y quedaria una lista mas limpia, pero el que tiene que
    desactivar una moneda en vez de borrarla no lo sabe todavia: solo lo sabria
    si el boton no estuviera y se preguntara por que. Con el boton ahi y el
    aviso al pulsarlo, el usuario ve la razon exacta —"esta en 2 compras, 30
    tipos de cambio y 4 pagos"—, que es informacion que le sirve igual aunque
    no quisiera borrar.

    El aviso se pinta antes de borrar y no despues, y por eso lleva su url y el
    nombre de la moneda en atributos, que es lo que lee el javascript de la
    pagina para armar el aviso sin tener el texto duplicado en dos sitios.
--}}
<td>
    <div class="d-flex justify-content-center gap-1">

        <button
            type="button"
            class="btn btn-sm btn-light btn-ver-moneda"
            data-url="{{ route('configuracion.monedas.show', $moneda) }}"
            title="Ver la ficha de {{ $moneda->nombre }}"
        >
            <i class="bi bi-eye"></i>
        </button>

        <button
            type="button"
            class="btn btn-sm btn-light btn-edit-moneda"
            data-url="{{ route('configuracion.monedas.edit', $moneda) }}"
            title="Editar {{ $moneda->nombre }}"
        >
            <i class="bi bi-pencil"></i>
        </button>

        <button
            type="button"
            class="btn btn-sm btn-light"
            data-confirm-delete-moneda
            data-url="{{ route('configuracion.monedas.destroy', $moneda) }}"
            data-nombre="{{ $moneda->nombre }}"
            title="Eliminar {{ $moneda->nombre }}"
        >
            <i class="bi bi-trash"></i>
        </button>

    </div>
</td>

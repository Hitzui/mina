{{--
    Los botones de la fila de un precio del oro.

    Solo hay editar y eliminar. No hay ficha: un precio son cinco campos y la
    ficha seria la fila con mas sitio en blanco.

    El boton de eliminar lleva su confirmacion repartida en atributos, que es
    lo que lee el javascript de la pagina. El aviso avisa de lo que pasa si
    ese dia se queda sin precio, y dice que al valorar se usara el ultimo que
    se sepa, que es lo que evita que alguien imagine que se queda sin valor.

    El va con data-url, no con el id dentro del html de la fila: el javascript
    pide los datos al servidor y los pone. Copiar un campo de la fila al modal
    es como una tabla se desincroniza de su detalle: en cuanto se cambia lo que
    se enseña, el modal enseña lo viejo.
--}}
<td>
    <div class="d-flex justify-content-center gap-1">

        <button
            type="button"
            class="btn btn-sm btn-light btn-edit-precio-oro"
            data-url="{{ route('configuracion.precios_oro.edit', $precioOro) }}"
            title="Editar el precio del {{ $precioOro->fecha->format('d/m/Y') }}"
        >
            <i class="bi bi-pencil"></i>
        </button>

        <form
            method="POST"
            action="{{ route('configuracion.precios_oro.destroy', $precioOro) }}"
            class="d-inline"
            data-confirm-delete-precio-oro
            data-url="{{ route('configuracion.precios_oro.destroy', $precioOro) }}"
            data-fecha="{{ $precioOro->fecha->format('d/m/Y') }}"
        >
            @csrf
            @method('DELETE')

            <button type="submit" class="btn btn-sm btn-light" title="Eliminar">
                <i class="bi bi-trash"></i>
            </button>
        </form>

    </div>
</td>

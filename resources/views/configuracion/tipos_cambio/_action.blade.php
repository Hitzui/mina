{{--
    Los botones de la fila del tipo de cambio.

    Solo hay editar y eliminar. No hay ficha: un tipo de cambio son cuatro
    campos y la ficha seria la misma fila con mas sitio en blanco.

    El boton de eliminar lleva su confirmacion repartida en atributos, que es
    lo que lee el javascript de la pagina. El texto avisa de lo que pasa si
    ese dia es el ultimo de la serie, porque el aviso se pinta antes de
    borrar y no despues.

    El va con data-url, no con el id dentro del html de la fila: el
    javascript pide los datos al servidor y los pone. Copiar un campo de la
    fila al modal es como una tabla se desincroniza de su detalle: en cuanto
    se cambia lo que se ensea, el modal ensena lo viejo.
--}}
<td>
    <div class="d-flex justify-content-center gap-1">

        <button
            type="button"
            class="btn btn-sm btn-light btn-edit-tipo-cambio"
            data-url="{{ route('configuracion.tipos_cambio.edit', $tipoCambio) }}"
            title="Editar el {{ $tipoCambio->fecha->format('d/m/Y') }}"
        >
            <i class="bi bi-pencil"></i>
        </button>

        <form
            method="POST"
            action="{{ route('configuracion.tipos_cambio.destroy', $tipoCambio) }}"
            class="d-inline"
            data-confirm-delete-tipo-cambio
            data-url="{{ route('configuracion.tipos_cambio.destroy', $tipoCambio) }}"
            data-fecha="{{ $tipoCambio->fecha->format('d/m/Y') }}"
        >
            @csrf
            @method('DELETE')

            <button type="submit" class="btn btn-sm btn-light" title="Eliminar">
                <i class="bi bi-trash"></i>
            </button>
        </form>

    </div>
</td>

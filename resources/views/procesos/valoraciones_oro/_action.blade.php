{{--
    Los botones de la fila de una valoracion.

    Solo hay editar y eliminar. No hay ficha: una valoracion son cuatro campos
    mas un calculo, y la ficha seria la fila con mas sitio en blanco.

    El boton de eliminar lleva su confirmacion repartida en atributos, que es lo
    que lee el javascript de la pagina. El aviso avisa de la cifra que se va,
    porque es la pregunta que uno se hace antes de pulsar: si se borra una
    valoracion de 7.050, hay que saber que eran 7.050 y no 705.

    Y avisa tambien de lo que no se puede arreglar luego: la valoracion no
    tiene precio guardado, y los gramos valorados eran los de una recuperacion
    que puede haber cambiado. Una vez borrada, volver a tener ese valor obliga a
    revalorar, que es volver a elegir el dia y la moneda a mano.

    El va con data-url, no con los datos metidos en el html de la fila: el
    javascript pide los datos al servidor y los pone. Copiar un campo de la fila
    al modal es como una tabla se desincroniza de su detalle.
--}}
<td>
    <div class="d-flex justify-content-center gap-1">

        <button
            type="button"
            class="btn btn-sm btn-light btn-edit-valoracion"
            data-url="{{ route('procesos.valoraciones_oro.edit', $valoracion) }}"
            title="Editar la valoración del {{ $valoracion->fecha->format('d/m/Y') }}"
        >
            <i class="bi bi-pencil"></i>
        </button>

        <form
            method="POST"
            action="{{ route('procesos.valoraciones_oro.destroy', $valoracion) }}"
            class="d-inline"
            data-confirm-delete-valoracion
            data-url="{{ route('procesos.valoraciones_oro.destroy', $valoracion) }}"
            data-fecha="{{ $valoracion->fecha->format('d/m/Y') }}"
            data-valor="{{ number_format((float) $valoracion->valor, 2) }}"
            data-moneda="{{ $valoracion->moneda?->codigo ?? '' }}"
        >
            @csrf
            @method('DELETE')

            <button type="submit" class="btn btn-sm btn-light" title="Eliminar">
                <i class="bi bi-trash"></i>
            </button>
        </form>

    </div>
</td>

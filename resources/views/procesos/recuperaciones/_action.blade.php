{{--
    Los botones de la fila de una recuperacion.

    Solo hay editar y eliminar. No hay ficha: una recuperacion son cinco campos
    y la ficha seria la fila con mas sitio en blanco.

    El boton de eliminar lleva su confirmacion repartida en atributos, que es
    lo que lee el javascript de la pagina. El aviso avisa de que los gramos se
    van con el, porque es la pregunta que uno se hace antes de pulsar: si se
    borra una partida de 300 gramos, hay que saber que se van.

    Y avisa tambien de lo que no se puede arreglar luego. Una vez borrada, esa
    recuperacion no tiene grams, ni fecha, ni relacion con la orden, y el valor
    del oro de esa partida habria que rehacerlo a mano. Es la unica de esta
    pantalla de la que no se puede volver atras.

    El va con data-url, no con el id dentro del html de la fila: el javascript
    pide los datos al servidor y los pone. Copiar un campo de la fila al modal
    es como una tabla se desincroniza de su detalle.
--}}
<td>
    <div class="d-flex justify-content-center gap-1">

        <button
            type="button"
            class="btn btn-sm btn-light btn-edit-recuperacion"
            data-url="{{ route('procesos.recuperaciones.edit', $recuperacion) }}"
            title="Editar la recuperación del {{ $recuperacion->fecha->format('d/m/Y') }}"
        >
            <i class="bi bi-pencil"></i>
        </button>

        <form
            method="POST"
            action="{{ route('procesos.recuperaciones.destroy', $recuperacion) }}"
            class="d-inline"
            data-confirm-delete-recuperacion
            data-url="{{ route('procesos.recuperaciones.destroy', $recuperacion) }}"
            data-fecha="{{ $recuperacion->fecha->format('d/m/Y') }}"
            data-gramos="{{ number_format((float) $recuperacion->gramos, 4) }}"
            data-orden="{{ $recuperacion->orden_trabajo?->codigo ?? '' }}"
        >
            @csrf
            @method('DELETE')

            <button type="submit" class="btn btn-sm btn-light" title="Eliminar">
                <i class="bi bi-trash"></i>
            </button>
        </form>

    </div>
</td>

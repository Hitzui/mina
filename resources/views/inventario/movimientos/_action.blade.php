{{--
    Los botones de la fila del kardex.

    El texto de la confirmacion va en data-confirm-title, -text y -button.
    El data-confirm-delete a secas es solo la bandera que le dice al
    javascript que el boton pide confirmacion; el mensaje ahi se ignora.

    Aqui el borrado no es solo quitar una fila: ademas devuelve el material
    al almacen. Es lo que dice el texto, porque es la parte que se puede
    llevar por sorpresa.
--}}
<td>
    <div class="d-flex justify-content-center gap-1">

        <a
            href="{{ route('inventario.movimientos.show', $movimiento) }}"
            class="btn btn-sm btn-light"
            title="Ver el detalle"
        >
            <i class="bi bi-eye"></i>
        </a>

        @if($movimiento->proceso_orden_id === null)
            {{-- Un consumo de un proceso no se borra desde aqui: se borra
                 desde la pantalla del proceso, que deja el costo al dia. --}}
            <form
                method="POST"
                action="{{ route('inventario.movimientos.destroy', $movimiento) }}"
                class="d-inline"
                data-confirm-delete
                data-confirm-title="¿Eliminar este movimiento?"
                data-confirm-text="Se deshará el movimiento: el material volverá al almacén y la existencia se corregirá sola. Si lo que se registró no era lo que pasó, esta es la manera de arreglarlo."
                data-confirm-button="Sí, deshacer"
            >
                @csrf
                @method('DELETE')

                <button type="submit" class="btn btn-sm btn-light" title="Eliminar">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        @else
            <span
                class="btn btn-sm btn-light disabled"
                title="Se borra desde la pantalla del proceso"
            >
                <i class="bi bi-trash"></i>
            </span>
        @endif

    </div>
</td>

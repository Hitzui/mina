{{--
    Los botones de la fila de cobros.

    ===========================================================================
    POR QUE NO ES UN MODAL
    ===========================================================================

    Porque un cobro lleva cabecera y un numero cualquiera de lineas de ingreso, y
    una rejilla de esas en una ventana pequena se queda corta: en un movil el
    usuario tendria que desplazarse dentro del modal para llegar al boton de
    guardar, y el total —que sale de las lineas— quedaria fuera de la vista. Es el
    mismo motivo por el que las compras van en pantalla aparte.

    ===========================================================================
    EL BOTON DE ELIMINAR, Y LO QUE DICE ANTES DE HACER NADA
    ===========================================================================

    Un cobro no se puede deshacer. Si se borra, sus lineas quedan dadas de baja en
    el historial —no se borran de verdad— pero el cobro desaparece del listado, y con
    el se va el saber que ese cliente entrego ese dinero.

    Y hay un caso en el que el aviso importa mas que en los demas: un cobro
    FINALIZADO es dinero que ya esta en la caja. Borrarlo no saca el dinero de la
    caja —eso no lo hace nadie, porque la caja no tiene descuadre automatico— pero
    deja de existir el papel que lo respalda, y el saldo de ese cliente vuelve a
    salir como pendiente. El aviso lo dice.

    El borrado va en un formulario aparte porque necesita POST con _method, y no se
    puede con un enlace.
--}}
<td>
    <div class="d-flex justify-content-center gap-1">

        <a
            href="{{ route('procesos.cobros.show', $cobro) }}"
            class="btn btn-sm btn-light"
            title="Ver el cobro"
        >
            <i class="bi bi-eye"></i>
        </a>

        <a
            href="{{ route('procesos.cobros.edit', $cobro) }}"
            class="btn btn-sm btn-light"
            title="Editar el cobro"
        >
            <i class="bi bi-pencil"></i>
        </a>

        <form
            method="POST"
            action="{{ route('procesos.cobros.destroy', $cobro) }}"
            class="d-inline"
            data-confirm-delete
            data-confirm-title="Eliminar el cobro {{ $cobro->codigo }}?"
            @if ($cobro->esFinalizado())
                data-confirm-text="Este cobro está finalizado: sus {{ $cobro->cantidad_lineas }} {{ $cobro->cantidad_lineas === 1 ? 'línea' : 'líneas' }} ya cuentan como cobrado. Si lo elimina, esos ingresos volverán a salir como pendientes y el papel que respalda ese dinero desaparecerá. El dinero no sale de la caja, pero el saldo del cliente queda descuadrado. Esto no se puede recuperar."
            @elseif ($cobro->estaCancelado())
                data-confirm-text="Este cobro ya está cancelado, así que sus líneas no cuentan como cobrado. Eliminarlo quita el documento. Esto no se puede recuperar."
            @else
                data-confirm-text="Este cobro está pendiente, así que todavía no cuenta como dinero recibido. Sus líneas quedarán dadas de baja y el cobro desaparecerá del listado. Esto no se puede recuperar."
            @endif
            data-confirm-button="Sí, eliminar"
        >
            @csrf
            @method('DELETE')

            <button type="submit" class="btn btn-sm btn-light" title="Eliminar el cobro">
                <i class="bi bi-trash"></i>
            </button>
        </form>

    </div>
</td>

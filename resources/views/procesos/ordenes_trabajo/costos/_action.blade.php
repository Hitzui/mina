{{--
    Acciones de un movimiento de costo general de la orden.

    Un costo general no tiene proceso, asi que la url solo lleva la orden.
--}}
<div class="btn-group" role="group" aria-label="Acciones">
    <button type="button" class="btn btn-sm btn-primary btn-show-costo" data-id="{{ $costo->id }}"
            data-url="{{ route('procesos.ordenes_trabajo.costos.show', [$costo->orden_trabajo_id, $costo]) }}"
            title="Ver">
        <i class="bi bi-eye"></i>
    </button>

    <a href="{{ route('procesos.ordenes_trabajo.costos.edit', [$costo->orden_trabajo_id, $costo]) }}"
       class="btn btn-sm btn-warning btn-edit-costo"
       data-url="{{ route('procesos.ordenes_trabajo.costos.edit', [$costo->orden_trabajo_id, $costo]) }}"
       title="Editar">
        <i class="bi bi-pencil"></i>
    </a>

    <a href="{{ route('procesos.ordenes_trabajo.costos.destroy', [$costo->orden_trabajo_id, $costo]) }}"
       data-confirm-delete
       data-confirm-title="¿Eliminar este costo?"
       data-confirm-text="Esta acción no se puede deshacer."
       data-confirm-button="Sí, eliminar"
       class="btn btn-sm btn-danger"
       title="Eliminar">
        <i class="bi bi-trash"></i>
    </a>
</div>

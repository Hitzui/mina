<div class="btn-group" role="group">
    <button type="button" class="btn btn-sm btn-primary btn-show-trabajo" data-id="{{ $id }}"
            data-url="{{ route('procesos.ordenes_trabajo.trabajos_empleados.show', [$orden_trabajo_id, $id]) }}"
            title="Ver">
        <i class="bi bi-eye"></i>
    </button>
    <a href="{{ route('procesos.ordenes_trabajo.trabajos_empleados.edit', [$orden_trabajo_id, $id]) }}"
       class="btn btn-sm btn-warning btn-edit-trabajo"
       data-url="{{ route('procesos.ordenes_trabajo.trabajos_empleados.edit', [$orden_trabajo_id, $id]) }}"
       title="Editar">
        <i class="bi bi-pencil"></i>
    </a>
    <a href="{{ route('procesos.ordenes_trabajo.trabajos_empleados.destroy', [$orden_trabajo_id, $id]) }}"
       data-confirm-delete
       data-confirm-title="¿Eliminar este trabajo?"
       data-confirm-text="Esta acción no se puede deshacer."
       data-confirm-button="Sí, eliminar"
       class="btn btn-sm btn-danger">
        <i class="bi bi-trash"></i>
    </a>
</div>

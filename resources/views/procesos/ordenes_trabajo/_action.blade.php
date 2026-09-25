<div class="btn-group" role="group">
    <a href="{{ route('procesos.ordenes_trabajo.show', $id) }}" class="btn btn-sm btn-primary btn-show-trabajo"
            title="Ver">
        <i class="fa-regular fa-eye"></i>
    </a>

    <a href="{{ route('procesos.ordenes_trabajo.edit', $id) }}" class="btn btn-sm btn-warning btn-edit-trabajo"
            title="Editar">
        <i class="fa-solid fa-pencil"></i>
    </a>

    <a href="{{ route('procesos.ordenes_trabajo.destroy', $id) }}"
       class="btn btn-sm btn-danger"
       data-confirm-delete
       data-confirm-title="¿Eliminar la orden de trabajo?"
       data-confirm-text="¿Deseas eliminar la orden de trabajo del sistema? Esta acción no se puede revertir."
       data-confirm-button="Sí, eliminar"
            title="Eliminar">
        <i class="fa-solid fa-trash"></i>
    </a>
</div>

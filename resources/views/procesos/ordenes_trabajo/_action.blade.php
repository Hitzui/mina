<div class="btn-group" role="group">
    <a href="{{ route('procesos.ordenes_trabajo.show', $id) }}" class="btn btn-sm btn-primary btn-show-trabajo"
            title="Ver">
        <i class="bi bi-eye"></i>
    </a>

    <a href="{{ route('procesos.ordenes_trabajo.edit', $id) }}" class="btn btn-sm btn-warning btn-edit-trabajo"
            title="Editar">
        <i class="bi bi-pencil"></i>
    </a>

    <button type="button" class="btn btn-sm btn-danger btn-delete-trabajo"
            data-id="{{ $id }}"
            title="Eliminar">
        <i class="bi bi-trash"></i>
    </button>
</div>

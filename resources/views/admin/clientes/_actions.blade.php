<div class="btn-group" role="group" aria-label="Basic example">
    <a href="{{ route('admin.clientes.show', $id) }}" class="btn btn-sm btn-primary">
        <i class="fa-regular fa-eye"></i>
    </a>
    <a href="{{ route('admin.clientes.edit', $id) }}" class="btn btn-sm btn-warning">
        <i class="fa-solid fa-pencil"></i>
    </a>
    <a href="{{ route('admin.clientes.destroy', $id) }}"
       class="btn btn-sm btn-danger"
       data-confirm-delete
       data-confirm-title="¿Eliminar cliente?"
       data-confirm-text="¿Desea eliminar el cliente del sistema? Esta acción no se puede revertir."
       data-confirm-button="Sí, eliminar">
        <i class="fa-solid fa-trash"></i>
    </a>
</div>

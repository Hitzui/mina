<div class="btn-group" role="group" aria-label="Acciones">
    <a href="{{ route('admin.clientes.show', $id) }}" class="btn btn-sm btn-primary" title="Ver">
        <i class="bi bi-eye"></i>
    </a>
    <a href="{{ route('admin.clientes.edit', $id) }}" class="btn btn-sm btn-warning" title="Editar">
        <i class="bi bi-pencil"></i>
    </a>
    <a href="{{ route('admin.clientes.destroy', $id) }}"
       class="btn btn-sm btn-danger"
       data-confirm-delete
       data-confirm-title="¿Eliminar cliente?"
       data-confirm-text="¿Desea eliminar el cliente del sistema? Esta acción no se puede revertir."
       data-confirm-button="Sí, eliminar"
       title="Eliminar">
        <i class="bi bi-trash"></i>
    </a>
</div>

<div class="btn-group" role="group" aria-label="Acciones">
    <a href="{{ route('admin.etapas.show', $id) }}" class="btn btn-sm btn-primary" title="Ver">
        <i class="bi bi-eye"></i>
    </a>
    <a href="{{ route('admin.etapas.edit', $id) }}" class="btn btn-sm btn-warning" title="Editar">
        <i class="bi bi-pencil"></i>
    </a>
    <a href="{{ route('admin.etapas.destroy', $id) }}"
       class="btn btn-sm btn-danger"
       data-confirm-delete
       data-confirm-title="¿Eliminar Etapa?"
       data-confirm-text="¿Desea eliminar la etapa del sistema? Si la etapa seleccionada tiene ordenes en proceso, no se podrá eliminar."
       data-confirm-button="Sí, eliminar"
       title="Eliminar">
        <i class="bi bi-trash"></i>
    </a>
</div>

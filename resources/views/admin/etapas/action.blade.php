<div class="btn-group" role="group" aria-label="Basic example">
    <a href="{{ route('admin.etapas.show', $id) }}" class="btn btn-sm btn-primary">
        <i class="fa-regular fa-eye"></i>
    </a>
    <a href="{{ route('admin.etapas.edit', $id) }}" class="btn btn-sm btn-warning">
        <i class="fa-solid fa-pencil"></i>
    </a>
    <a href="{{ route('admin.etapas.destroy', $id) }}"
       class="btn btn-sm btn-danger"
       data-confirm-delete
       data-confirm-title="¿Eliminar Etapa?"
       data-confirm-text="¿Desea eliminar la etapa del sistema? Si la etapa seleccionada tiene ordenes en proceso, no se podrá eliminar."
       data-confirm-button="Sí, eliminar">
        <i class="fa-solid fa-trash"></i>
    </a>
</div>

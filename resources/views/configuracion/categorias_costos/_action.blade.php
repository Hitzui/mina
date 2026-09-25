<div class="btn-group" role="group" aria-label="Acciones">
    <a href="{{ route('configuracion.categorias_costos.show', $id) }}" class="btn btn-sm btn-primary" title="Ver">
        <i class="bi bi-eye"></i>
    </a>
    <a href="{{ route('configuracion.categorias_costos.edit', $id) }}" class="btn btn-sm btn-warning" title="Editar">
        <i class="bi bi-pencil"></i>
    </a>
    <a href="{{ route('configuracion.categorias_costos.destroy', $id) }}"
       class="btn btn-sm btn-danger"
       data-confirm-delete
       data-confirm-title="¿Eliminar categoría?"
       data-confirm-text="¿Desea eliminar la categoría del costo del sistema? Esta acción no se puede revertir."
       data-confirm-button="Sí, eliminar"
       title="Eliminar">
        <i class="bi bi-trash"></i>
    </a>
</div>

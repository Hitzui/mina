<div class="btn-group" role="group" aria-label="Acciones">
    <a href="{{ route('admin.equipos.show', $id) }}" class="btn btn-sm btn-primary" title="Ver">
        <i class="bi bi-eye"></i>
    </a>
    <a href="{{ route('admin.equipos.edit', $id) }}" class="btn btn-sm btn-warning" title="Editar">
        <i class="bi bi-pencil"></i>
    </a>
    <a href="{{ route('admin.equipos.destroy', $id) }}"
       class="btn btn-sm btn-danger"
       data-confirm-delete
       data-confirm-title="¿Eliminar Equipo?"
       data-confirm-text="¿Desea eliminar el equipo del sistema? Si el equipo ya se usó en algún proceso no se podrá eliminar, porque los costos registrados dependen de él."
       data-confirm-button="Sí, eliminar"
       title="Eliminar">
        <i class="bi bi-trash"></i>
    </a>
</div>

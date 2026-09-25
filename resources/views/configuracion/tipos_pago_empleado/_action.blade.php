<div class="btn-group" role="group" aria-label="Acciones">
    <a href="{{ route('configuracion.tipos_pago_empleado.show', $id) }}" class="btn btn-sm btn-primary" title="Ver">
        <i class="bi bi-eye"></i>
    </a>
    <a href="{{ route('configuracion.tipos_pago_empleado.edit', $id) }}" class="btn btn-sm btn-warning" title="Editar">
        <i class="bi bi-pencil"></i>
    </a>
    <a href="{{ route('configuracion.tipos_pago_empleado.destroy', $id) }}"
       class="btn btn-sm btn-danger"
       data-confirm-delete
       data-confirm-title="¿Eliminar tipo de pago?"
       data-confirm-text="¿Desea eliminar el tipo de pago a empleado del sistema? Esta acción no se puede revertir."
       data-confirm-button="Sí, eliminar"
       title="Eliminar">
        <i class="bi bi-trash"></i>
    </a>
</div>

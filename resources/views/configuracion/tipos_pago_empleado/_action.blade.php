<div class="btn-group" role="group" aria-label="Basic example">
    <a href="{{ route('configuracion.tipos_pago_empleado.show', $id) }}" class="btn btn-sm btn-primary">
        <i class="fa-regular fa-eye"></i>
    </a>
    <a href="{{ route('configuracion.tipos_pago_empleado.edit', $id) }}" class="btn btn-sm btn-warning">
        <i class="fa-solid fa-pencil"></i>
    </a>
    <a href="{{ route('configuracion.tipos_pago_empleado.destroy', $id) }}"
       class="btn btn-sm btn-danger"
       data-confirm-delete
       data-confirm-title="¿Eliminar tipo de pago?"
       data-confirm-text="¿Desea eliminar el tipo de pago a empleado del sistema? Esta acción no se puede revertir."
       data-confirm-button="Sí, eliminar">
        <i class="fa-solid fa-trash"></i>
    </a>
</div>

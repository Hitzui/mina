<div class="btn-group" role="group" aria-label="Acciones">
    <button type="button"
            class="btn btn-sm btn-primary btn-ver-empleado-pago"
            data-url="{{ route('admin.empleados.pagos.show', [$empleado_id, $id]) }}"
            data-edit-url="{{ route('admin.empleados.pagos.edit', [$empleado_id, $id]) }}"
            title="Ver">
        <i class="fa-regular fa-eye"></i>
    </button>

    <button type="button"
            class="btn btn-sm btn-warning btn-editar-empleado-pago"
            data-url="{{ route('admin.empleados.pagos.edit', [$empleado_id, $id]) }}"
            title="Editar">
        <i class="fa-solid fa-pencil"></i>
    </button>

    <a href="{{ route('admin.empleados.pagos.destroy', [$empleado_id, $id]) }}"
       class="btn btn-sm btn-danger"
       data-confirm-delete
       data-confirm-title="¿Eliminar tarifa?"
       data-confirm-text="¿Desea eliminar esta tarifa del historial del empleado? Esta acción no se puede revertir."
       data-confirm-button="Sí, eliminar"
       title="Eliminar">
        <i class="fa-solid fa-trash"></i>
    </a>
</div>

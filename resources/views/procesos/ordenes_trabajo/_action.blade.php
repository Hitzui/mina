{{--
    Acciones de la orden de trabajo.

    Ojo: estas clases NO son las de trabajos_empleados.js (btn-show-trabajo
    y btn-edit-trabajo). Ese script escucha esos nombres para abrir el modal
    del trabajo de un empleado, y la OT no tiene modal: sus acciones son
    enlaces que llevan a otra pagina. Si se le pusieran aqui, un data-url
    de mas abriria el modal equivocado.
--}}
<div class="btn-group" role="group" aria-label="Acciones">
    <a href="{{ route('procesos.ordenes_trabajo.show', $id) }}"
       class="btn btn-sm btn-primary"
       title="Ver">
        <i class="bi bi-eye"></i>
    </a>

    <a href="{{ route('procesos.ordenes_trabajo.edit', $id) }}"
       class="btn btn-sm btn-warning"
       title="Editar">
        <i class="bi bi-pencil"></i>
    </a>

    <a href="{{ route('procesos.ordenes_trabajo.destroy', $id) }}"
       class="btn btn-sm btn-danger"
       data-confirm-delete
       data-confirm-title="¿Eliminar la orden de trabajo?"
       data-confirm-text="Esta acción no se puede deshacer."
       data-confirm-button="Sí, eliminar"
       title="Eliminar">
        <i class="bi bi-trash"></i>
    </a>
</div>

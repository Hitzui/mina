{{--
    Acciones de un trabajo de empleado.

    La URL lleva la orden y el proceso porque el trabajo cuelga del
    proceso. Ambos vienen en la variable $trabajo: se sacan de ahi y no
    de la fila suelta, para que el enlace no pueda apuntar a otro proceso.
--}}
@php
    $procesoOrden = $trabajo->proceso_orden;
    $ordenTrabajo = $procesoOrden ? $procesoOrden->orden_trabajo : null;
@endphp

<div class="btn-group" role="group" aria-label="Acciones">
    <button type="button" class="btn btn-sm btn-primary btn-show-trabajo" data-id="{{ $trabajo->id }}"
            data-url="{{ route('procesos.ordenes_trabajo.procesos.trabajos_empleados.show', [$ordenTrabajo, $procesoOrden, $trabajo]) }}"
            title="Ver">
        <i class="bi bi-eye"></i>
    </button>

    <a href="{{ route('procesos.ordenes_trabajo.procesos.trabajos_empleados.edit', [$ordenTrabajo, $procesoOrden, $trabajo]) }}"
       class="btn btn-sm btn-warning btn-edit-trabajo"
       data-url="{{ route('procesos.ordenes_trabajo.procesos.trabajos_empleados.edit', [$ordenTrabajo, $procesoOrden, $trabajo]) }}"
       title="Editar">
        <i class="bi bi-pencil"></i>
    </a>

    <a href="{{ route('procesos.ordenes_trabajo.procesos.trabajos_empleados.destroy', [$ordenTrabajo, $procesoOrden, $trabajo]) }}"
       data-confirm-delete
       data-confirm-title="¿Eliminar este trabajo?"
       data-confirm-text="Esta acción no se puede deshacer."
       data-confirm-button="Sí, eliminar"
       class="btn btn-sm btn-danger"
       title="Eliminar">
        <i class="bi bi-trash"></i>
    </a>
</div>

{{--
    Acciones de un movimiento de costo del proceso.

    La URL lleva la orden y el proceso porque el costo cuelga del proceso.
    Ambos vienen en la variable $costo: se sacan de ahi y no de la fila
    suelta, para que el enlace no pueda apuntar a otro proceso.
--}}
@php
    $procesoOrden = $costo->proceso_orden;
    $ordenTrabajo = $costo->orden_trabajo;
@endphp

<div class="btn-group" role="group" aria-label="Acciones">
    <button type="button" class="btn btn-sm btn-primary btn-show-costo" data-id="{{ $costo->id }}"
            data-url="{{ route('procesos.ordenes_trabajo.procesos.costos.show', [$ordenTrabajo, $procesoOrden, $costo]) }}"
            title="Ver">
        <i class="bi bi-eye"></i>
    </button>

    <a href="{{ route('procesos.ordenes_trabajo.procesos.costos.edit', [$ordenTrabajo, $procesoOrden, $costo]) }}"
       class="btn btn-sm btn-warning btn-edit-costo"
       data-url="{{ route('procesos.ordenes_trabajo.procesos.costos.edit', [$ordenTrabajo, $procesoOrden, $costo]) }}"
       title="Editar">
        <i class="bi bi-pencil"></i>
    </a>

    <a href="{{ route('procesos.ordenes_trabajo.procesos.costos.destroy', [$ordenTrabajo, $procesoOrden, $costo]) }}"
       data-confirm-delete
       data-confirm-title="¿Eliminar este costo?"
       data-confirm-text="Esta acción no se puede deshacer y el costo del proceso volverá a recalcularse."
       data-confirm-button="Sí, eliminar"
       class="btn btn-sm btn-danger"
       title="Eliminar">
        <i class="bi bi-trash"></i>
    </a>
</div>

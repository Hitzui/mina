{{--
    Acciones de un uso de equipo en un proceso.

    La URL lleva la orden y el proceso porque el uso cuelga del proceso.
    Ambos vienen en la variable $uso: se sacan de ahi y no de la fila
    suelta, para que el enlace no pueda apuntar a otro proceso.
--}}
@php
    $procesoOrden = $uso->procesoOrden;
    $ordenTrabajo = $procesoOrden ? $procesoOrden->orden_trabajo : null;
@endphp

<div class="btn-group" role="group" aria-label="Acciones">
    <button type="button" class="btn btn-sm btn-primary btn-show-uso" data-id="{{ $uso->id }}"
            data-url="{{ route('procesos.ordenes_trabajo.procesos.equipos.show', [$ordenTrabajo, $procesoOrden, $uso]) }}"
            title="Ver">
        <i class="bi bi-eye"></i>
    </button>

    <a href="{{ route('procesos.ordenes_trabajo.procesos.equipos.edit', [$ordenTrabajo, $procesoOrden, $uso]) }}"
       class="btn btn-sm btn-warning btn-edit-uso"
       data-url="{{ route('procesos.ordenes_trabajo.procesos.equipos.edit', [$ordenTrabajo, $procesoOrden, $uso]) }}"
       title="Editar">
        <i class="bi bi-pencil"></i>
    </a>

    <a href="{{ route('procesos.ordenes_trabajo.procesos.equipos.destroy', [$ordenTrabajo, $procesoOrden, $uso]) }}"
       data-confirm-delete
       data-confirm-title="¿Quitar este equipo del proceso?"
       data-confirm-text="Se quitará el equipo y su depreciación desaparecerá del costo del proceso. Si vuelve a usarse después, se registra de nuevo."
       data-confirm-button="Sí, quitar"
       class="btn btn-sm btn-danger"
       title="Quitar del proceso">
        <i class="bi bi-trash"></i>
    </a>
</div>

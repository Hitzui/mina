{{--
    Acciones de un ingreso de la orden.

    Un ingreso cuelga de la orden entera y no de un proceso, asi que la url solo
    lleva la orden y el ingreso.

    Y LA ORDEN SE SACA DEL PROPIO INGRESO, con $ingreso->orden_trabajo_id, y no
    de una variable que venga de la vista. Esta vista la pinta el DataTable, y
    el DataTable no recibe la orden: solo tiene el modelo de cada fila. Al
    escribir $ordenTrabajo ahi, la tabla devolvia las filas con un error
    "Undefined variable $ordenTrabajo" dentro del html, y no se veia: la pagina
    cargaba, la tabla salia, y las celdas de acciones venian a medio hacer.
    Es la misma razon por la que el boton de los costos usa
    $costo->orden_trabajo_id.

    Y sale mejor asi por otra cosa: la url del boton lleva la orden que es de
    verdad la del ingreso. Con la de la ficha, si algún dia se pintara una fila
    de otra orden, el boton llevaria la orden de la ficha con el id de un
    ingreso que no es de ella, y el servidor devolveria un 404 en vez de algo
    raro.

    El boton de eliminar lleva la confirmacion repartida en atributos, que es lo
    que pide la libreria: si el boton pertenece a un formulario lo envia, y si
    no va a su url. Va como enlace y no dentro de un form porque un form con
    method DELETE dentro de una tabla de filas es un form dentro de otro, y el
    navegador no sabe a que fila pertenece.
--}}
<div class="btn-group" role="group" aria-label="Acciones">
    <button
        type="button"
        class="btn btn-sm btn-primary btn-show-ingreso"
        data-url="{{ route('procesos.ordenes_trabajo.ingresos.show', [$ingreso->orden_trabajo_id, $ingreso]) }}"
        title="Ver el ingreso del {{ $ingreso->fecha->format('d/m/Y') }}"
    >
        <i class="bi bi-eye"></i>
    </button>

    <a href="{{ route('procesos.ordenes_trabajo.ingresos.edit', [$ingreso->orden_trabajo_id, $ingreso]) }}"
       class="btn btn-sm btn-warning btn-edit-ingreso"
       data-url="{{ route('procesos.ordenes_trabajo.ingresos.edit', [$ingreso->orden_trabajo_id, $ingreso]) }}"
       title="Editar el ingreso del {{ $ingreso->fecha->format('d/m/Y') }}">
        <i class="bi bi-pencil"></i>
    </a>

    <a href="{{ route('procesos.ordenes_trabajo.ingresos.destroy', [$ingreso->orden_trabajo_id, $ingreso]) }}"
       data-confirm-delete
       data-confirm-title="¿Eliminar este ingreso?"
       data-confirm-text="Se borran {{ number_format((float) $ingreso->total, 2) }} {{ $ingreso->moneda?->codigo }} del {{ $ingreso->fecha->format('d/m/Y') }}. Esta acción no se puede deshacer."
       data-confirm-button="Sí, eliminar"
       class="btn btn-sm btn-danger"
       title="Eliminar el ingreso del {{ $ingreso->fecha->format('d/m/Y') }}">
        <i class="bi bi-trash"></i>
    </a>
</div>

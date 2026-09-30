{{--
    Modal de la ficha de un tipo de ingreso.

    No hay pantalla de ficha, y no por pereza: un tipo de ingreso son tres
    campos y la ficha seria la fila con mas sitio en blanco. Lo que si se anade
    aqui, y es lo que hace que valga la pena abrirla, es de donde se usa.

    Esa lista contesta la pregunta que uno se hace al mirar un catalogo: puedo
    tocar este o solo puedo desactivarlo. Y la respuesta cambia por completo
    segun el tipo: "Venta de oro" todavia no tiene ningun ingreso y se puede
    borrar tranquilamente, mientras que "Servicio de procesamiento" puede tener
    veinte y entonces no se puede tocar sin romper lo que ya esta escrito.

    Y el boton de borrar no esta en la ficha, a proposito. Esta es una pantalla
    para MIR: si el tipo esta en uso, la respuesta es desactivar, y poner un
    boton de borrar aqui seria ofrecer la opcion que no funciona. El boton esta
    en la fila de la lista, al lado de los demas, y ahi tampoco se esconde —lo
    que hace es avisar cuando se pulsa.

    Los datos vienen del servidor, como en el resto de modales. La ficha no
    copia nada del html de la fila: si copiase, en cuanto se actualizase una
    fila la ficha seguiria enseñando lo viejo.
--}}
<div class="modal fade" id="modalVerTipoIngreso" tabindex="-1" aria-labelledby="modalVerTipoIngresoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">

            <div class="modal-header">
                <h5 class="modal-title" id="modalVerTipoIngresoLabel">Tipo de ingreso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">

                <div id="verTipoIngresoAviso" class="alert alert-danger d-none"></div>

                <div class="row g-3">

                    <div class="col-md-7">
                        <div class="text-muted small">Nombre</div>
                        <div class="fs-5" id="verTipoIngresoNombre">—</div>
                    </div>

                    <div class="col-md-5">
                        <div class="text-muted small">Estado</div>
                        <div id="verTipoIngresoEstado">—</div>
                    </div>

                </div>

                <div class="row g-3 mt-1">

                    <div class="col-12">
                        <div class="text-muted small">Descripción</div>
                        <div id="verTipoIngresoDescripcion">—</div>
                    </div>

                </div>

                <hr>

                <div class="text-muted small mb-2">Dónde se está usando</div>

                {{--
                    Esta lista la pide el servidor y se pinta con javascript, y no
                    con un bucle de blade. El dato sale de contar las filas de
                    ingresos de otra tabla, y meterlo en la vista haria que el
                    html de la ficha se generase en el servidor con datos que son
                    de otra tabla. Como el resto de los modales: el servidor
                    contesta con los datos y el navegador los pinta.
                --}}
                <div id="verTipoIngresoUsos"></div>

                <div id="verTipoIngresoSePuedeBorrar" class="alert alert-info mt-3 mb-0 d-none"></div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>

                <button type="button" class="btn btn-primary" id="btnEditarDesdeFicha">
                    <i class="bi bi-pencil me-1"></i>
                    Editar
                </button>
            </div>

        </div>
    </div>
</div>

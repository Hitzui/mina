{{--
    Modal de la ficha de una caja.

    No hay pantalla de ficha, y no por pereza: una caja son tres campos y la
    ficha seria la fila con mas sitio en blanco. Lo que si se anade aqui, y es lo
    que hace que valga la pena abrirla, es de donde se usa.

    Esa lista contesta la pregunta que uno se hace al mirar un catalogo: puedo
    tocar esta o solo puedo desactivarla. Y la respuesta cambia por completo segun
    la caja: la que todavia no ha recibido ningun cobro se puede borrar
    tranquilamente, mientras que la que lleva veinte ya no se puede tocar sin
    romper lo que esta escrito.

    Y el boton de borrar no esta en la ficha, a proposito. Esta es una pantalla
    para MIR: si la caja esta en uso, la respuesta es desactivar, y poner un boton
    de borrar aqui seria ofrecer la opcion que no funciona. El boton esta en la
    fila de la lista, al lado de los demas, y ahi tampoco se esconde —lo que hace
    es avisar cuando se pulsa.

    Los datos vienen del servidor, como en el resto de modales. La ficha no copia
    nada del html de la fila: si copiase, en cuanto se actualizase una fila la
    ficha seguiria enseñando lo viejo.
--}}
<div class="modal fade" id="modalVerCaja" tabindex="-1" aria-labelledby="modalVerCajaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">

            <div class="modal-header">
                <h5 class="modal-title" id="modalVerCajaLabel">Caja</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">

                <div id="verCajaAviso" class="alert alert-danger d-none"></div>

                <div class="row g-3">

                    <div class="col-md-7">
                        <div class="text-muted small">Nombre</div>
                        <div class="fs-5" id="verCajaNombre">—</div>
                    </div>

                    <div class="col-md-5">
                        <div class="text-muted small">Estado</div>
                        <div id="verCajaEstado">—</div>
                    </div>

                </div>

                <div class="row g-3 mt-1">

                    <div class="col-12">
                        <div class="text-muted small">Descripción</div>
                        <div id="verCajaDescripcion">—</div>
                    </div>

                </div>

                <hr>

                <div class="text-muted small mb-2">Dónde se está usando</div>

                {{--
                    Esta lista la pide el servidor y se pinta con javascript, y no
                    con un bucle de blade. El dato sale de contar las filas de
                    cobros de otra tabla, y meterlo en la vista haria que el html
                    de la ficha se generase en el servidor con datos que son de
                    otra tabla. Como el resto de los modales: el servidor contesta
                    con los datos y el navegador los pinta.
                --}}
                <div id="verCajaUsos"></div>

                <div id="verCajaSePuedeBorrar" class="alert alert-info mt-3 mb-0 d-none"></div>

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

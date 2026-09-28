{{--
    Modal de la ficha de una moneda.

    No hay pantalla de ficha, y no por pereza: una moneda son cinco campos y la
    ficha seria la fila con mas sitio en blanco. Lo que si se anade aqui, y es
    lo que hace que valga la pena abrirla, es de donde se usa la moneda.

    Esa lista es la que contesta la pregunta que uno se hace al mirar un
    catalogo de monedas: puedo tocar esta o solo puedo desactivarla. Y la
    respuesta cambia por completo segun la moneda: el cordoba esta en compras,
    pagos, costos, almacen y trabajos, y el dolar tiene treinta dias de tipo de
    cambio. Las dos estan en uso, y eso quiere decir que ninguna se puede
    borrar.

    Los datos vienen del servidor, como en el resto de modales. La ficha no
    copia nada del html de la fila: si copiase, en cuanto se actualizase una
    fila la ficha seguiria enseñando lo viejo.
--}}
<div class="modal fade" id="modalVerMoneda" tabindex="-1" aria-labelledby="modalVerMonedaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white">

            <div class="modal-header">
                <h5 class="modal-title" id="modalVerMonedaLabel">Moneda</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">

                <div id="verMonedaAviso" class="alert alert-danger d-none"></div>

                <div class="row g-3">

                    <div class="col-md-3">
                        <div class="text-muted small">Código</div>
                        <div class="font-monospace fs-5" id="verMonedaCodigo">—</div>
                    </div>

                    <div class="col-md-6">
                        <div class="text-muted small">Moneda</div>
                        <div class="fs-5" id="verMonedaNombre">—</div>
                    </div>

                    <div class="col-md-3">
                        <div class="text-muted small">Símbolo</div>
                        <div class="fs-5 font-monospace" id="verMonedaSimbolo">—</div>
                    </div>

                </div>

                <hr>

                <div class="row g-3">

                    <div class="col-md-6">
                        <div class="text-muted small">Moneda base del taller</div>
                        <div id="verMonedaBase">—</div>
                    </div>

                    <div class="col-md-6">
                        <div class="text-muted small">Estado</div>
                        <div id="verMonedaEstado">—</div>
                    </div>

                </div>

                <hr>

                <div class="text-muted small mb-2">Dónde se está usando</div>

                {{--
                    Esta lista la pide el servidor y se pinta con javascript,
                    y no con un bucle de blade. La razon es que el dato depende
                    de once tablas de la base, y meterlo en la vista haria que
                    el html de la ficha se generase en el servidor con datos que
                    son de otra tabla. Como el resto de los modales: el servidor
                    contesta con los datos y el navegador los pinta.
                --}}
                <div id="verMonedaUsos"></div>

                <div id="verMonedaSePuedeBorrar" class="alert alert-info mt-3 mb-0 d-none"></div>

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

{{--
    El modal de elegir proveedor, dentro de la propia pantalla de la compra.

    Va aqui y no en una pagina aparte porque la compra ya es una pantalla
    completa con su rejilla de lineas: abrir el catalogo en otra pestana
    obligaria a volver atras, y en un telefono eso es perder el formulario
    entero, que no cabe de otra manera en la pantalla.

    La tabla la pinta el servidor con su propio DataTable, para que el
    buscador y el reparto de columnas en moviles sean los de siempre y no una
    copia. Se construye la primera vez que se abre el modal, no al cargar la
    pagina: mientras esta cerrado no hace falta, y una tabla con datos que
    nadie ve solo retrasa la pantalla.

    El id y el nombre del table los pone el DataTable, con su setTableId. No
    se escriben a mano: si se escribieran aqui, el javascript buscaria una
    tabla y la peticion la responderia otra, y el buscador se quedaria en
    blanco sin decir por que.
--}}
<div class="modal fade"
     id="modalSeleccionarProveedor"
     tabindex="-1"
     aria-labelledby="modalSeleccionarProveedorLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="modalSeleccionarProveedorLabel">
                    <i class="bi bi-truck me-2"></i>
                    Elegir proveedor
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar">
                </button>
            </div>

            <div class="modal-body">

                <div class="text-center py-4" id="cargadorSelectorProveedor">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando los proveedores...</span>
                    </div>
                </div>

                {!! $selectorProveedor->html()->table([
                    'class' => 'table table-hover table-bordered w-100 d-none',
                ]) !!}

            </div>

            <div class="modal-footer">
                <button type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal">
                    Cerrar
                </button>
            </div>

        </div>

    </div>

</div>

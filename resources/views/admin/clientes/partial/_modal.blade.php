<div class="modal fade"
     id="modalSeleccionarCliente"
     data-url="{{ route('admin.clientes.selector') }}"
     tabindex="-1"
     aria-labelledby="modalSeleccionarClienteLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered">

        <div class="modal-content">

            {{-- Encabezado --}}
            <div class="modal-header">

                <h5 class="modal-title" id="modalSeleccionarClienteLabel">
                    <i class="bi bi-people me-2"></i>
                    Seleccionar Cliente
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar">
                </button>

            </div>

            {{-- Contenido --}}
            <div class="modal-body">

                <div class="table-responsive">

                    <table id="cliente-selector-table"
                           class="table table-hover table-bordered w-100">

                        <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Teléfono</th>
                            <th>Dirección</th>
                            <th class="text-center">Acción</th>
                        </tr>
                        </thead>

                        <tbody>
                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>

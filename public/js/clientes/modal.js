$(document).ready(function () {

    let tablaClientes = null;

    /**
     * Inicializa el DataTable del selector de clientes.
     */
    function inicializarTablaClientes() {

        if ($.fn.DataTable.isDataTable('#cliente-selector-table')) {
            tablaClientes = $('#cliente-selector-table').DataTable();
            return;
        }

        tablaClientes = $('#cliente-selector-table').DataTable({
            processing: true,
            serverSide: true,

            ajax: {
                url: $('#modalSeleccionarCliente').data('url'),
                type: 'GET'
            },

            columns: [
                {
                    data: 'nombre',
                    name: 'nombre'
                },
                {
                    data: 'telefono',
                    name: 'telefono',
                    defaultContent: '-'
                },
                {
                    data: 'direccion',
                    name: 'direccion',
                    defaultContent: '-'
                },
                {
                    data: 'seleccionar',
                    name: 'seleccionar',
                    orderable: false,
                    searchable: false,
                    className: 'text-center'
                }
            ],

            order: [
                [0, 'asc']
            ],

            pageLength: 10,

            language: {
                url: 'https://cdn.datatables.net/plug-ins/2.3.8/i18n/es-ES.json'
            }
        });
    }


    /**
     * Cuando se abre el modal.
     */
    $('#modalSeleccionarCliente').on('shown.bs.modal', function () {

        inicializarTablaClientes();

        if (tablaClientes) {
            tablaClientes.columns.adjust();
        }

    });


    /**
     * Cliente seleccionado.
     */
    $(document).on('click', '.btn-seleccionar-cliente', function () {

        const id = $(this).data('id');
        const nombre = $(this).data('nombre');

        $(document).trigger('cliente:seleccionado', {
            id: id,
            nombre: nombre
        });

        $('#modalSeleccionarCliente').modal('hide');
    });

});

<td>
    <div class="d-flex justify-content-center gap-1">
        @can('acuerdos_orden.edit')
            <button type="button" class="btn btn-sm btn-light btn-editar-acuerdo-orden"
                    data-url="{{ route('procesos.ordenes_trabajo.acuerdos.show', [$acuerdo->orden_trabajo_id, $acuerdo]) }}"
                    title="Editar acuerdo"><i class="bi bi-pencil"></i></button>
        @endcan
        @can('acuerdos_orden.delete')
            <button type="button" class="btn btn-sm btn-light btn-borrar-acuerdo-orden"
                    data-url="{{ route('procesos.ordenes_trabajo.acuerdos.destroy', [$acuerdo->orden_trabajo_id, $acuerdo]) }}"
                    data-tipo="{{ $acuerdo->tipo_participacion?->nombre }}"
                    title="Eliminar acuerdo"><i class="bi bi-trash"></i></button>
        @endcan
    </div>
</td>

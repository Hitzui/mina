<td>
    <div class="d-flex justify-content-center gap-1">
        @can('liquidaciones.edit')
            <button type="button" class="btn btn-sm btn-light btn-editar-liquidacion"
                    data-url="{{ route('procesos.ordenes_trabajo.liquidaciones.show', [$liquidacion->orden_trabajo_id, $liquidacion]) }}"
                    title="Corregir liquidación"><i class="bi bi-pencil"></i></button>
        @endcan
        @can('liquidaciones.delete')
            <button type="button" class="btn btn-sm btn-light btn-borrar-liquidacion"
                    data-url="{{ route('procesos.ordenes_trabajo.liquidaciones.destroy', [$liquidacion->orden_trabajo_id, $liquidacion]) }}"
                    data-fecha="{{ $liquidacion->fecha?->format('d/m/Y') }}"
                    title="Eliminar liquidación"><i class="bi bi-trash"></i></button>
        @endcan
    </div>
</td>

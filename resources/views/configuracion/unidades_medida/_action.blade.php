<td><div class="d-flex justify-content-center gap-1">
    @can('configuracion.unidades_medida.edit')
        <button type="button" class="btn btn-sm btn-light btn-editar-unidad" data-url="{{ route('configuracion.unidades_medida.edit', $unidad) }}" title="Editar {{ $unidad->nombre }}"><i class="bi bi-pencil"></i></button>
    @endcan
    @can('configuracion.unidades_medida.delete')
        <button type="button" class="btn btn-sm btn-light btn-borrar-unidad" data-url="{{ route('configuracion.unidades_medida.destroy', $unidad) }}" data-nombre="{{ $unidad->nombre }}" title="Eliminar {{ $unidad->nombre }}"><i class="bi bi-trash"></i></button>
    @endcan
</div></td>

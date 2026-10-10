<td>
    <div class="d-flex justify-content-center gap-1">
        <button type="button" class="btn btn-sm btn-light btn-ver-tipo-produccion"
                data-url="{{ route('configuracion.tipos_produccion.show', $tipoProduccion) }}"
                title="Ver {{ $tipoProduccion->nombre }}"><i class="bi bi-eye"></i></button>
        @can('configuracion.tipos_produccion.edit')
            <button type="button" class="btn btn-sm btn-light btn-edit-tipo-produccion"
                    data-url="{{ route('configuracion.tipos_produccion.edit', $tipoProduccion) }}"
                    title="Editar {{ $tipoProduccion->nombre }}"><i class="bi bi-pencil"></i></button>
            <button type="button" class="btn btn-sm btn-light btn-cambiar-estado-tipo-produccion"
                    data-url="{{ route('configuracion.tipos_produccion.cambiar-estado', $tipoProduccion) }}"
                    title="{{ $tipoProduccion->estado ? 'Desactivar' : 'Activar' }} {{ $tipoProduccion->nombre }}">
                <i class="bi {{ $tipoProduccion->estado ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
            </button>
        @endcan
        @can('configuracion.tipos_produccion.delete')
            <button type="button" class="btn btn-sm btn-light btn-borrar-tipo-produccion"
                    data-url="{{ route('configuracion.tipos_produccion.destroy', $tipoProduccion) }}"
                    data-nombre="{{ $tipoProduccion->nombre }}" title="Eliminar {{ $tipoProduccion->nombre }}">
                <i class="bi bi-trash"></i>
            </button>
        @endcan
    </div>
</td>

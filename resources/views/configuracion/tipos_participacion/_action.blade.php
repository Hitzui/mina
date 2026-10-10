<td>
    <div class="d-flex justify-content-center gap-1">
        <button type="button" class="btn btn-sm btn-light btn-ver-tipo-participacion"
                data-url="{{ route('configuracion.tipos_participacion.show', $tipo) }}"
                title="Ver {{ $tipo->nombre }}"><i class="bi bi-eye"></i></button>
        @can('configuracion.tipos_participacion.edit')
            <button type="button" class="btn btn-sm btn-light btn-edit-tipo-participacion"
                    data-url="{{ route('configuracion.tipos_participacion.edit', $tipo) }}"
                    title="Editar {{ $tipo->nombre }}"><i class="bi bi-pencil"></i></button>
            <button type="button" class="btn btn-sm btn-light btn-cambiar-estado-tipo-participacion"
                    data-url="{{ route('configuracion.tipos_participacion.cambiar-estado', $tipo) }}"
                    title="{{ $tipo->estado ? 'Desactivar' : 'Activar' }} {{ $tipo->nombre }}">
                <i class="bi {{ $tipo->estado ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
            </button>
        @endcan
        @can('configuracion.tipos_participacion.delete')
            <button type="button" class="btn btn-sm btn-light btn-borrar-tipo-participacion"
                    data-url="{{ route('configuracion.tipos_participacion.destroy', $tipo) }}"
                    data-nombre="{{ $tipo->nombre }}" title="Eliminar {{ $tipo->nombre }}">
                <i class="bi bi-trash"></i>
            </button>
        @endcan
    </div>
</td>

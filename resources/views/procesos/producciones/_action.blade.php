<td>
    <div class="d-flex justify-content-center gap-1">
        @can('producciones.edit')
            <button type="button" class="btn btn-sm btn-light btn-edit-produccion"
                    data-url="{{ route('procesos.producciones.edit', $produccion) }}"
                    title="Editar producción del {{ $produccion->fecha->format('d/m/Y') }}">
                <i class="bi bi-pencil"></i>
            </button>
        @endcan
        @can('producciones.delete')
            <button type="button" class="btn btn-sm btn-light btn-delete-produccion"
                    data-url="{{ route('procesos.producciones.destroy', $produccion) }}"
                    data-fecha="{{ $produccion->fecha->format('d/m/Y') }}"
                    data-tipo="{{ $produccion->tipo_produccion?->nombre }}"
                    data-cantidad="{{ number_format((float) $produccion->cantidad, 4, '.', ',') }} {{ $produccion->unidad_medida }}"
                    title="Eliminar producción">
                <i class="bi bi-trash"></i>
            </button>
        @endcan
    </div>
</td>

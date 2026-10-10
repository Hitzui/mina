<div class="d-flex justify-content-center gap-1">
    <a href="{{ route('comercial.cotizaciones.show', $id) }}" class="btn btn-sm btn-light" title="Ver cotización">
        <i class="bi bi-eye"></i>
    </a>
    @if($puedeEditar)
        @can('cotizaciones.edit')
            <a href="{{ route('comercial.cotizaciones.edit', $id) }}" class="btn btn-sm btn-light" title="Editar borrador">
                <i class="bi bi-pencil"></i>
            </a>
        @endcan
    @endif
    @if($puedeEliminar)
        @can('cotizaciones.delete')
            <form action="{{ route('comercial.cotizaciones.destroy', $id) }}" method="POST"
                  data-confirm-delete data-confirm-title="¿Eliminar este borrador?"
                  data-confirm-text="La cotización se retirará de la lista."
                  data-confirm-button="Sí, eliminar">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-light text-danger" title="Eliminar borrador">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        @endcan
    @endif
</div>

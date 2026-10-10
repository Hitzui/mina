<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title ?? 'Unidades de medida' }}</x-slot:pageTitle>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles></x-slot:headerFiles>

    <div class="row layout-top-spacing"><div class="col-12"><div class="widget-content widget-content-area br-8">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div><h4 class="mb-1"><i class="bi bi-rulers me-2"></i>Unidades de medida</h4><p class="text-muted mb-0">Administre las unidades usadas en materiales, órdenes, ingresos, producción y costos.</p></div>
            @can('configuracion.unidades_medida.create')
                <button type="button" class="btn btn-primary" id="btnNuevaUnidad"><i class="bi bi-plus me-1"></i>Nueva unidad</button>
            @endcan
        </div>
        <div class="table-responsive">{!! $dataTable->table(['class' => 'table table-hover table-bordered w-100']) !!}</div>
    </div></div></div>

    <div class="modal fade" id="modalUnidad" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content bg-white">
        <form id="formUnidad" action="{{ route('configuracion.unidades_medida.store') }}" data-store-url="{{ route('configuracion.unidades_medida.store') }}" data-update-url="{{ route('configuracion.unidades_medida.update', '__ID__') }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title" id="modalUnidadTitulo">Nueva unidad</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"><label for="unidadNombre" class="form-label">Nombre <span class="text-danger">*</span></label><input id="unidadNombre" name="nombre" class="form-control" maxlength="40" required><div class="invalid-feedback"></div>
                <div class="form-check form-switch mt-3"><input type="checkbox" class="form-check-input" id="unidadEstado" checked><label class="form-check-label" for="unidadEstado">Activa</label><input type="hidden" name="estado" id="unidadEstadoValor" value="1"></div>
                <div id="unidadError" class="alert alert-danger mt-3 d-none"></div>
            </div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" id="btnGuardarUnidad">Guardar unidad</button></div>
        </form>
    </div></div></div>

    <x-slot:footerFiles>{!! $dataTable->scripts() !!}<script src="{{ asset('js/configuracion/unidades_medida.js') }}"></script></x-slot>
</x-base-layout>

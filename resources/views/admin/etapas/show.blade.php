<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Información de la Etapa' }}
    </x-slot>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-sm-12 layout-spacing">

            <div class="widget-content widget-content-area br-8">

                {{-- Encabezado --}}
                <div class="d-flex justify-content-between align-items-center mb-4">

                    <div>
                        <h5 class="mb-1">
                            Información de la Etapa
                        </h5>

                        <p class="text-muted mb-0">
                            Detalle de la etapa de procesamiento.
                        </p>
                    </div>

                    <div>
                        <span class="badge {{ $etapa->estado ? 'bg-success' : 'bg-danger' }}">
                            {{ $etapa->estado ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>

                </div>

                {{-- Información --}}
                <div class="row">

                    {{-- ID --}}
                    <div class="col-md-3 mb-4">
                        <label class="form-label text-muted">
                            ID
                        </label>

                        <div class="fw-bold">
                            {{ $etapa->id }}
                        </div>
                    </div>

                    {{-- Nombre --}}
                    <div class="col-md-6 mb-4">
                        <label class="form-label text-muted">
                            Nombre
                        </label>

                        <div class="fw-bold">
                            {{ $etapa->nombre }}
                        </div>
                    </div>

                    {{-- Orden --}}
                    <div class="col-md-3 mb-4">
                        <label class="form-label text-muted">
                            Orden
                        </label>

                        <div class="fw-bold">
                            {{ $etapa->orden }}
                        </div>
                    </div>

                    {{-- Descripción --}}
                    <div class="col-12 mb-4">
                        <label class="form-label text-muted">
                            Descripción
                        </label>

                        <div>
                            {{ $etapa->descripcion ?: 'Sin descripción' }}
                        </div>
                    </div>

                </div>

                <hr>

                {{-- Información de auditoría --}}
                <div class="row mt-3">

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">
                            Fecha de creación
                        </label>

                        <div>
                            {{ $etapa->created_at?->format('d/m/Y H:i:s') ?? '-' }}
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">
                            Última actualización
                        </label>

                        <div>
                            {{ $etapa->updated_at?->format('d/m/Y H:i:s') ?? '-' }}
                        </div>
                    </div>

                </div>

                {{-- Acciones --}}
                <div class="mt-4 d-flex justify-content-end gap-2">

                    <a
                        href="{{ route('admin.etapas.index') }}"
                        class="btn btn-light">
                        <i class="fa-solid fa-arrow-left"></i> Regresar
                    </a>

                    <a
                        href="{{ route('admin.etapas.edit', $etapa) }}"
                        class="btn btn-warning">
                        <i class="fa-regular fa-pen-to-square"></i> Editar
                    </a>

                    <form
                        action="{{ route('admin.etapas.destroy', $etapa) }}"
                        method="POST"
                        class="d-inline"
                        data-confirm-delete="true">
                        @csrf
                        @method('DELETE')

                        <button
                            type="submit"
                            class="btn btn-danger">
                            <i class="fa-solid fa-trash-arrow-up"></i> Eliminar
                        </button>
                    </form>

                </div>

            </div>

        </div>

    </div>

    <x-slot:footerFiles>
    </x-slot>

</x-base-layout>

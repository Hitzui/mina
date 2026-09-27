<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Sistema de Laboratorio' }}
    </x-slot>

    <x-slot:headerFiles>
    </x-slot>

    <x-breadcrumb :items="$breadcrumbs"/>

    <div class="card">

        <div class="card-header d-flex justify-content-between align-items-center">

            <h5 class="mb-0">
                <i class="bi bi-person me-2"></i>
                Información del Cliente
            </h5>

            <span class="badge {{ $cliente->estado ? 'bg-success' : 'bg-danger' }}">
                {{ $cliente->estado ? 'Activo' : 'Inactivo' }}
            </span>

        </div>

        <div class="card-body">

            <div class="row">

                {{-- Nombre --}}
                <div class="col-md-6 mb-4">
                    <label class="form-label text-muted">
                        Nombre
                    </label>

                    <div class="fs-5 fw-semibold">
                        {{ $cliente->nombre }}
                    </div>
                </div>

                {{-- Teléfono --}}
                <div class="col-md-6 mb-4">
                    <label class="form-label text-muted">
                        Teléfono
                    </label>

                    <div class="fs-5">
                        {{ $cliente->telefono ?: 'No registrado' }}
                    </div>
                </div>

                {{-- Dirección --}}
                <div class="col-md-12 mb-4">
                    <label class="form-label text-muted">
                        Dirección
                    </label>

                    <div class="fs-5">
                        {{ $cliente->direccion ?: 'No registrada' }}
                    </div>
                </div>

                {{-- Fecha de registro --}}
                <div class="col-md-6 mb-4">
                    <label class="form-label text-muted">
                        Fecha de registro
                    </label>

                    <div>
                        {{ $cliente->created_at?->format('d/m/Y H:i') ?? 'No disponible' }}
                    </div>
                </div>

                {{-- Última actualización --}}
                <div class="col-md-6 mb-4">
                    <label class="form-label text-muted">
                        Última actualización
                    </label>

                    <div>
                        {{ $cliente->updated_at?->format('d/m/Y H:i') ?? 'No disponible' }}
                    </div>
                </div>

            </div>

        </div>

        <div class="card-footer">

            <div class="d-flex justify-content-between">

                {{-- Regresar --}}
                <a
                    href="{{ route('admin.clientes.index') }}"
                    class="btn btn-outline-secondary"
                >
                    <i class="bi bi-arrow-left me-1"></i>
                    Regresar
                </a>

                <div class="d-flex gap-2">

                    {{-- Editar --}}
                    <a
                        href="{{ route('admin.clientes.edit', $cliente) }}"
                        class="btn btn-outline-primary"
                    >
                        <i class="bi bi-pencil-square me-1"></i>
                        Editar
                    </a>

                    {{-- Eliminar --}}
                    <form
                        action="{{ route('admin.clientes.destroy', $cliente) }}"
                        method="POST"
                        class="d-inline"
                    >
                        @csrf
                        @method('DELETE')

                        <button
                            type="submit"
                            class="btn btn-outline-danger"
                            data-confirm-delete
                            data-confirm-title="¿Eliminar cliente?"
                            data-confirm-text="¿Desea eliminar el cliente del sistema? Esta acción no se puede revertir."
                            data-confirm-button="Sí, eliminar">
                            <i class="bi bi-trash me-1"></i>
                            Eliminar
                        </button>
                    </form>

                </div>

            </div>

        </div>

    </div>

    <x-slot name="footerFiles">
    </x-slot>

</x-base-layout>

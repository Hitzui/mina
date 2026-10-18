<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title }}</x-slot>
    <x-slot:headerFiles></x-slot><x-slot:footerFiles></x-slot>
    <div class="card mt-4"><div class="card-body">
        <h4>{{ $ordenTrabajo->codigo }} · Tratamiento de lama</h4>
        <p class="text-muted">El lote de origen determina cantidad estimada, unidad, cliente y reparto. Aquí puede corregir fecha y descripción.</p>
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('procesos.ordenes_trabajo.update', $ordenTrabajo) }}">@csrf @method('PUT')
            <label class="form-label">Fecha</label><input class="form-control mb-3" type="date" name="fecha" value="{{ old('fecha', $ordenTrabajo->fecha->toDateString()) }}" min="{{ $ordenTrabajo->loteLama->fecha->toDateString() }}" required>
            <label class="form-label">Descripción</label><textarea class="form-control mb-3" name="descripcion" maxlength="1000">{{ old('descripcion', $ordenTrabajo->descripcion) }}</textarea>
            <button class="btn btn-primary">Guardar</button> <a class="btn btn-light" href="{{ route('procesos.ordenes_trabajo.show', $ordenTrabajo) }}">Volver</a>
        </form>
    </div></div>
</x-base-layout>

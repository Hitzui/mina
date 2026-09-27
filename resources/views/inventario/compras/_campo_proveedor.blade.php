{{--
    Elegir el proveedor de una compra.

    El proveedor se elige en una ventana aparte y no en un desplegable, por
    dos razones, y las dos se notan en cuanto el catalogo tiene unos cuantos:

    - con un desplegable hay que abrirlo y recorrer la lista hasta dar con el
      proveedor, y en un telefono son quince taps con la pantalla medio
      tapada;
    - el desplegable no deja buscar. El buscador del modal filtra por
      nombre mientras se escribe, que es como se busca de verdad cuando ya
      se sabe a quien se le quiere comprar.

    En el formulario quedan dos campos: el id, escondido, que es lo que se
    guarda, y el nombre, a la vista, que es lo que se lee.

    El campo del nombre lleva name a proposito, y no por descuido. El servidor
    no lo lee: el que decide de que proveedor es la compra es el id, y el
    nombre no aparece en ninguna regla de validacion. El nombre va con name
    para que, si la compra vuelve al formulario porque faltaba otra cosa, el
    navegador lo recupere con old() y el campo no aparezca en blanco con el id
    puesto. Sin name, un fallo de validacion en otro campo dejaria el
    formulario con el proveedor medio elegido y sin pista de cual.

    El proveedor que ya tiene la compra se saca de la relacion y no de una
    lista de todos: asi el partial no necesita que le pasen el catalogo
    entero, que con el modal ya no se carga en la pagina.
--}}

@php
    $proveedorDeLaCompra = $compra->exists ? $compra->proveedor : null;

    $nombreDelProveedor = old(
        'proveedor_nombre',
        $proveedorDeLaCompra
            ? $proveedorDeLaCompra->nombre . ' (' . $proveedorDeLaCompra->codigo . ')'
            : null
    );
@endphp

<div class="col-md-4">
    <label for="proveedor_nombre" class="form-label">
        Proveedor <span class="text-danger">*</span>
    </label>

    <div class="input-group">
        <input
            type="text"
            id="proveedor_nombre"
            name="proveedor_nombre"
            class="form-control @error('proveedor_id') is-invalid @enderror"
            value="{{ $nombreDelProveedor }}"
            placeholder="Busque el proveedor"
            readonly
            autocomplete="off"
        >

        <button
            type="button"
            class="btn btn-outline-primary"
            id="btnBuscarProveedor"
            title="Buscar el proveedor en el catálogo"
        >
            <i class="bi bi-search me-1"></i>
            Buscar
        </button>
    </div>

    <input
        type="hidden"
        name="proveedor_id"
        id="proveedor_id"
        value="{{ old('proveedor_id', $compra->proveedor_id) }}"
    >

    <div class="form-text">
        <i class="bi bi-info-circle me-1"></i>
        Busque el proveedor en el catálogo y pulse Elegir. Solo salen los que
        están activos.
    </div>

    @error('proveedor_id')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

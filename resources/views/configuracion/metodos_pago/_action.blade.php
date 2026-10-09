{{--
    Los botones de la fila de un metodo de pago.

    Cuatro botones: ver, editar, activar o desactivar, y eliminar.

    EL DE ELIMINAR NO SE ESCONDE CUANDO EL METODO TIENE COBROS, y es una
    decision que conviene dejar escrita. Podria esconderse —o ponerse gris— con
    un "no se puede" y la lista quedaria mas limpia, pero el que tiene que
    desactivar un metodo en vez de borrarlo no lo sabe todavia: solo lo sabria si
    el boton no estuviera y se preguntara por que. Con el boton ahi y el aviso al
    pulsarlo, el usuario ve la razon exacta —"esta en uso, en 3 cobros"—, que es
    informacion que le sirve igual aunque no quisiera borrar. Y en vez de borrarlo
    lo desactiva, que es el otro camino del mismo problema.

    Y AQUI EL NOMBRE PUEDE REPETIRSE, al contrario que en las monedas, y eso se
    ve en los botones: en el aviso sale el nombre del metodo, no el identificador.
    Con dos metodos que se llamen igual, el aviso es el mismo para los dos, y lo
    que los distingue es que uno tenga cobros y el otro no —la columna de la
    lista— o la descripcion, que es justo para lo que la descripcion esta.

    LOS BOTONES DE BORRAR Y DE ACTIVAR NO VAN DENTRO DE UN FORM.

    Es lo que hacen las otras pantallas y no es casualidad: el javascript no
    recarga la pagina en ninguno de los dos casos, porque hace la peticion con
    jquery y recoge lo que le conteste el servidor. Lo que necesita cada boton son
    sus datos —"la url y el nombre, para armar el aviso"— y esos van en el boton,
    en atributos data. Meterlos en un form que envuelve al boton obligaria a que
    el boton fuera de tipo submit, y entonces el title del formulario acabaria en
    el boton sin querer y el aviso se armaria con lo que encontrara a mano.

    Y el boton de activar o desactivar no lleva aviso, a diferencia del de borrar.
    Es una accion de la que se puede volver a pulsar para lo contrario, asi que
    no hay nada que confirmar, y lo que si lleva es el texto de lo que va a
    pasar en el title. Poner un aviso por cada desactivacion es un aviso que la
    gente aprende a saltar, y este es justo el que importa que se lea.

    El boton de eliminar avisa de lo que no tiene arreglo: un metodo de pago que
    ya recibio cobros no vuelve, y el aviso lo dice antes de hacerlo y no despues.
--}}
<td>
    <div class="d-flex justify-content-center gap-1">

        <button
            type="button"
            class="btn btn-sm btn-light btn-ver-metodo-pago"
            data-url="{{ route('configuracion.metodos_pago.show', $metodoPago) }}"
            title="Ver la ficha de {{ $metodoPago->nombre }}"
        >
            <i class="bi bi-eye"></i>
        </button>

        <button
            type="button"
            class="btn btn-sm btn-light btn-edit-metodo-pago"
            data-url="{{ route('configuracion.metodos_pago.edit', $metodoPago) }}"
            title="Editar {{ $metodoPago->nombre }}"
        >
            <i class="bi bi-pencil"></i>
        </button>

        <button
            type="button"
            class="btn btn-sm btn-light btn-cambiar-estado-metodo-pago"
            data-url="{{ route('configuracion.metodos_pago.cambiar-estado', $metodoPago) }}"
            data-nombre="{{ $metodoPago->nombre }}"
            data-esta-activa="{{ $metodoPago->estado ? '1' : '0' }}"
            title="{{ $metodoPago->estado ? 'Desactivar' : 'Activar' }} {{ $metodoPago->nombre }}"
        >
            <i class="bi {{ $metodoPago->estado ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
        </button>

        <button
            type="button"
            class="btn btn-sm btn-light"
            data-confirm-delete-metodo-pago
            data-url="{{ route('configuracion.metodos_pago.destroy', $metodoPago) }}"
            data-nombre="{{ $metodoPago->nombre }}"
            title="Eliminar {{ $metodoPago->nombre }}"
        >
            <i class="bi bi-trash"></i>
        </button>

    </div>
</td>

{{--
    Los botones de la fila de un tipo de ingreso.

    Cuatro botones: ver, editar, activar o desactivar, y eliminar.

    EL DE ELIMINAR NO SE ESCONDE CUANDO EL TIPO ESTA EN USO, y es una decision
    que conviene dejar escrita. Podria esconderse —o ponerse gris— con un "no se
    puede" y la lista quedaria mas limpia, pero el que tiene que desactivar un
    tipo en vez de borrarlo no lo sabe todavia: solo lo sabria si el boton no
    estuviera y se preguntara por que. Con el boton ahi y el aviso al pulsarlo,
    el usuario ve la razon exacta —"está en uso, en 3 ingresos"—, que es
    informacion que le sirve igual aunque no quisiera borrar. Y en vez de
    borrarlo desactiva, que es el otro camino del mismo problema.

    LOS BOTONES DE BORRAR Y DE ACTIVAR NO VAN DENTRO DE UN FORM.

    Es lo que hacen las otras pantallas y no es casualidad: el javascript no
    recarga la pagina en ninguno de los dos casos, porque hace la peticion con
    jquery y recoge lo que le conteste el servidor. Lo que necesita cada boton
    son sus datos —la url y el nombre, para armar el aviso— y esos van en el
    boton, en atributos data. Meterlos en un form que envuelve al boton
    obligaria a que el boton fuera de tipo submit, y entonces el title del
    formulario acabaria en el boton sin querer y el aviso se armaria con lo que
    encontrara a mano.

    Y el boton de activar o desactivar no lleva aviso, a diferencia del de borrar.
    Es una accion de la que se puede volver a pulsar para lo contrario, asi que
    no hay nada que confirmar, y lo que si lleva es el texto de lo que va a
    pasar en el title. Poner un aviso por cada desactivacion es un aviso que la
    gente aprende a saltar, y este es justo el que importa que se lea.

    El boton de eliminar avisa de lo que no tiene arreglo: un tipo que ya se uso
    no vuelve, y el aviso lo dice antes de hacerlo y no despues.
--}}
<td>
    <div class="d-flex justify-content-center gap-1">

        <button
            type="button"
            class="btn btn-sm btn-light btn-ver-tipo-ingreso"
            data-url="{{ route('configuracion.tipos_ingreso.show', $tipoIngreso) }}"
            title="Ver la ficha de {{ $tipoIngreso->nombre }}"
        >
            <i class="bi bi-eye"></i>
        </button>

        <button
            type="button"
            class="btn btn-sm btn-light btn-edit-tipo-ingreso"
            data-url="{{ route('configuracion.tipos_ingreso.edit', $tipoIngreso) }}"
            title="Editar {{ $tipoIngreso->nombre }}"
        >
            <i class="bi bi-pencil"></i>
        </button>

        <button
            type="button"
            class="btn btn-sm btn-light btn-cambiar-estado-tipo-ingreso"
            data-url="{{ route('configuracion.tipos_ingreso.cambiar-estado', $tipoIngreso) }}"
            data-nombre="{{ $tipoIngreso->nombre }}"
            data-esta-activo="{{ $tipoIngreso->estado ? '1' : '0' }}"
            title="{{ $tipoIngreso->estado ? 'Desactivar' : 'Activar' }} {{ $tipoIngreso->nombre }}"
        >
            <i class="bi {{ $tipoIngreso->estado ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
        </button>

        <button
            type="button"
            class="btn btn-sm btn-light"
            data-confirm-delete-tipo-ingreso
            data-url="{{ route('configuracion.tipos_ingreso.destroy', $tipoIngreso) }}"
            data-nombre="{{ $tipoIngreso->nombre }}"
            title="Eliminar {{ $tipoIngreso->nombre }}"
        >
            <i class="bi bi-trash"></i>
        </button>

    </div>
</td>

{{--
    El aviso de que la orden esta cerrada.

    Esta en un archivo aparte porque sale en dos fichas, la de la orden y la
    de cada proceso, y el texto tiene que ser el mismo en las dos: si en una
    se dijera una cosa y en la otra otra, el usuario dejaria de fiarse de
    los dos.

    Lo que no dice es "no se puede registrar un costo". Eso es el texto del
    boton cuando algo se intenta de verdad; aqui lo que se explica es que
    los botones de abajo estan apagados, y por que.
--}}
@if($ordenTrabajo->estaCerrada())
    <div class="alert alert-warning d-flex align-items-start gap-2 mb-4" role="alert">
        <i class="bi bi-lock-fill mt-1"></i>
        <div>
            <strong class="d-block mb-1">
                Esta orden está {{ mb_strtolower($ordenTrabajo->estadoTexto()) }} y no admite
                datos nuevos
            </strong>
            {{-- 
                Las tres cosas que hacen falta: que no se puede, por que, y
                que se puede hacer. Sin la tercera el usuario se queda
                mirando los botones apagados sin salida, cuando la salida
                existe y es una pulsacion, la del boton de estado de arriba.
            --}}
            No se pueden registrar procesos, costos, consumos, trabajos ni
            equipos. Lo que ya está escrito se sigue editando con normalidad.
            Para seguir trabajando en esta orden hay que volver a ponerla en
            Pendiente desde su botón de estado, aquí arriba.
        </div>
    </div>
@endif
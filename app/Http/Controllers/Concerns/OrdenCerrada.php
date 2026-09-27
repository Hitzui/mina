<?php

namespace App\Http\Controllers\Concerns;

use App\Models\OrdenesTrabajo;
use Illuminate\Http\RedirectResponse;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Lo que hacen igual todas las pantallas que cuelgan de una orden.
 *
 * Hoy es una sola regla: una orden finalizada o cancelada ya no admite datos
 * nuevos. Se corrige lo que ya esta escrito, pero no se anade nada.
 *
 * Por que esta en un trait y no en cada controlador: son seis pantallas
 * distintas (el proceso, los costos de la orden, los del proceso, los
 * consumos, los trabajos de empleado y el uso de equipos) y la regla es una.
 * Si se escribiera en cada store, el dia que se decidiera que "cerrada"
 * incluye tambien el estado 2, habria que acordarse de los seis. Con el
 * metodo este, el cambio se hace en un sitio.
 *
 * Lo que si queda en cada pantalla es el nombre de lo que se intentaba
 * agregar, porque el aviso tiene que poder decir "no se puede registrar un
 * costo" y no "no se puede hacer esto". Un mensaje generico obliga a volver
 * a la pantalla a averiguar que era.
 *
 * Dos caminos de vuelta, y estan los dos puestos a proposito:
 *
 *   - desde la pantalla, los botones de registrar salen apagados y hay un
 *     aviso arriba. Es lo que se ve casi siempre.
 *   - desde el store, si alguien mandara el formulario a mano o con el
 *     boton pulsado justo antes de cerrar la orden, no se guarda nada y se
 *     vuelve a la orden con el motivo. El boton apagado evita el primer
 *     caso; este evita el segundo, que siempre se puede dar.
 *
 * El texto que ve el usuario en la pantalla no sale de aqui: lo pinta el
 * partial _aviso_orden_cerrada, que incluyen las dos fichas. Va alla y no
 * en un metodo de aqui porque no lo usa ningun controlador, solo las
 * vistas, y tener el texto en los dos sitios invita a cambiarlo en uno.
 */
trait OrdenCerrada
{
    /**
     * Si a esta orden ya se le pueden anadir cosas.
     */
    private function ordenAbierta(OrdenesTrabajo $orden): bool
    {
        return ! $orden->estaCerrada();
    }

    /**
     * No deja seguir si la orden esta cerrada.
     *
     * Devuelve la respuesta a devolver tal cual cuando no se puede, y null
     * cuando si se puede. Se escribe asi para que la pantalla diga que sigue
     * sin un if de mas en medio:
     *
     *     if ($bloqueo = $this->bloquearOrdenCerrada($ordenTrabajo, 'un costo')) {
     *         return $bloqueo;
     *     }
     *
     * @param  string  $que  Lo que se pretendia agregar, en minuscula y con
     *                        article: "un costo", "un trabajo de empleado".
     *                        Va dentro del texto del aviso, asi que se lee
     *                        "no se puede registrar un costo".
     */
    private function bloquearOrdenCerrada(OrdenesTrabajo $orden, string $que): ?RedirectResponse
    {
        if ($this->ordenAbierta($orden)) {
            return null;
        }

        Alert::toast($this->mensajeDeOrdenCerrada($orden, $que))->error()->flash();

        return redirect()->route('procesos.ordenes_trabajo.show', $orden);
    }

    /**
     * El texto del aviso, que es el mismo para el boton y para la pantalla.
     *
     * Dice las tres cosas que hacen falta: que no se puede, por que, y que
     * se puede hacer. Sin la tercera el usuario se queda mirando un boton
     * apagado sin salida, cuando la salida existe y es una linea de la ficha
     * de la orden: volver a ponerla en Pendiente.
     */
    private function mensajeDeOrdenCerrada(OrdenesTrabajo $orden, string $que): string
    {
        return sprintf(
            'No se puede registrar %s: la orden %s está %s y una orden cerrada '
            . 'no admite datos nuevos. Lo que ya está escrito se puede editar '
            . 'con normalidad. Si lo que hace falta es seguir trabajando en '
            . 'esta orden, vuelve a ponerla en Pendiente desde su ficha.',
            $que,
            $orden->codigo,
            mb_strtolower($orden->estadoTexto())
        );
    }

}

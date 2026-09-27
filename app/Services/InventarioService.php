<?php

namespace App\Services;

use App\Models\InventarioProducto;
use App\Models\Moneda;
use App\Models\MovimientosInventario;
use App\Models\Producto;
use App\Models\TiposCambio;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El movimiento de material del almacen.
 *
 * Todo lo que entra y sale del stock pasa por aqui. Que este en un solo
 * sitio no es por la forma: es porque las existencias se calculan
 * sumando movimientos, y si cada pantalla actualizase el saldo por su
 * cuenta, dos caminos distintos para el mismo calculo acabarian
 * discrepando y no habria forma de saber cual estaba bien.
 *
 * Lo que se garantiza aqui:
 *
 * - La existencia nunca queda en negativo. Es la regla que pide la
 *   documentacion, y se comprueba antes de escribir nada.
 * - El costo unitario de una salida lo decide el promedio del almacen,
 *   no el formulario. Por eso el metodo arma el movimiento y luego pisa
 *   el precio: si alguien lo trae puesto, se sobrescribe.
 * - El saldo y el movimiento se escriben en la misma transaccion, o no se
 *   escribe ninguno de los dos.
 */
class InventarioService
{
    /**
     * Registra un movimiento de material y deja el saldo al dia.
     *
     * @param  array  $datos  producto_id, tipo, cantidad, fecha, y para
     *                        las entradas costo_unitario, moneda_id,
     *                        referencia, observaciones, y opcionalmente
     *                        orden_trabajo_id y proceso_orden_id
     * @return MovimientosInventario
     *
     * @throws ValidationException
     */
    public function registrar(array $datos): MovimientosInventario
    {
        return DB::transaction(function () use ($datos) {
            $productoId = (int) ($datos['producto_id'] ?? 0);

            /*
             * El saldo se bloquea antes de armar el movimiento, porque el
             * costo unitario de una salida sale de ahi. Sin el bloqueo, dos
             * consumos simultaneos podrian leer el mismo promedio y pasar
             * los dos la comprobacion de existencia, dejando el almacen en
             * negativo entre los dos.
             */
            $saldo = $this->saldoBloqueado($productoId);

            $movimiento = $this->armar($datos, $saldo);

            $this->aplicarAlSaldo($saldo, $movimiento);

            $movimiento->save();

            return $movimiento;
        });
    }

    /**
     * Da de baja un movimiento y devuelve el material al saldo.
     *
     * Al borrar un consumo hay que devolver las existencias, no solo
     * quitar la fila: si no, el almacen queda corto de material que sigue
     * estando ahi, y el siguiente consumo se rechaza por falta de
     * existencias sin que nadie entienda por que.
     */
    public function revertir(MovimientosInventario $movimiento): void
    {
        DB::transaction(function () use ($movimiento) {
            $saldo = $this->saldoBloqueado((int) $movimiento->producto_id);

            $this->aplicarAlSaldo($saldo, $movimiento, revertiendo: true);

            $movimiento->delete();
        });
    }

    /**
     * Arma el movimiento con el tipo normalizado y el precio resuelto.
     */
    private function armar(array $datos, InventarioProducto $saldo): MovimientosInventario
    {
        $movimiento = new MovimientosInventario([
            MovimientosInventario::PRODUCTO_ID => $datos['producto_id'] ?? null,
            MovimientosInventario::ORDEN_TRABAJO_ID => $datos['orden_trabajo_id'] ?? null,
            MovimientosInventario::PROCESO_ORDEN_ID => $datos['proceso_orden_id'] ?? null,
            MovimientosInventario::FECHA => $datos['fecha'] ?? null,
            MovimientosInventario::CANTIDAD => $datos['cantidad'] ?? 0,
            MovimientosInventario::REFERENCIA => $datos['referencia'] ?? null,
            MovimientosInventario::OBSERVACIONES => $datos['observaciones'] ?? null,
            MovimientosInventario::MONEDA_ID => $datos['moneda_id'] ?? null,
        ]);

        /*
         * Se normaliza antes de guardar, para que el texto de la base sea
         * siempre uno del catalogo y no dependa de como lo escribio el
         * usuario. Guardar "Salida" en mayuscula haria que una busqueda
         * por tipo no la encontrara.
         */
        $movimiento->tipo = (string) $datos['tipo'];

        /*
         * El precio de una salida lo pone el inventario: lo que sale de un
         * almacen ya esta valuado en la moneda base. El precio que venga en
         * el formulario se pisa, que es lo que se aseguro con el cliente.
         */
        if ($movimiento->esSalida()) {
            $movimiento->costo_unitario = (float) $saldo->cpp_actual;
        } else {
            $movimiento->costo_unitario = (float) ($datos['costo_unitario'] ?? 0);
        }

        /*
         * La columna moneda_id no admite nulos. Cuando no se indica ninguna
         * se pone la moneda base, que es el NIO: es lo unico que se puede
         * dejar vacio sin que la base lo rechace, y para una salida es ademas
         * lo correcto, porque lo que sale del almacen ya esta en NIO.
         */
        if ($movimiento->moneda_id === null) {
            $movimiento->moneda_id = $this->monedaBaseId();
        }

        // El equivalente en NIO lo pone el tipo de cambio de la fecha
        $movimiento->calcularTotales($this->tipoCambioDe($movimiento));

        return $movimiento;
    }

    /**
     * Mueve el saldo segun el signo del movimiento.
     *
     * @throws ValidationException
     */
    private function aplicarAlSaldo(
        InventarioProducto $saldo,
        MovimientosInventario $movimiento,
        bool $revertiendo = false
    ): void {
        $cantidad = abs((float) $movimiento->cantidad);
        $valor = abs((float) $movimiento->costo_total);

        /*
         * Al revertir, el signo se invierte: lo que salia vuelve a entrar y
         * lo que entria se queda sin haber entrado. Un movimiento de tipo
         * desconocido tiene signo 0, y al revertir contaria como entrada:
         * por eso se sale antes, si no hay tipo no hay nada que revertir.
         */
        $signo = $movimiento->signo();

        if ($signo === 0) {
            return;
        }

        $entra = $revertiendo ? $signo < 0 : $signo > 0;

        if ($entra) {
            $nuevo = $saldo->calcularDespuesDeEntrada($cantidad, $valor);
        } else {
            $this->comprobarExistencia($saldo, $cantidad, $movimiento);
            $nuevo = $saldo->calcularDespuesDeSalida($cantidad, $valor);
        }

        $saldo->cantidad_actual = $nuevo['cantidad'];
        $saldo->valor_actual = $nuevo['valor'];
        $saldo->cpp_actual = $nuevo['cpp'];
        $saldo->save();
    }

    /**
     * No se puede sacar mas material del que hay.
     *
     * Se acepta una diferencia minima para que un redondeo a la baja del
     * saldo no bloquee un consumo que en realidad cabe entero. Sin esto,
     * un saldo que quedara en 0.001 por un redondeo impediria consumir un
     * kilo mas, sin explicacion.
     *
     * @throws ValidationException
     */
    private function comprobarExistencia(
        InventarioProducto $saldo,
        float $cantidad,
        MovimientosInventario $movimiento
    ): void {
        $disponible = (float) $saldo->cantidad_actual;

        if ($cantidad <= $disponible + 0.0005) {
            return;
        }

        $producto = Producto::find($movimiento->producto_id);

        throw ValidationException::withMessages([
            MovimientosInventario::CANTIDAD => sprintf(
                'No hay suficiente material en el almacen: se piden %s y hay %s%s.',
                $this->cantidadLegible($cantidad),
                $this->cantidadLegible($disponible),
                $producto?->unidad_medida ? ' ' . $producto->unidad_medida : ''
            ),
        ]);
    }

    /**
     * El saldo del producto, bloqueado, creandolo si no existe todavia.
     *
     * La fila se asegura antes de bloquearla porque un bloqueo sobre una
     * fila que no existe no bloquea nada, y el alta simultanea de dos
     * consumos del mismo producto dejaria dos saldos en vez de uno.
     */
    private function saldoBloqueado(int $productoId): InventarioProducto
    {
        $existente = InventarioProducto::where(
            InventarioProducto::PRODUCTO_ID,
            $productoId
        )->first();

        if ($existente === null) {
            InventarioProducto::create([
                InventarioProducto::PRODUCTO_ID => $productoId,
                InventarioProducto::CANTIDAD_ACTUAL => 0,
                InventarioProducto::VALOR_ACTUAL => 0,
                InventarioProducto::CPP_ACTUAL => 0,
            ]);
        }

        return InventarioProducto::where(
            InventarioProducto::PRODUCTO_ID,
            $productoId
        )
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Un numero sin ceros de mas, para los mensajes.
     */
    private function cantidadLegible(float $cantidad): string
    {
        return rtrim(rtrim(number_format($cantidad, 3), '0'), '.') ?: '0';
    }

    /**
     * El tipo de cambio de la moneda y la fecha del movimiento.
     *
     * Se usa el mismo central que el resto del sistema, para que un
     * material comprado en dolares y un costo en dolares se conviertan con
     * la misma paridad.
     */
    private function tipoCambioDe(MovimientosInventario $movimiento): ?float
    {
        if ($movimiento->moneda_id === null || $movimiento->fecha === null) {
            return null;
        }

        return TiposCambio::vigentePara(
            (int) $movimiento->moneda_id,
            $movimiento->fecha->toDateString()
        );
    }

    /**
     * La moneda base del sistema, que es el NIO.
     *
     * Se busca cada vez y no se cachea en una constante: si un dia se
     * cambia la moneda base, el valor guardado en el movimiento sigue
     * siendo el que se uso, y lo que cambia es para los movimientos
     * nuevos, que es lo correcto.
     */
    private function monedaBaseId(): ?int
    {
        static $id = null;

        if ($id !== null) {
            return $id;
        }

        $id = Moneda::where('es_moneda_base', true)
            ->value('id');

        return $id === null ? null : (int) $id;
    }
}

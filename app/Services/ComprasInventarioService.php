<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\InventarioProducto;
use App\Models\MovimientosInventario;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * El efecto de una compra sobre el almacen.
 *
 * El documento pide que "compras + inventario" se hagan en una transaccion, y
 * aqui se cumple: o se guardan la compra y sus entradas de almacen, o no se
 * guarda ninguna de las dos. A medias, el kardex contaria material que no
 * esta y las existencias no cuadrarian.
 *
 * La regla que lo gobierna es una sola, y por eso esta en un sitio y no
 * repartida por las pantallas:
 *
 *   - una compra en pendiente, en proceso o cancelada es un papel. El
 *     material no ha entrado.
 *   - una compra finalizada mete el material. Cada linea se convierte en una
 *     entrada de almacen con el precio de la linea, y el costo promedio se
 *     mueve solo.
 *   - al pasar de finalizada a otra cosa, o al corregir una finalizada, el
 *     material sale. Si ya se consumio parte, no se puede deshacer y se dice
 *     por que, en vez de dejar el almacen en negativo.
 */
class ComprasInventarioService
{
    public function __construct(
        private InventarioService $inventario
    ) {
    }

    /**
     * Deja el almacen como toca segun el estado en que quede la compra.
     *
     * Se llama despues de guardar la compra y sus lineas, y decide que hacer
     * comparando con lo que ya habia: si la compra estaba finalizada y deja
     * de estarlo, o si sus lineas cambiaron, primero se deshace lo anterior
     * y luego se mete lo nuevo.
     *
     * @param  Compra  $compra  Ya guardada, con sus lineas en la base
     *
     * @throws ValidationException
     */
    public function sincronizar(Compra $compra): Compra
    {
        return DB::transaction(function () use ($compra) {
            $compra->load('detalles');

            $yaEstabaEnAlmacen = $this->tieneMovimientosVivos($compra);

            $quiereEstarEnAlmacen = $compra->esFinalizada();

            // Nada que hacer: el estado y el almacen ya coinciden
            if (! $yaEstabaEnAlmacen && ! $quiereEstarEnAlmacen) {
                return $compra;
            }

            // Salir del almacen: se deshace lo que habia
            if ($yaEstabaEnAlmacen) {
                $this->deshacerEntradas($compra);
            }

            // Entrar al almacen, si toca
            if ($quiereEstarEnAlmacen) {
                $this->hacerEntradas($compra);
            }

            return $compra;
        });
    }

    /**
     * Si la compra tiene entradas de almacen vivas.
     */
    public function tieneMovimientosVivos(Compra $compra): bool
    {
        return $compra->movimientos()
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Mete el material de cada linea al almacen.
     *
     * @throws ValidationException
     */
    private function hacerEntradas(Compra $compra): void
    {
        $detalles = $compra->detalles;

        if ($detalles->isEmpty()) {
            throw ValidationException::withMessages([
                'detalles' => 'Una compra finalizada tiene que llevar al menos '
                    . 'una línea de material. Sin líneas no entra nada al almacén.',
            ]);
        }

        // El mismo material en dos lineas se mete una sola vez
        $porProducto = $detalles
            ->groupBy('producto_id')
            ->map(function ($lineas) {
                $cantidad = 0.0;
                $valor = 0.0;

                foreach ($lineas as $linea) {
                    $cantidad += (float) $linea->cantidad;
                    $valor += (float) $linea->subtotal;
                }

                return compact('cantidad', 'valor');
            });

        foreach ($porProducto as $productoId => $datos) {
            $cantidad = $datos['cantidad'];
            $valor = $datos['valor'];

            if ($cantidad <= 0) {
                continue;
            }

            /*
             * El costo unitario que entra al almacen es el promedio ponderado
             * de lo que ya habia, no el de la linea. Si el material se
             * compro antes a otro precio, mezclar las dos cantidades da el
             * promedio real, que es el que usaran las salidas.
             */
            $costoUnitario = round($valor / $cantidad, 4);

            $this->inventario->registrar([
                'producto_id' => $productoId,
                'tipo' => MovimientosInventario::TIPO_ENTRADA,

                // La compra es el destino del movimiento, y lo que lo
                // guarda es esta columna; la referencia es solo para que se
                // lea en el kardex
                'compra_id' => $compra->id,
                'orden_trabajo_id' => null,
                'proceso_orden_id' => null,

                'cantidad' => $cantidad,
                'costo_unitario' => $costoUnitario,
                'moneda_id' => $compra->moneda_id,
                'fecha' => $compra->fecha->toDateTimeString(),

                'referencia' => $compra->codigo,
                'observaciones' => 'Entrada por la compra ' . $compra->codigo
                    . ($compra->numero_documento
                        ? ' (documento ' . $compra->numero_documento . ')'
                        : ''),
            ]);
        }
    }

    /**
     * Saca del almacen el material que habia-meterido esta compra.
     *
     * @throws ValidationException
     */
    private function deshacerEntradas(Compra $compra): void
    {
        $movimientos = $compra->movimientos()
            ->whereNull('deleted_at')
            ->get();

        foreach ($movimientos as $movimiento) {
            try {
                $this->inventario->revertir($movimiento);
            } catch (ValidationException $e) {
                /*
                 * No se puede sacar del almacen material que ya se consumio.
                 * Se dice que material es y cuanto se ha usado, en vez de
                 * dejar el almacen en negativo: dejaria las existencias
                 * mintiendo y los materiales siguientes saldrian gratis.
                 */
                $producto = Producto::find($movimiento->producto_id);

                /*
                 * El mensaje lleva las dos cifras que explican el
                 * problema: cuanto entro y cuanto queda. Con las dos se
                 * entiende que se gastó el material, y no hace falta ir a
                 * restar a mano para saber cuanto.
                 */
                $entrada = rtrim(
                    rtrim(number_format((float) $movimiento->cantidad, 3), '0'),
                    '.'
                ) ?: '0';

                $disponible = rtrim(
                    rtrim(number_format($producto?->existencia ?? 0, 3), '0'),
                    '.'
                ) ?: '0';

                $unidad = $producto?->unidad_medida ?? '';

                throw ValidationException::withMessages([
                    'estado' => sprintf(
                        'No se puede deshacer la compra %s: entraron %s %s de %s '
                        . 'y ahora el almacén solo tiene %s %s, así que ese '
                        . 'material ya se consumió en algún proceso. Primero '
                        . 'deshaz esos consumos y vuelve a intentarlo.',
                        $compra->codigo,
                        $entrada,
                        $unidad,
                        $producto?->nombre ?? 'ese material',
                        $disponible,
                        $unidad
                    ),
                ]);
            }
        }
    }

    /**
     * Quita del kardex todo lo de una compra, sin tocar el saldo.
     *
     * Es para borrar la compra entera: los movimientos se dan de baja sin
     * devolver el material, porque la compra que los genero ya no esta y no
     * hay a que devolver nada. El saldo se ajusta en el mismo paso, quitando
     * la cantidad y el valor que esos movimientos sumo, para que el resumen
     * del almacen no herede material de una compra que ya no existe.
     *
     * @throws RuntimeException
     */
    public function olvidar(Compra $compra): void
    {
        DB::transaction(function () use ($compra) {
            $movimientos = $compra->movimientos()->get();

            foreach ($movimientos as $movimiento) {
                if ($movimiento->deleted_at !== null) {
                    continue;
                }

                // El saldo se corrige a mano en vez de con revertir(),
                // porque revertir() devolveria el material al almacen y aqui
                // lo que se quiere es quitarlo de los dos sitios
                $saldo = InventarioProducto::where(
                    InventarioProducto::PRODUCTO_ID,
                    $movimiento->producto_id
                )->lockForUpdate()
                    ->first();

                if ($saldo !== null) {
                    $saldo->cantidad_actual = round(
                        (float) $saldo->cantidad_actual - abs((float) $movimiento->cantidad),
                        3
                    );
                    $saldo->valor_actual = round(
                        (float) $saldo->valor_actual - abs((float) $movimiento->costo_total),
                        2
                    );

                    /*
                     * El promedio se recalcula con lo que queda. Borrar una
                     * compra deshace una entrada, y una entrada que se
                     * deshace cambia el promedio: dejarlo como estaba
                     * significaria medir el costo del almacen contra un
                     * material que ya no esta.
                     */
                    $saldo->recalcularPromedio();
                    $saldo->save();
                }

                $movimiento->delete();
            }
        });
    }
}

<?php

namespace App\Models;

use App\Models\Base\InventarioProducto as BaseInventarioProducto;

/**
 * El saldo de un producto en el almacen.
 *
 * Esta tabla es el estado resumido; la fuente historica es el kardex
 * (movimientos_inventario), como dice la documentacion. Aquí solo vive lo
 * que hace falta para no recalcular todo el historial cada vez que se
 * consulta una existencia: cuanto hay y a cuanto costo promedio.
 */
class InventarioProducto extends BaseInventarioProducto
{
    protected $fillable = [
        self::PRODUCTO_ID,
        self::CANTIDAD_ACTUAL,
        self::VALOR_ACTUAL,
        self::CPP_ACTUAL
    ];

    /**
     * El producto al que pertenece este saldo.
     */
    public function producto()
    {
        return $this->belongsTo(Producto::class, Producto::ID);
    }

    /**
     * Suma una cantidad con su valor y recalcula el costo promedio.
     *
     * Es la formula del costo promedio ponderado, y es la unica manera de
     * meter material: se mezcla lo que hay con lo que entra, y el promedio
     * se desplaza hacia el precio nuevo en proporcion a las cantidades.
     *
     * Devuelve los tres valores ya redondeados, sin guardar, para que
     * quien llame pueda guardarlos dentro de su transaccion.
     *
     * @return array{cantidad: float, valor: float, cpp: float}
     */
    public function calcularDespuesDeEntrada(float $cantidad, float $valor): array
    {
        $cantidadAntes = (float) $this->cantidad_actual;
        $valorAntes = (float) $this->valor_actual;

        $cantidadDespues = $cantidadAntes + $cantidad;
        $valorDespues = $valorAntes + $valor;

        /*
         * Si la cantidad queda en cero no hay nada que promediar. Pasa al
         * vaciar el almacen y volver a llenarlo: si no, se dividiria entre
         * cero y el costo promedio quedaria en 0, con lo que el siguiente
         * consumo saldría a coste cero sin que nada lo avise.
         */
        $cpp = $cantidadDespues > 0
            ? $valorDespues / $cantidadDespues
            : (float) $this->cpp_actual;

        return [
            'cantidad' => round($cantidadDespues, 3),
            'valor' => round($valorDespues, 2),
            'cpp' => round($cpp, 4),
        ];
    }

    /**
     * Resta una cantidad valorizada y deja el promedio como estaba.
     *
     * Al sacar material el promedio no se recalcula: lo que sale se
     * valora al promedio que habia antes, que es justamente lo que hace
     * que el costo promedio sea estable. Recalcularlo aqui haria que cada
     * salida moviera el precio de las siguientes y el costo de un proceso
     * dependeria del orden en que se registraron los consumos.
     *
     * @return array{cantidad: float, valor: float, cpp: float}
     */
    public function calcularDespuesDeSalida(float $cantidad, float $valor): array
    {
        return [
            'cantidad' => round((float) $this->cantidad_actual - $cantidad, 3),
            'valor' => round((float) $this->valor_actual - $valor, 2),

            // El promedio no se toca en una salida
            'cpp' => round((float) $this->cpp_actual, 4),
        ];
    }

    /**
     * Recalcula el costo promedio con lo que queda en el almacen.
     *
     * Es para cuando se deshace una entrada, no cuando se consume: al
     * deshacer, el material se devuelve porque no llego a estar dentro, y el
     * promedio de lo que queda es otro. Si la cantidad queda en cero se
     * conserva el ultimo promedio, que es lo que dice la documentacion: en
     * vacio no hay nada que promediar, y ponerlo a cero haria que el
     * siguiente consumo saliera a coste cero.
     */
    public function recalcularPromedio(): self
    {
        $cantidad = (float) $this->cantidad_actual;

        if ($cantidad > 0) {
            $this->cpp_actual = round((float) $this->valor_actual / $cantidad, 4);
        }

        return $this;
    }

    /**
     * Si queda material por debajo del minimo del producto.
     *
     * Sirve para avisar, no para impedir: un faltante de stock es una
     * aviso de reponer, y el consumo ya ocurrio de todos modos.
     */
    public function estaPorDebajoDelMinimo(): bool
    {
        $minimo = (float) ($this->producto?->stock_minimo ?? 0);

        return $minimo > 0 && (float) $this->cantidad_actual < $minimo;
    }
}

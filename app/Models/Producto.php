<?php

namespace App\Models;

use App\Models\Base\Producto as BaseProducto;

class Producto extends BaseProducto
{
	protected $fillable = [
		self::CODIGO,
		self::NOMBRE,
		self::DESCRIPCION,
		self::UNIDAD_MEDIDA,
		self::CATEGORIA,
		self::STOCK_MINIMO,
		self::ESTADO
	];

	/*
	 * Como se muestra un producto en los combos y en las pantallas.
	 *
	 * El producto no tiene columna "nombre corto": se describe con el
	 * codigo y el nombre, y la unidad detras, porque en un consumo de
	 * 200 lo que importa es saber si son kilos o toneladas.
	 */
	public function getNombreCompletoAttribute(): string
	{
		return $this->nombre . ' (' . $this->unidad_medida . ')';
	}

	/**
	 * El saldo de este producto en el almacen.
	 */
	public function inventario()
	{
		return $this->hasOne(InventarioProducto::class);
	}

	/**
	 * Todos los movimientos de este producto, entradas y salidas.
	 */
	public function movimientos()
	{
		return $this->hasMany(MovimientosInventario::class);
	}

	/**
	 * Cuanto hay en el almacen ahora mismo.
	 *
	 * Sale del saldo resumido y no de sumar el kardex: el kardex es la
	 * fuente historica, pero recorrerlo entero para cada fila de un
	 * listado seria lento, y el saldo se mantiene con el mismo criterio
	 * en cada movimiento.
	 */
	public function getExistenciaAttribute(): float
	{
		if ($this->relationLoaded('inventario')) {
			return (float) ($this->inventario?->cantidad_actual ?? 0);
		}

		return (float) ($this->inventario()->value('cantidad_actual') ?? 0);
	}

	/**
	 * A cuanto costo promedio sale de aqui un kilo, un litro o lo que sea.
	 */
	public function getCostoPromedioAttribute(): float
	{
		if ($this->relationLoaded('inventario')) {
			return (float) ($this->inventario?->cpp_actual ?? 0);
		}

		return (float) ($this->inventario()->value('cpp_actual') ?? 0);
	}

	/**
	 * Cuanto vale en NIO todo lo que hay de este producto.
	 */
	public function getValorInventarioAttribute(): float
	{
		if ($this->relationLoaded('inventario')) {
			return (float) ($this->inventario?->valor_actual ?? 0);
		}

		return (float) ($this->inventario()->value('valor_actual') ?? 0);
	}

	/**
	 * Si el material esta por debajo del minimo con el que se quiere
	 * tener en el almacen.
	 *
	 * Es un aviso de reponer, no un estado: no impide consumir ni
	 * comprar. Solo dice que conviene mirar.
	 */
	public function estaPorDebajoDelMinimo(): bool
	{
		$minimo = (float) $this->stock_minimo;

		return $minimo > 0 && $this->existencia < $minimo;
	}
}

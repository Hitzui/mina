<?php

namespace App\Models;

use App\Models\Base\TiposPagoEmpleado as BaseTiposPagoEmpleado;

class TiposPagoEmpleado extends BaseTiposPagoEmpleado
{
	/**
	 * La tarifa ES el pago completo.
	 *
	 * Se usa para pagos "por trabajo" o "fijo": la cantidad se registra
	 * como dato informativo (por ejemplo, las horas que tomó) pero no
	 * multiplica el pago.
	 */
	public const METODO_TARIFA = 'TARIFA';

	/**
	 * El pago es cantidad × tarifa.
	 *
	 * Se usa para pagos por hora, por día, por unidad, etc.
	 */
	public const METODO_CANTIDAD_X_TARIFA = 'CANTIDAD_X_TARIFA';

	protected $fillable = [
		self::NOMBRE,
		self::CODIGO,
		self::METODO_CALCULO,
		self::DESCRIPCION,
		self::ESTADO
	];

	/**
	 * Métodos de cálculo disponibles, con su texto para el formulario.
	 */
	public static function metodosCalculo(): array
	{
		return [
			self::METODO_TARIFA =>
				'La tarifa es el pago total (la cantidad es informativa)',
			self::METODO_CANTIDAD_X_TARIFA =>
				'Cantidad × tarifa (la cantidad multiplica)',
		];
	}

	/**
	 * Calcular el total según el método configurado en el tipo de pago.
	 *
	 * @param  float  $cantidad
	 * @param  float  $tarifa
	 * @return float
	 */
	public function calcularTotal($cantidad, $tarifa): float
	{
		return $this->metodo_calculo === self::METODO_TARIFA
			? round((float) $tarifa, 2)
			: round((float) $cantidad * (float) $tarifa, 2);
	}
}

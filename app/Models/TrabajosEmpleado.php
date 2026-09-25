<?php

namespace App\Models;

use App\Models\Base\TrabajosEmpleado as BaseTrabajosEmpleado;

class TrabajosEmpleado extends BaseTrabajosEmpleado
{
	/**
	 * Etiqueta para el trabajo que no pertenece a ningun proceso de la
	 * orden, sino a la orden misma.
	 *
	 * En la base de datos ese caso es proceso_orden_id nulo, y en el
	 * formulario se elige con un option de valor vacio (que
	 * ConvertEmptyStringsToNull convierte a null al guardar).
	 *
	 * Vive aqui para que el combo y la pantalla de detalle no digan
	 * cosas distintas.
	 */
	public const ETIQUETA_GENERAL = 'General a la OT';

	protected $fillable = [
		self::EMPLEADO_ID,
		self::ORDEN_TRABAJO_ID,
		self::PROCESO_ORDEN_ID,
		self::TIPO_PAGO_ID,
		self::FECHA,
		self::HORA_INICIO,
		self::HORA_FIN,
		self::DESCRIPCION,
		self::CANTIDAD,
		self::TARIFA,
		self::TOTAL,
		self::TARIFA_NIO,
		self::TOTAL_NIO,
		self::MONEDA_ID,
		self::OBSERVACIONES,
		self::UNIDAD,
        self::TIPO_CAMBIO
	];
}

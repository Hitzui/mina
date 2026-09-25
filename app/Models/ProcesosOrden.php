<?php

namespace App\Models;

use App\Models\Base\ProcesosOrden as BaseProcesosOrden;

class ProcesosOrden extends BaseProcesosOrden
{
	/**
	 * Como se muestra un proceso en los combos y en las pantallas.
	 *
	 * El proceso no tiene columna "nombre": se describe con el codigo y,
	 * si tiene, la etapa. Sin esto cada pantalla tiene que armarlo a mano
	 * y es facil que una se quede en blanco (que es lo que pasaba en el
	 * detalle del trabajo de un empleado: pedia ->nombre, que no existe,
	 * y por eso siempre caia en la etiqueta de trabajo general).
	 */
	public function getNombreCompletoAttribute(): string
	{
		$etapa = $this->etapa?->nombre;

		return $etapa
			? $this->codigo . ' - ' . $etapa
			: (string) $this->codigo;
	}

	protected $fillable = [
		self::ORDEN_TRABAJO_ID,
		self::ETAPA_ID,
		self::FECHA_INICIO,
		self::FECHA_FIN,
		self::PESO_ENTRADA,
		self::PESO_SALIDA,
		self::ESTADO,
		self::OBSERVACIONES,
		self::CODIGO
	];
}

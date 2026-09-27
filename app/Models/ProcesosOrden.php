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

	/**
	 * Los trabajos de los empleados que se registran en este proceso.
	 */
	public function trabajos_empleados()
	{
		return $this->hasMany(TrabajosEmpleado::class, TrabajosEmpleado::PROCESO_ORDEN_ID);
	}

	/**
	 * Cuanto se le paga a los empleados por este proceso: la suma de los
	 * totales de los trabajos registrados.
	 *
	 * Se calcula al momento y no se guarda: asi nunca puede quedar
	 * desfasado respecto a los trabajos, que es el problema tipico de
	 * mantener un total duplicado.
	 *
	 * Usa withTrashed() a proposito para que el total refleje el
	 * historial, incluidos los trabajos que se han borrado logicamente.
	 * Si lo que se quiere es solo lo que esta vigente, quitarlo.
	 */
	public function getCostoEmpleadosAttribute(): float
	{
		// Con la relacion ya cargada no hay que volver a consultar
		if ($this->relationLoaded('trabajos_empleados')) {
			return (float) $this->trabajos_empleados
				->sum(fn ($trabajo) => (float) $trabajo->total);
		}

		return (float) $this->trabajos_empleados()->withTrashed()->sum('total');
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

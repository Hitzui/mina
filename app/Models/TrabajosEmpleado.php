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
	 *
	 * retired: el trabajo ahora cuelga siempre de un proceso, asi que
	 * este caso ya no existe. Se deja el texto por si hay que mostrarlo
	 * en trabajos antiguos migrados.
	 */
	public const ETIQUETA_GENERAL = 'General a la OT';

	protected $fillable = [
		self::EMPLEADO_ID,
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

	/**
	 * La orden de trabajo se alcanza a traves del proceso.
	 *
	 * Antes el trabajo guardaba tambien orden_trabajo_id y podia quedar
	 * desincronizado del proceso. Ahora hay una sola fuente de verdad: el
	 * proceso, y la orden sale de el con un hasOneThrough.
	 *
	 * Devuelve una relacion de verdad y no un modelo suelto a proposito:
	 * asi funciona con with(), con el construtor de eager loading y con
	 * los metodos de Collection.
	 */
	public function orden_trabajo()
	{
		return $this->hasOneThrough(
			OrdenesTrabajo::class,
			ProcesosOrden::class,
			// clave en procesos_orden
			'id',
			// clave en ordenes_trabajo
			'id',
			// clave en trabajos_empleados
			'proceso_orden_id',
			// clave foranea en procesos_orden
			'orden_trabajo_id'
		);
	}
}

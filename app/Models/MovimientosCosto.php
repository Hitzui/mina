<?php

namespace App\Models;

use App\Models\Base\MovimientosCosto as BaseMovimientosCosto;
use Illuminate\Validation\ValidationException;

class MovimientosCosto extends BaseMovimientosCosto
{
	protected $fillable = [
		self::ORDEN_TRABAJO_ID,
		self::PROCESO_ORDEN_ID,
		self::CATEGORIA_COSTO_ID,
		self::FECHA,
		self::DESCRIPCION,
		self::CANTIDAD,
		self::COSTO_UNITARIO,
		self::COSTO_TOTAL,
		self::COSTO_UNITARIO_NIO,
		self::COSTO_TOTAL_NIO,
		self::MONEDA_ID,
		self::OBSERVACIONES
	];

	/**
	 * Los costos que se registran a mano, de toda la orden.
	 *
	 * Un costo con proceso_orden_id en NULL es un gasto de la orden en
	 * general: alquiler, transporte, un insumo que no pertenece a un
	 * proceso concreto. Los que sí son de un proceso se llegan por el.
	 */
	public function scopeGeneralesDe($query, int $ordenTrabajoId)
	{
		return $query->where(self::ORDEN_TRABAJO_ID, $ordenTrabajoId)
			->whereNull(self::PROCESO_ORDEN_ID);
	}

	/**
	 * Los costos que son de un proceso concreto.
	 */
	public function scopeDeProceso($query, int $procesoOrdenId)
	{
		return $query->where(self::PROCESO_ORDEN_ID, $procesoOrdenId);
	}

	/**
	 * Calcula el total y su equivalente en NIO, y los deja actualizados.
	 *
	 * El total nunca se acepta del formulario: se calcula aqui con la
	 * cantidad y el costo unitario, que es lo unico que viene de la
	 * pantalla. Si se recibiera ya calculado, un valor equivocado en el
	 * formulario se guardaria como si fuera verdad y nadie lo notaria.
	 *
	 * El equivalente en NIO se guarda y no se recalcula al vuelo, por el
	 * mismo motivo que la depreciacion: si manana se corrige el tipo de
	 * cambio de esa fecha, el costo de una orden ya cerrada no debe
	 * moverse. Es la misma regla historica que sigue el resto del sistema.
	 */
	public function calcularTotales(?float $tipoCambio): self
	{
		$this->costo_total = round(
			(float) $this->cantidad * (float) $this->costo_unitario,
			2
		);

		if ($tipoCambio !== null) {
			$this->costo_unitario_nio = round(
				(float) $this->costo_unitario * $tipoCambio,
				4
			);
			$this->costo_total_nio = round(
				$this->costo_total * $tipoCambio,
				2
			);
		}

		return $this;
	}

	/**
	 * Comprueba que el proceso, si lo hay, sea de esta misma orden.
	 *
	 * Con las dos columnas en la fila, nada impide guardar un proceso de
	 * una orden junto al id de otra: el costo aparecería en el desglose de
	 * una OT sin haber pasado por ella. Es la regla que pide la
	 * documentacion, y vive aqui para que ningun camino la esquive.
	 *
	 * @throws ValidationException
	 */
	public function validarProcesoPertenece(): void
	{
		if ($this->proceso_orden_id === null) {
			return;
		}

		$proceso = ProcesosOrden::find($this->proceso_orden_id);

		if ($proceso === null) {
			throw ValidationException::withMessages([
				self::PROCESO_ORDEN_ID => 'El proceso seleccionado no existe.',
			]);
		}

		if ((int) $proceso->orden_trabajo_id !== (int) $this->orden_trabajo_id) {
			throw ValidationException::withMessages([
				self::PROCESO_ORDEN_ID => 'El proceso seleccionado pertenece a otra orden de trabajo.',
			]);
		}
	}
}

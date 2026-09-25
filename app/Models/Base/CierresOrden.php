<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\OrdenesTrabajo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class CierresOrden
 * 
 * @property int $id
 * @property string $codigo
 * @property int $orden_trabajo_id
 * @property Carbon $fecha_cierre
 * @property float $total_costos
 * @property float $total_ingresos
 * @property float $utilidad
 * @property float $total_cobrado
 * @property float $saldo_pendiente
 * @property float $gramos_recuperados
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property OrdenesTrabajo $orden_trabajo
 *
 * @package App\Models\Base
 */
class CierresOrden extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const CODIGO = 'codigo';
	const ORDEN_TRABAJO_ID = 'orden_trabajo_id';
	const FECHA_CIERRE = 'fecha_cierre';
	const TOTAL_COSTOS = 'total_costos';
	const TOTAL_INGRESOS = 'total_ingresos';
	const UTILIDAD = 'utilidad';
	const TOTAL_COBRADO = 'total_cobrado';
	const SALDO_PENDIENTE = 'saldo_pendiente';
	const GRAMOS_RECUPERADOS = 'gramos_recuperados';
	const OBSERVACIONES = 'observaciones';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'cierres_orden';

	protected $casts = [
		self::ID => 'int',
		self::ORDEN_TRABAJO_ID => 'int',
		self::FECHA_CIERRE => 'datetime',
		self::TOTAL_COSTOS => 'float',
		self::TOTAL_INGRESOS => 'float',
		self::UTILIDAD => 'float',
		self::TOTAL_COBRADO => 'float',
		self::SALDO_PENDIENTE => 'float',
		self::GRAMOS_RECUPERADOS => 'float',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function orden_trabajo()
	{
		return $this->belongsTo(OrdenesTrabajo::class, \App\Models\CierresOrden::ORDEN_TRABAJO_ID);
	}
}

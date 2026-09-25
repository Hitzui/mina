<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\CategoriasCosto;
use App\Models\Moneda;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class MovimientosCosto
 * 
 * @property int $id
 * @property int $orden_trabajo_id
 * @property int|null $proceso_orden_id
 * @property int $categoria_costo_id
 * @property Carbon $fecha
 * @property string $descripcion
 * @property float $cantidad
 * @property float $costo_unitario
 * @property float $costo_total
 * @property float|null $costo_unitario_nio
 * @property float|null $costo_total_nio
 * @property int $moneda_id
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property CategoriasCosto $categoria_costo
 * @property Moneda $moneda
 * @property OrdenesTrabajo $orden_trabajo
 * @property ProcesosOrden|null $proceso_orden
 *
 * @package App\Models\Base
 */
class MovimientosCosto extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const ORDEN_TRABAJO_ID = 'orden_trabajo_id';
	const PROCESO_ORDEN_ID = 'proceso_orden_id';
	const CATEGORIA_COSTO_ID = 'categoria_costo_id';
	const FECHA = 'fecha';
	const DESCRIPCION = 'descripcion';
	const CANTIDAD = 'cantidad';
	const COSTO_UNITARIO = 'costo_unitario';
	const COSTO_TOTAL = 'costo_total';
	const COSTO_UNITARIO_NIO = 'costo_unitario_nio';
	const COSTO_TOTAL_NIO = 'costo_total_nio';
	const MONEDA_ID = 'moneda_id';
	const OBSERVACIONES = 'observaciones';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'movimientos_costos';

	protected $casts = [
		self::ID => 'int',
		self::ORDEN_TRABAJO_ID => 'int',
		self::PROCESO_ORDEN_ID => 'int',
		self::CATEGORIA_COSTO_ID => 'int',
		self::FECHA => 'datetime',
		self::CANTIDAD => 'float',
		self::COSTO_UNITARIO => 'float',
		self::COSTO_TOTAL => 'float',
		self::COSTO_UNITARIO_NIO => 'float',
		self::COSTO_TOTAL_NIO => 'float',
		self::MONEDA_ID => 'int',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function categoria_costo()
	{
		return $this->belongsTo(CategoriasCosto::class, \App\Models\MovimientosCosto::CATEGORIA_COSTO_ID);
	}

	public function moneda()
	{
		return $this->belongsTo(Moneda::class);
	}

	public function orden_trabajo()
	{
		return $this->belongsTo(OrdenesTrabajo::class, \App\Models\MovimientosCosto::ORDEN_TRABAJO_ID);
	}

	public function proceso_orden()
	{
		return $this->belongsTo(ProcesosOrden::class, \App\Models\MovimientosCosto::PROCESO_ORDEN_ID);
	}
}

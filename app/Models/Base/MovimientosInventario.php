<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\Moneda;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use App\Models\Producto;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class MovimientosInventario
 * 
 * @property int $id
 * @property int $producto_id
 * @property int|null $orden_trabajo_id
 * @property int|null $proceso_orden_id
 * @property string $tipo
 * @property Carbon $fecha
 * @property float $cantidad
 * @property int $moneda_id
 * @property float|null $costo_unitario
 * @property float|null $costo_total
 * @property string|null $referencia
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property float|null $costo_unitario_nio
 * @property float|null $costo_total_nio
 * 
 * @property Moneda $moneda
 * @property OrdenesTrabajo|null $orden_trabajo
 * @property ProcesosOrden|null $proceso_orden
 * @property Producto $producto
 *
 * @package App\Models\Base
 */
class MovimientosInventario extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const PRODUCTO_ID = 'producto_id';
	const ORDEN_TRABAJO_ID = 'orden_trabajo_id';
	const PROCESO_ORDEN_ID = 'proceso_orden_id';
	const TIPO = 'tipo';
	const FECHA = 'fecha';
	const CANTIDAD = 'cantidad';
	const MONEDA_ID = 'moneda_id';
	const COSTO_UNITARIO = 'costo_unitario';
	const COSTO_TOTAL = 'costo_total';
	const REFERENCIA = 'referencia';
	const OBSERVACIONES = 'observaciones';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	const COSTO_UNITARIO_NIO = 'costo_unitario_nio';
	const COSTO_TOTAL_NIO = 'costo_total_nio';
	protected $table = 'movimientos_inventario';

	protected $casts = [
		self::ID => 'int',
		self::PRODUCTO_ID => 'int',
		self::ORDEN_TRABAJO_ID => 'int',
		self::PROCESO_ORDEN_ID => 'int',
		self::FECHA => 'datetime',
		self::CANTIDAD => 'float',
		self::MONEDA_ID => 'int',
		self::COSTO_UNITARIO => 'float',
		self::COSTO_TOTAL => 'float',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime',
		self::COSTO_UNITARIO_NIO => 'float',
		self::COSTO_TOTAL_NIO => 'float'
	];

	public function moneda()
	{
		return $this->belongsTo(Moneda::class);
	}

	public function orden_trabajo()
	{
		return $this->belongsTo(OrdenesTrabajo::class, \App\Models\MovimientosInventario::ORDEN_TRABAJO_ID);
	}

	public function proceso_orden()
	{
		return $this->belongsTo(ProcesosOrden::class, \App\Models\MovimientosInventario::PROCESO_ORDEN_ID);
	}

	public function producto()
	{
		return $this->belongsTo(Producto::class);
	}
}

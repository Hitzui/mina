<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\DetalleCobro;
use App\Models\Liquidacione;
use App\Models\Moneda;
use App\Models\OrdenesTrabajo;
use App\Models\TiposIngreso;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Ingreso
 * 
 * @property int $id
 * @property int $orden_trabajo_id
 * @property int|null $liquidacion_id
 * @property int $tipo_ingreso_id
 * @property Carbon $fecha
 * @property string|null $descripcion
 * @property float|null $cantidad
 * @property string|null $unidad_medida
 * @property float|null $precio_unitario
 * @property float $total
 * @property float|null $precio_unitario_nio
 * @property float|null $total_nio
 * @property int $moneda_id
 * @property int $estado
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Liquidacione|null $liquidacion
 * @property Moneda $moneda
 * @property OrdenesTrabajo $orden_trabajo
 * @property TiposIngreso $tipo_ingreso
 * @property Collection|DetalleCobro[] $detalle_cobros
 *
 * @package App\Models\Base
 */
class Ingreso extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const ORDEN_TRABAJO_ID = 'orden_trabajo_id';
	const LIQUIDACION_ID = 'liquidacion_id';
	const TIPO_INGRESO_ID = 'tipo_ingreso_id';
	const FECHA = 'fecha';
	const DESCRIPCION = 'descripcion';
	const CANTIDAD = 'cantidad';
	const UNIDAD_MEDIDA = 'unidad_medida';
	const PRECIO_UNITARIO = 'precio_unitario';
	const TOTAL = 'total';
	const PRECIO_UNITARIO_NIO = 'precio_unitario_nio';
	const TOTAL_NIO = 'total_nio';
	const MONEDA_ID = 'moneda_id';
	const ESTADO = 'estado';
	const OBSERVACIONES = 'observaciones';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'ingresos';

	protected $casts = [
		self::ID => 'int',
		self::ORDEN_TRABAJO_ID => 'int',
		self::LIQUIDACION_ID => 'int',
		self::TIPO_INGRESO_ID => 'int',
		self::FECHA => 'datetime',
		self::CANTIDAD => 'float',
		self::PRECIO_UNITARIO => 'float',
		self::TOTAL => 'float',
		self::PRECIO_UNITARIO_NIO => 'float',
		self::TOTAL_NIO => 'float',
		self::MONEDA_ID => 'int',
		self::ESTADO => 'int',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function liquidacion()
	{
		return $this->belongsTo(Liquidacione::class, \App\Models\Ingreso::LIQUIDACION_ID);
	}

	public function moneda()
	{
		return $this->belongsTo(Moneda::class);
	}

	public function orden_trabajo()
	{
		return $this->belongsTo(OrdenesTrabajo::class, \App\Models\Ingreso::ORDEN_TRABAJO_ID);
	}

	public function tipo_ingreso()
	{
		return $this->belongsTo(TiposIngreso::class, \App\Models\Ingreso::TIPO_INGRESO_ID);
	}

	public function detalle_cobros()
	{
		return $this->hasMany(DetalleCobro::class);
	}
}

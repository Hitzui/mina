<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\Ingreso;
use App\Models\OrdenesTrabajo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Liquidacione
 * 
 * @property int $id
 * @property int $orden_trabajo_id
 * @property Carbon $fecha
 * @property float $gramos_recuperados
 * @property float $gramos_cliente
 * @property float $gramos_empresa
 * @property float $porcentaje_cliente
 * @property float $porcentaje_empresa
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property OrdenesTrabajo $orden_trabajo
 * @property Collection|Ingreso[] $ingresos_where_liquidacion
 *
 * @package App\Models\Base
 */
class Liquidacione extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const ORDEN_TRABAJO_ID = 'orden_trabajo_id';
	const FECHA = 'fecha';
	const GRAMOS_RECUPERADOS = 'gramos_recuperados';
	const GRAMOS_CLIENTE = 'gramos_cliente';
	const GRAMOS_EMPRESA = 'gramos_empresa';
	const PORCENTAJE_CLIENTE = 'porcentaje_cliente';
	const PORCENTAJE_EMPRESA = 'porcentaje_empresa';
	const OBSERVACIONES = 'observaciones';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'liquidaciones';

	protected $casts = [
		self::ID => 'int',
		self::ORDEN_TRABAJO_ID => 'int',
		self::FECHA => 'datetime',
		self::GRAMOS_RECUPERADOS => 'float',
		self::GRAMOS_CLIENTE => 'float',
		self::GRAMOS_EMPRESA => 'float',
		self::PORCENTAJE_CLIENTE => 'float',
		self::PORCENTAJE_EMPRESA => 'float',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function orden_trabajo()
	{
		return $this->belongsTo(OrdenesTrabajo::class, \App\Models\Liquidacione::ORDEN_TRABAJO_ID);
	}

	public function ingresos_where_liquidacion()
	{
		return $this->hasMany(Ingreso::class, Ingreso::LIQUIDACION_ID);
	}
}

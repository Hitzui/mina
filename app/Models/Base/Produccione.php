<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use App\Models\TiposProduccion;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Produccione
 * 
 * @property int $id
 * @property int $orden_trabajo_id
 * @property int|null $proceso_orden_id
 * @property int $tipo_produccion_id
 * @property Carbon $fecha
 * @property string|null $descripcion
 * @property float $cantidad
 * @property string $unidad_medida
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property OrdenesTrabajo $orden_trabajo
 * @property ProcesosOrden|null $proceso_orden
 * @property TiposProduccion $tipo_produccion
 *
 * @package App\Models\Base
 */
class Produccione extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const ORDEN_TRABAJO_ID = 'orden_trabajo_id';
	const PROCESO_ORDEN_ID = 'proceso_orden_id';
	const TIPO_PRODUCCION_ID = 'tipo_produccion_id';
	const FECHA = 'fecha';
	const DESCRIPCION = 'descripcion';
	const CANTIDAD = 'cantidad';
	const UNIDAD_MEDIDA = 'unidad_medida';
	const OBSERVACIONES = 'observaciones';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'producciones';

	protected $casts = [
		self::ID => 'int',
		self::ORDEN_TRABAJO_ID => 'int',
		self::PROCESO_ORDEN_ID => 'int',
		self::TIPO_PRODUCCION_ID => 'int',
		self::FECHA => 'datetime',
		self::CANTIDAD => 'float',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function orden_trabajo()
	{
		return $this->belongsTo(OrdenesTrabajo::class, \App\Models\Produccione::ORDEN_TRABAJO_ID);
	}

	public function proceso_orden()
	{
		return $this->belongsTo(ProcesosOrden::class, \App\Models\Produccione::PROCESO_ORDEN_ID);
	}

	public function tipo_produccion()
	{
		return $this->belongsTo(TiposProduccion::class, \App\Models\Produccione::TIPO_PRODUCCION_ID);
	}
}

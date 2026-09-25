<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\Moneda;
use App\Models\OrdenesTrabajo;
use App\Models\TiposParticipacion;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class AcuerdosOrden
 * 
 * @property int $id
 * @property int $orden_trabajo_id
 * @property int $tipo_participacion_id
 * @property float|null $porcentaje_cliente
 * @property float|null $porcentaje_empresa
 * @property float|null $tarifa_servicio
 * @property int $moneda_id
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property int $estado
 * 
 * @property Moneda $moneda
 * @property OrdenesTrabajo $orden_trabajo
 * @property TiposParticipacion $tipo_participacion
 *
 * @package App\Models\Base
 */
class AcuerdosOrden extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const ORDEN_TRABAJO_ID = 'orden_trabajo_id';
	const TIPO_PARTICIPACION_ID = 'tipo_participacion_id';
	const PORCENTAJE_CLIENTE = 'porcentaje_cliente';
	const PORCENTAJE_EMPRESA = 'porcentaje_empresa';
	const TARIFA_SERVICIO = 'tarifa_servicio';
	const MONEDA_ID = 'moneda_id';
	const OBSERVACIONES = 'observaciones';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	const ESTADO = 'estado';
	protected $table = 'acuerdos_orden';

	protected $casts = [
		self::ID => 'int',
		self::ORDEN_TRABAJO_ID => 'int',
		self::TIPO_PARTICIPACION_ID => 'int',
		self::PORCENTAJE_CLIENTE => 'float',
		self::PORCENTAJE_EMPRESA => 'float',
		self::TARIFA_SERVICIO => 'float',
		self::MONEDA_ID => 'int',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime',
		self::ESTADO => 'int'
	];

	public function moneda()
	{
		return $this->belongsTo(Moneda::class);
	}

	public function orden_trabajo()
	{
		return $this->belongsTo(OrdenesTrabajo::class, \App\Models\AcuerdosOrden::ORDEN_TRABAJO_ID);
	}

	public function tipo_participacion()
	{
		return $this->belongsTo(TiposParticipacion::class, \App\Models\AcuerdosOrden::TIPO_PARTICIPACION_ID);
	}
}

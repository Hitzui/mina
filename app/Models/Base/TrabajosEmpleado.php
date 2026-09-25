<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\Empleado;
use App\Models\Moneda;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use App\Models\TiposPagoEmpleado;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class TrabajosEmpleado
 * 
 * @property int $id
 * @property int $empleado_id
 * @property int|null $orden_trabajo_id
 * @property int|null $proceso_orden_id
 * @property int $tipo_pago_id
 * @property Carbon $fecha
 * @property Carbon|null $hora_inicio
 * @property Carbon|null $hora_fin
 * @property string|null $descripcion
 * @property float $cantidad
 * @property float $tarifa
 * @property float $total
 * @property float|null $tarifa_nio
 * @property float|null $total_nio
 * @property int $moneda_id
 * @property float|null $tipo_cambio
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property string $unidad
 * 
 * @property Empleado $empleado
 * @property Moneda $moneda
 * @property OrdenesTrabajo|null $orden_trabajo
 * @property ProcesosOrden|null $proceso_orden
 * @property TiposPagoEmpleado $tipo_pago
 *
 * @package App\Models\Base
 */
class TrabajosEmpleado extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const EMPLEADO_ID = 'empleado_id';
	const ORDEN_TRABAJO_ID = 'orden_trabajo_id';
	const PROCESO_ORDEN_ID = 'proceso_orden_id';
	const TIPO_PAGO_ID = 'tipo_pago_id';
	const FECHA = 'fecha';
	const HORA_INICIO = 'hora_inicio';
	const HORA_FIN = 'hora_fin';
	const DESCRIPCION = 'descripcion';
	const CANTIDAD = 'cantidad';
	const TARIFA = 'tarifa';
	const TOTAL = 'total';
	const TARIFA_NIO = 'tarifa_nio';
	const TOTAL_NIO = 'total_nio';
	const MONEDA_ID = 'moneda_id';
	const TIPO_CAMBIO = 'tipo_cambio';
	const OBSERVACIONES = 'observaciones';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	const UNIDAD = 'unidad';
	protected $table = 'trabajos_empleados';

	protected $casts = [
		self::ID => 'int',
		self::EMPLEADO_ID => 'int',
		self::ORDEN_TRABAJO_ID => 'int',
		self::PROCESO_ORDEN_ID => 'int',
		self::TIPO_PAGO_ID => 'int',
		self::FECHA => 'datetime',
		self::HORA_INICIO => 'datetime',
		self::HORA_FIN => 'datetime',
		self::CANTIDAD => 'float',
		self::TARIFA => 'float',
		self::TOTAL => 'float',
		self::TARIFA_NIO => 'float',
		self::TOTAL_NIO => 'float',
		self::MONEDA_ID => 'int',
		self::TIPO_CAMBIO => 'float',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function empleado()
	{
		return $this->belongsTo(Empleado::class);
	}

	public function moneda()
	{
		return $this->belongsTo(Moneda::class);
	}

	public function orden_trabajo()
	{
		return $this->belongsTo(OrdenesTrabajo::class, \App\Models\TrabajosEmpleado::ORDEN_TRABAJO_ID);
	}

	public function proceso_orden()
	{
		return $this->belongsTo(ProcesosOrden::class, \App\Models\TrabajosEmpleado::PROCESO_ORDEN_ID);
	}

	public function tipo_pago()
	{
		return $this->belongsTo(TiposPagoEmpleado::class, \App\Models\TrabajosEmpleado::TIPO_PAGO_ID);
	}
}

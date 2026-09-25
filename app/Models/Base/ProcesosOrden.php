<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\Etapa;
use App\Models\MovimientosCosto;
use App\Models\MovimientosInventario;
use App\Models\OrdenesTrabajo;
use App\Models\Produccione;
use App\Models\TrabajosEmpleado;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class ProcesosOrden
 *
 * @property int $id
 * @property int $orden_trabajo_id
 * @property int $etapa_id
 * @property Carbon|null $fecha_inicio
 * @property Carbon|null $fecha_fin
 * @property float|null $peso_entrada
 * @property float|null $peso_salida
 * @property int $estado
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $codigo
 * @property string|null $deleted_at
 *
 * @property Etapa $etapa
 * @property OrdenesTrabajo $orden_trabajo
 * @property Collection|MovimientosCosto[] $movimientos_costos_where_proceso_orden
 * @property Collection|MovimientosInventario[] $movimientos_inventarios_where_proceso_orden
 * @property Collection|Produccione[] $producciones_where_proceso_orden
 * @property Collection|TrabajosEmpleado[] $trabajos_empleados_where_proceso_orden
 *
 * @package App\Models\Base
 */
class ProcesosOrden extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const ORDEN_TRABAJO_ID = 'orden_trabajo_id';
	const ETAPA_ID = 'etapa_id';
	const FECHA_INICIO = 'fecha_inicio';
	const FECHA_FIN = 'fecha_fin';
	const PESO_ENTRADA = 'peso_entrada';
	const PESO_SALIDA = 'peso_salida';
	const ESTADO = 'estado';
	const OBSERVACIONES = 'observaciones';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const CODIGO = 'codigo';
	const DELETED_AT = 'deleted_at';
	protected $table = 'procesos_orden';

	protected $casts = [
		self::ID => 'int',
		self::ORDEN_TRABAJO_ID => 'int',
		self::ETAPA_ID => 'int',
		self::FECHA_INICIO => 'datetime',
		self::FECHA_FIN => 'datetime',
		self::PESO_ENTRADA => 'float',
		self::PESO_SALIDA => 'float',
		self::ESTADO => 'int',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function etapa()
	{
		return $this->belongsTo(Etapa::class);
	}

	public function orden_trabajo()
	{
		return $this->belongsTo(OrdenesTrabajo::class, ProcesosOrden::ORDEN_TRABAJO_ID);
	}

	public function movimientos_costos_where_proceso_orden()
	{
		return $this->hasMany(MovimientosCosto::class, MovimientosCosto::PROCESO_ORDEN_ID);
	}

	public function movimientos_inventarios_where_proceso_orden()
	{
		return $this->hasMany(MovimientosInventario::class, MovimientosInventario::PROCESO_ORDEN_ID);
	}

	public function producciones_where_proceso_orden()
	{
		return $this->hasMany(Produccione::class, Produccione::PROCESO_ORDEN_ID);
	}

	public function trabajos_empleados_where_proceso_orden()
	{
		return $this->hasMany(TrabajosEmpleado::class, TrabajosEmpleado::PROCESO_ORDEN_ID);
	}
}

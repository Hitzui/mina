<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\AcuerdosOrden;
use App\Models\CierresOrden;
use App\Models\Cliente;
use App\Models\Ingreso;
use App\Models\Liquidacione;
use App\Models\MovimientosCosto;
use App\Models\MovimientosInventario;
use App\Models\ProcesosOrden;
use App\Models\Produccione;
use App\Models\Recuperacione;
use App\Models\TrabajosEmpleado;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class OrdenesTrabajo
 *
 * @property int $id
 * @property string $codigo
 * @property int $cliente_id
 * @property Carbon $fecha
 * @property string|null $descripcion
 * @property float $peso_mineral
 * @property string $unidad_peso
 * @property int $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 *
 * @property Cliente $cliente
 * @property Collection|AcuerdosOrden[] $acuerdos_ordens_where_orden_trabajo
 * @property CierresOrden|null $cierres_orden
 * @property Collection|Ingreso[] $ingresos_where_orden_trabajo
 * @property Collection|Liquidacione[] $liquidaciones_where_orden_trabajo
 * @property Collection|MovimientosCosto[] $movimientos_costos_where_orden_trabajo
 * @property Collection|MovimientosInventario[] $movimientos_inventarios_where_orden_trabajo
 * @property Collection|ProcesosOrden[] $procesos_ordens_where_orden_trabajo
 * @property Collection|Produccione[] $producciones_where_orden_trabajo
 * @property Collection|Recuperacione[] $recuperaciones_where_orden_trabajo
 * @property Collection|TrabajosEmpleado[] $trabajos_empleados_where_orden_trabajo
 *
 * @package App\Models\Base
 */
class OrdenesTrabajo extends Model
{
	use SoftDeletes;
	const string ID = 'id';
	const string CODIGO = 'codigo';
	const string CLIENTE_ID = 'cliente_id';
	const string FECHA = 'fecha';
	const string DESCRIPCION = 'descripcion';
	const string PESO_MINERAL = 'peso_mineral';
    const string UNIDAD_PESO = 'unidad_peso';
    const string ESTADO = 'estado';
    const string CREATED_AT = 'created_at';
    const string UPDATED_AT = 'updated_at';
    const string DELETED_AT = 'deleted_at';
	protected $table = 'ordenes_trabajo';

	protected $casts = [
		self::ID => 'int',
		self::CLIENTE_ID => 'int',
		self::FECHA => 'datetime',
		self::PESO_MINERAL => 'float',
		self::ESTADO => 'int',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function cliente()
	{
		return $this->belongsTo(Cliente::class);
	}

	public function acuerdos_ordens_where_orden_trabajo()
	{
		return $this->hasMany(AcuerdosOrden::class, AcuerdosOrden::ORDEN_TRABAJO_ID);
	}

	public function cierres_orden()
	{
		return $this->hasOne(CierresOrden::class, CierresOrden::ORDEN_TRABAJO_ID);
	}

	public function ingresos_where_orden_trabajo()
	{
		return $this->hasMany(Ingreso::class, Ingreso::ORDEN_TRABAJO_ID);
	}

	public function liquidaciones_where_orden_trabajo()
	{
		return $this->hasMany(Liquidacione::class, Liquidacione::ORDEN_TRABAJO_ID);
	}

	public function movimientos_costos_where_orden_trabajo()
	{
		return $this->hasMany(MovimientosCosto::class, MovimientosCosto::ORDEN_TRABAJO_ID);
	}

	public function movimientos_inventarios_where_orden_trabajo()
	{
		return $this->hasMany(MovimientosInventario::class, MovimientosInventario::ORDEN_TRABAJO_ID);
	}

	public function procesos_ordens_where_orden_trabajo()
	{
		return $this->hasMany(ProcesosOrden::class, ProcesosOrden::ORDEN_TRABAJO_ID);
	}

	public function producciones_where_orden_trabajo()
	{
		return $this->hasMany(Produccione::class, Produccione::ORDEN_TRABAJO_ID);
	}

	public function recuperaciones_where_orden_trabajo()
	{
		return $this->hasMany(Recuperacione::class, Recuperacione::ORDEN_TRABAJO_ID);
	}

	/*
	 * Los trabajos de los empleados ya no tienen orden_trabajo_id: cuelgan
	 * del proceso, y la orden se alcanza a traves de el.
	 *
	 * La relacion se declara en App\Models\OrdenesTrabajo usando
	 * hasManyThrough, para que consultar los trabajos de una orden siga
	 * funcionando. Esta de aqui queda solo si alguien la usa por su nombre
	 * antiguo, y devuelve lo mismo.
	 */
	public function trabajos_empleados_where_orden_trabajo()
	{
		return $this->hasManyThrough(
			TrabajosEmpleado::class,
			ProcesosOrden::class,
			'orden_trabajo_id',
			'proceso_orden_id',
			'id',
			'id'
		);
	}
}

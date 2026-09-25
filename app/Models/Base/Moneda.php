<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\AcuerdosOrden;
use App\Models\Cobro;
use App\Models\Compra;
use App\Models\EmpleadosPago;
use App\Models\Ingreso;
use App\Models\MovimientosCosto;
use App\Models\MovimientosInventario;
use App\Models\PreciosOro;
use App\Models\TiposCambio;
use App\Models\TrabajosEmpleado;
use App\Models\ValoracionesOro;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Moneda
 * 
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $simbolo
 * @property bool $es_moneda_base
 * @property bool $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Collection|AcuerdosOrden[] $acuerdos_ordens
 * @property Collection|Cobro[] $cobros
 * @property Collection|Compra[] $compras
 * @property Collection|EmpleadosPago[] $empleados_pagos
 * @property Collection|Ingreso[] $ingresos
 * @property Collection|MovimientosCosto[] $movimientos_costos
 * @property Collection|MovimientosInventario[] $movimientos_inventarios
 * @property Collection|PreciosOro[] $precios_oros
 * @property Collection|TiposCambio[] $tipos_cambios
 * @property Collection|TrabajosEmpleado[] $trabajos_empleados
 * @property Collection|ValoracionesOro[] $valoraciones_oros
 *
 * @package App\Models\Base
 */
class Moneda extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const CODIGO = 'codigo';
	const NOMBRE = 'nombre';
	const SIMBOLO = 'simbolo';
	const ES_MONEDA_BASE = 'es_moneda_base';
	const ESTADO = 'estado';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'monedas';

	protected $casts = [
		self::ID => 'int',
		self::ES_MONEDA_BASE => 'bool',
		self::ESTADO => 'bool',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function acuerdos_ordens()
	{
		return $this->hasMany(AcuerdosOrden::class);
	}

	public function cobros()
	{
		return $this->hasMany(Cobro::class);
	}

	public function compras()
	{
		return $this->hasMany(Compra::class);
	}

	public function empleados_pagos()
	{
		return $this->hasMany(EmpleadosPago::class);
	}

	public function ingresos()
	{
		return $this->hasMany(Ingreso::class);
	}

	public function movimientos_costos()
	{
		return $this->hasMany(MovimientosCosto::class);
	}

	public function movimientos_inventarios()
	{
		return $this->hasMany(MovimientosInventario::class);
	}

	public function precios_oros()
	{
		return $this->hasMany(PreciosOro::class);
	}

	public function tipos_cambios()
	{
		return $this->hasMany(TiposCambio::class);
	}

	public function trabajos_empleados()
	{
		return $this->hasMany(TrabajosEmpleado::class);
	}

	public function valoraciones_oros()
	{
		return $this->hasMany(ValoracionesOro::class);
	}
}

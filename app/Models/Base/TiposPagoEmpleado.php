<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\EmpleadosPago;
use App\Models\TrabajosEmpleado;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class TiposPagoEmpleado
 * 
 * @property int $id
 * @property string $nombre
 * @property string|null $codigo
 * @property string $metodo_calculo
 * @property string|null $descripcion
 * @property bool $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Collection|EmpleadosPago[] $empleados_pagos_where_tipo_pago
 * @property Collection|TrabajosEmpleado[] $trabajos_empleados_where_tipo_pago
 *
 * @package App\Models\Base
 */
class TiposPagoEmpleado extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const NOMBRE = 'nombre';
	const CODIGO = 'codigo';
	const METODO_CALCULO = 'metodo_calculo';
	const DESCRIPCION = 'descripcion';
	const ESTADO = 'estado';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'tipos_pago_empleado';

	protected $casts = [
		self::ID => 'int',
		self::ESTADO => 'bool',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function empleados_pagos_where_tipo_pago()
	{
		return $this->hasMany(EmpleadosPago::class, EmpleadosPago::TIPO_PAGO_ID);
	}

	public function trabajos_empleados_where_tipo_pago()
	{
		return $this->hasMany(TrabajosEmpleado::class, TrabajosEmpleado::TIPO_PAGO_ID);
	}
}

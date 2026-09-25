<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\EmpleadosPago;
use App\Models\TiposEmpleado;
use App\Models\TrabajosEmpleado;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Empleado
 * 
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $telefono
 * @property int $tipo_empleado_id
 * @property Carbon|null $fecha_ingreso
 * @property bool $estado
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property TiposEmpleado $tipo_empleado
 * @property Collection|EmpleadosPago[] $empleados_pagos
 * @property Collection|TrabajosEmpleado[] $trabajos_empleados
 *
 * @package App\Models\Base
 */
class Empleado extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const CODIGO = 'codigo';
	const NOMBRE = 'nombre';
	const TELEFONO = 'telefono';
	const TIPO_EMPLEADO_ID = 'tipo_empleado_id';
	const FECHA_INGRESO = 'fecha_ingreso';
	const ESTADO = 'estado';
	const OBSERVACIONES = 'observaciones';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'empleados';

	protected $casts = [
		self::ID => 'int',
		self::TIPO_EMPLEADO_ID => 'int',
		self::FECHA_INGRESO => 'datetime',
		self::ESTADO => 'bool',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function tipo_empleado()
	{
		return $this->belongsTo(TiposEmpleado::class, \App\Models\Empleado::TIPO_EMPLEADO_ID);
	}

	public function empleados_pagos()
	{
		return $this->hasMany(EmpleadosPago::class);
	}

	public function trabajos_empleados()
	{
		return $this->hasMany(TrabajosEmpleado::class);
	}
}

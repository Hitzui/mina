<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\Empleado;
use App\Models\Moneda;
use App\Models\TiposPagoEmpleado;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class EmpleadosPago
 * 
 * @property int $id
 * @property int $empleado_id
 * @property int $tipo_pago_id
 * @property float $tarifa
 * @property int $moneda_id
 * @property Carbon $fecha_inicio
 * @property Carbon|null $fecha_fin
 * @property bool $estado
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Empleado $empleado
 * @property Moneda $moneda
 * @property TiposPagoEmpleado $tipo_pago
 *
 * @package App\Models\Base
 */
class EmpleadosPago extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const EMPLEADO_ID = 'empleado_id';
	const TIPO_PAGO_ID = 'tipo_pago_id';
	const TARIFA = 'tarifa';
	const MONEDA_ID = 'moneda_id';
	const FECHA_INICIO = 'fecha_inicio';
	const FECHA_FIN = 'fecha_fin';
	const ESTADO = 'estado';
	const OBSERVACIONES = 'observaciones';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'empleados_pagos';

	protected $casts = [
		self::ID => 'int',
		self::EMPLEADO_ID => 'int',
		self::TIPO_PAGO_ID => 'int',
		self::TARIFA => 'float',
		self::MONEDA_ID => 'int',
		self::FECHA_INICIO => 'datetime',
		self::FECHA_FIN => 'datetime',
		self::ESTADO => 'bool',
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

	public function tipo_pago()
	{
		return $this->belongsTo(TiposPagoEmpleado::class, \App\Models\EmpleadosPago::TIPO_PAGO_ID);
	}
}

<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\DetalleCobro;
use App\Models\MetodosPago;
use App\Models\Moneda;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Cobro
 * 
 * @property int $id
 * @property int $cliente_id
 * @property int $caja_id
 * @property int $metodo_pago_id
 * @property Carbon $fecha
 * @property float $monto
 * @property int $moneda_id
 * @property string|null $referencia
 * @property int $estado
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property string $codigo
 * 
 * @property Caja $caja
 * @property Cliente $cliente
 * @property MetodosPago $metodo_pago
 * @property Moneda $moneda
 * @property Collection|DetalleCobro[] $detalle_cobros
 *
 * @package App\Models\Base
 */
class Cobro extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const CLIENTE_ID = 'cliente_id';
	const CAJA_ID = 'caja_id';
	const METODO_PAGO_ID = 'metodo_pago_id';
	const FECHA = 'fecha';
	const MONTO = 'monto';
	const MONEDA_ID = 'moneda_id';
	const REFERENCIA = 'referencia';
	const ESTADO = 'estado';
	const OBSERVACIONES = 'observaciones';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	const CODIGO = 'codigo';
	protected $table = 'cobros';

	protected $casts = [
		self::ID => 'int',
		self::CLIENTE_ID => 'int',
		self::CAJA_ID => 'int',
		self::METODO_PAGO_ID => 'int',
		self::FECHA => 'datetime',
		self::MONTO => 'float',
		self::MONEDA_ID => 'int',
		self::ESTADO => 'int',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function caja()
	{
		return $this->belongsTo(Caja::class);
	}

	public function cliente()
	{
		return $this->belongsTo(Cliente::class);
	}

	public function metodo_pago()
	{
		return $this->belongsTo(MetodosPago::class, \App\Models\Cobro::METODO_PAGO_ID);
	}

	public function moneda()
	{
		return $this->belongsTo(Moneda::class);
	}

	public function detalle_cobros()
	{
		return $this->hasMany(DetalleCobro::class);
	}
}

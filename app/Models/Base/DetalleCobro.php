<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\Cobro;
use App\Models\Ingreso;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class DetalleCobro
 * 
 * @property int $id
 * @property int $cobro_id
 * @property int $ingreso_id
 * @property float $monto
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Cobro $cobro
 * @property Ingreso $ingreso
 *
 * @package App\Models\Base
 */
class DetalleCobro extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const COBRO_ID = 'cobro_id';
	const INGRESO_ID = 'ingreso_id';
	const MONTO = 'monto';
	const OBSERVACIONES = 'observaciones';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'detalle_cobros';

	protected $casts = [
		self::ID => 'int',
		self::COBRO_ID => 'int',
		self::INGRESO_ID => 'int',
		self::MONTO => 'float',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function cobro()
	{
		return $this->belongsTo(Cobro::class);
	}

	public function ingreso()
	{
		return $this->belongsTo(Ingreso::class);
	}
}

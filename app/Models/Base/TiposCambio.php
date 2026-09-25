<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\Moneda;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class TiposCambio
 * 
 * @property int $id
 * @property Carbon $fecha
 * @property int $moneda_id
 * @property float $valor
 * @property string|null $fuente
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Moneda $moneda
 *
 * @package App\Models\Base
 */
class TiposCambio extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const FECHA = 'fecha';
	const MONEDA_ID = 'moneda_id';
	const VALOR = 'valor';
	const FUENTE = 'fuente';
	const OBSERVACIONES = 'observaciones';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'tipos_cambio';

	protected $casts = [
		self::ID => 'int',
		self::FECHA => 'datetime',
		self::MONEDA_ID => 'int',
		self::VALOR => 'float',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function moneda()
	{
		return $this->belongsTo(Moneda::class);
	}
}

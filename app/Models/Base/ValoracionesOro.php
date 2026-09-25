<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\Moneda;
use App\Models\PreciosOro;
use App\Models\Recuperacione;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class ValoracionesOro
 * 
 * @property int $id
 * @property int $recuperacion_id
 * @property int|null $precio_oro_id
 * @property float $valor
 * @property int $moneda_id
 * @property Carbon $fecha
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Moneda $moneda
 * @property PreciosOro|null $precio_oro
 * @property Recuperacione $recuperacion
 *
 * @package App\Models\Base
 */
class ValoracionesOro extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const RECUPERACION_ID = 'recuperacion_id';
	const PRECIO_ORO_ID = 'precio_oro_id';
	const VALOR = 'valor';
	const MONEDA_ID = 'moneda_id';
	const FECHA = 'fecha';
	const OBSERVACIONES = 'observaciones';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'valoraciones_oro';

	protected $casts = [
		self::ID => 'int',
		self::RECUPERACION_ID => 'int',
		self::PRECIO_ORO_ID => 'int',
		self::VALOR => 'float',
		self::MONEDA_ID => 'int',
		self::FECHA => 'datetime',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function moneda()
	{
		return $this->belongsTo(Moneda::class);
	}

	public function precio_oro()
	{
		return $this->belongsTo(PreciosOro::class, \App\Models\ValoracionesOro::PRECIO_ORO_ID);
	}

	public function recuperacion()
	{
		return $this->belongsTo(Recuperacione::class, \App\Models\ValoracionesOro::RECUPERACION_ID);
	}
}

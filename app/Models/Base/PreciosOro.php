<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\Moneda;
use App\Models\ValoracionesOro;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class PreciosOro
 * 
 * @property int $id
 * @property Carbon $fecha
 * @property float $precio
 * @property string $unidad
 * @property int $moneda_id
 * @property string|null $fuente
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Moneda $moneda
 * @property Collection|ValoracionesOro[] $valoraciones_oros_where_precio_oro
 *
 * @package App\Models\Base
 */
class PreciosOro extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const FECHA = 'fecha';
	const PRECIO = 'precio';
	const UNIDAD = 'unidad';
	const MONEDA_ID = 'moneda_id';
	const FUENTE = 'fuente';
	const OBSERVACIONES = 'observaciones';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'precios_oro';

	protected $casts = [
		self::ID => 'int',
		self::FECHA => 'datetime',
		self::PRECIO => 'float',
		self::MONEDA_ID => 'int',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function moneda()
	{
		return $this->belongsTo(Moneda::class);
	}

	public function valoraciones_oros_where_precio_oro()
	{
		return $this->hasMany(ValoracionesOro::class, ValoracionesOro::PRECIO_ORO_ID);
	}
}

<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\OrdenesTrabajo;
use App\Models\ValoracionesOro;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Recuperacione
 * 
 * @property int $id
 * @property int $orden_trabajo_id
 * @property Carbon $fecha
 * @property float $gramos
 * @property float|null $pureza
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property OrdenesTrabajo $orden_trabajo
 * @property Collection|ValoracionesOro[] $valoraciones_oros_where_recuperacion
 *
 * @package App\Models\Base
 */
class Recuperacione extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const ORDEN_TRABAJO_ID = 'orden_trabajo_id';
	const FECHA = 'fecha';
	const GRAMOS = 'gramos';
	const PUREZA = 'pureza';
	const OBSERVACIONES = 'observaciones';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'recuperaciones';

	protected $casts = [
		self::ID => 'int',
		self::ORDEN_TRABAJO_ID => 'int',
		self::FECHA => 'datetime',
		self::GRAMOS => 'float',
		self::PUREZA => 'float',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function orden_trabajo()
	{
		return $this->belongsTo(OrdenesTrabajo::class, \App\Models\Recuperacione::ORDEN_TRABAJO_ID);
	}

	public function valoraciones_oros_where_recuperacion()
	{
		return $this->hasMany(ValoracionesOro::class, ValoracionesOro::RECUPERACION_ID);
	}
}

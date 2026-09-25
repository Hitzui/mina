<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\MovimientosCosto;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class CategoriasCosto
 * 
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property bool $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Collection|MovimientosCosto[] $movimientos_costos_where_categoria_costo
 *
 * @package App\Models\Base
 */
class CategoriasCosto extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const NOMBRE = 'nombre';
	const DESCRIPCION = 'descripcion';
	const ESTADO = 'estado';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'categorias_costos';

	protected $casts = [
		self::ID => 'int',
		self::ESTADO => 'bool',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function movimientos_costos_where_categoria_costo()
	{
		return $this->hasMany(MovimientosCosto::class, MovimientosCosto::CATEGORIA_COSTO_ID);
	}
}

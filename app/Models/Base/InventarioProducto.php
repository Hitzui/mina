<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\Producto;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class InventarioProducto
 * 
 * @property int $id
 * @property int $producto_id
 * @property float $cantidad_actual
 * @property float $valor_actual
 * @property float $cpp_actual
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Producto $producto
 *
 * @package App\Models\Base
 */
class InventarioProducto extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const PRODUCTO_ID = 'producto_id';
	const CANTIDAD_ACTUAL = 'cantidad_actual';
	const VALOR_ACTUAL = 'valor_actual';
	const CPP_ACTUAL = 'cpp_actual';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'inventario_productos';

	protected $casts = [
		self::ID => 'int',
		self::PRODUCTO_ID => 'int',
		self::CANTIDAD_ACTUAL => 'float',
		self::VALOR_ACTUAL => 'float',
		self::CPP_ACTUAL => 'float',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function producto()
	{
		return $this->belongsTo(Producto::class);
	}
}

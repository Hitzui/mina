<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\Compra;
use App\Models\Producto;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class DetalleCompra
 * 
 * @property int $id
 * @property int $compra_id
 * @property int $producto_id
 * @property float $cantidad
 * @property float $costo_unitario
 * @property float $subtotal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Compra $compra
 * @property Producto $producto
 *
 * @package App\Models\Base
 */
class DetalleCompra extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const COMPRA_ID = 'compra_id';
	const PRODUCTO_ID = 'producto_id';
	const CANTIDAD = 'cantidad';
	const COSTO_UNITARIO = 'costo_unitario';
	const SUBTOTAL = 'subtotal';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'detalle_compras';

	protected $casts = [
		self::ID => 'int',
		self::COMPRA_ID => 'int',
		self::PRODUCTO_ID => 'int',
		self::CANTIDAD => 'float',
		self::COSTO_UNITARIO => 'float',
		self::SUBTOTAL => 'float',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function compra()
	{
		return $this->belongsTo(Compra::class);
	}

	public function producto()
	{
		return $this->belongsTo(Producto::class);
	}
}

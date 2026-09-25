<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\DetalleCompra;
use App\Models\InventarioProducto;
use App\Models\MovimientosInventario;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Producto
 * 
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $descripcion
 * @property string $unidad_medida
 * @property string|null $categoria
 * @property float $stock_minimo
 * @property bool $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Collection|DetalleCompra[] $detalle_compras
 * @property InventarioProducto|null $inventario_producto
 * @property Collection|MovimientosInventario[] $movimientos_inventarios
 *
 * @package App\Models\Base
 */
class Producto extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const CODIGO = 'codigo';
	const NOMBRE = 'nombre';
	const DESCRIPCION = 'descripcion';
	const UNIDAD_MEDIDA = 'unidad_medida';
	const CATEGORIA = 'categoria';
	const STOCK_MINIMO = 'stock_minimo';
	const ESTADO = 'estado';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'productos';

	protected $casts = [
		self::ID => 'int',
		self::STOCK_MINIMO => 'float',
		self::ESTADO => 'bool',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function detalle_compras()
	{
		return $this->hasMany(DetalleCompra::class);
	}

	public function inventario_producto()
	{
		return $this->hasOne(InventarioProducto::class);
	}

	public function movimientos_inventarios()
	{
		return $this->hasMany(MovimientosInventario::class);
	}
}

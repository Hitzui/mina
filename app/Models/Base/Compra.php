<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\DetalleCompra;
use App\Models\Moneda;
use App\Models\Proveedore;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Compra
 * 
 * @property int $id
 * @property string $codigo
 * @property int $proveedor_id
 * @property Carbon $fecha
 * @property string|null $numero_documento
 * @property float $subtotal
 * @property float $impuesto
 * @property float $total
 * @property int $moneda_id
 * @property int $estado
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Moneda $moneda
 * @property Proveedore $proveedor
 * @property Collection|DetalleCompra[] $detalle_compras
 *
 * @package App\Models\Base
 */
class Compra extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const CODIGO = 'codigo';
	const PROVEEDOR_ID = 'proveedor_id';
	const FECHA = 'fecha';
	const NUMERO_DOCUMENTO = 'numero_documento';
	const SUBTOTAL = 'subtotal';
	const IMPUESTO = 'impuesto';
	const TOTAL = 'total';
	const MONEDA_ID = 'moneda_id';
	const ESTADO = 'estado';
	const OBSERVACIONES = 'observaciones';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'compras';

	protected $casts = [
		self::ID => 'int',
		self::PROVEEDOR_ID => 'int',
		self::FECHA => 'datetime',
		self::SUBTOTAL => 'float',
		self::IMPUESTO => 'float',
		self::TOTAL => 'float',
		self::MONEDA_ID => 'int',
		self::ESTADO => 'int',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function moneda()
	{
		return $this->belongsTo(Moneda::class);
	}

	public function proveedor()
	{
		return $this->belongsTo(Proveedore::class, \App\Models\Compra::PROVEEDOR_ID);
	}

	public function detalle_compras()
	{
		return $this->hasMany(DetalleCompra::class);
	}
}

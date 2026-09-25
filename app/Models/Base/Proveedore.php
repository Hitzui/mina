<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\Compra;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Proveedore
 * 
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $direccion
 * @property string|null $contacto
 * @property bool $estado
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Collection|Compra[] $compras_where_proveedor
 *
 * @package App\Models\Base
 */
class Proveedore extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const CODIGO = 'codigo';
	const NOMBRE = 'nombre';
	const TELEFONO = 'telefono';
	const EMAIL = 'email';
	const DIRECCION = 'direccion';
	const CONTACTO = 'contacto';
	const ESTADO = 'estado';
	const OBSERVACIONES = 'observaciones';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'proveedores';

	protected $casts = [
		self::ID => 'int',
		self::ESTADO => 'bool',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function compras_where_proveedor()
	{
		return $this->hasMany(Compra::class, Compra::PROVEEDOR_ID);
	}
}

<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\ProcesosOrden;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Etapa
 * 
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property int $orden
 * @property bool $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Collection|ProcesosOrden[] $procesos_ordens
 *
 * @package App\Models\Base
 */
class Etapa extends Model
{
	use SoftDeletes;
	const ID = 'id';
	const NOMBRE = 'nombre';
	const DESCRIPCION = 'descripcion';
	const ORDEN = 'orden';
	const ESTADO = 'estado';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const DELETED_AT = 'deleted_at';
	protected $table = 'etapas';

	protected $casts = [
		self::ID => 'int',
		self::ORDEN => 'int',
		self::ESTADO => 'bool',
		self::CREATED_AT => 'datetime',
		self::UPDATED_AT => 'datetime'
	];

	public function procesos_ordens()
	{
		return $this->hasMany(ProcesosOrden::class);
	}
}

<?php

namespace App\Models;

use App\Models\Base\Producto as BaseProducto;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Producto extends BaseProducto
{
	/*
	 * El codigo lo pone el sistema, no la persona que da de alta el
	 * material.
	 *
	 * El formato sigue el que ya usan los empleados (EMP-000001): unas
	 * letras y un numero de seis digitos con ceros delante. Los ceros no son
	 * adorno: hacen que el codigo se ordene igual que el numero al mirar la
	 * columna, y sin ellos el 10 se colaria antes que el 9.
	 */
	public const PREFIJO_CODIGO = 'MAT-';
	public const LARGO_NUMERO_CODIGO = 6;

	protected $fillable = [
		self::CODIGO,
		self::NOMBRE,
		self::DESCRIPCION,
		self::UNIDAD_MEDIDA,
		self::CATEGORIA,
		self::STOCK_MINIMO,
		self::ESTADO
	];

	/**
	 * El siguiente codigo libre de material.
	 *
	 * Se mira tambien entre los borrados logicamente, y a proposito. Si se
	 * mirara solo entre los vivos, al reutilizar el codigo de uno borrado
	 * habria dos filas con el mismo codigo en el historial del kardex, y el
	 * indice unico de la base lo rechazaria. Ademas un material borrado
	 * sigue citandose en los movimientos de los procesos donde se consumio,
	 * y un codigo que vuelve a salir hace imposible saber de que material
	 * se hablo.
	 *
	 * Un codigo con otra forma, puesto a mano antes de que esto existiera, no
	 * participa en la serie: se cuentan solo los que siguen el patron, para
	 * que un nombre raro no tumbe la cuenta.
	 */
	public static function generarCodigo(): string
	{
		$maximo = 0;

		$codigos = static::withTrashed()
			->where(self::CODIGO, 'like', self::PREFIJO_CODIGO . '%')
			->pluck(self::CODIGO);

		foreach ($codigos as $codigo) {
			$numero = substr((string) $codigo, strlen(self::PREFIJO_CODIGO));

			/*
			 * Solo cuentan los que son numeros del todo. Con un (int) a
			 * secas, "MAT-1a2b3c" contaria como 1 y "MAT-12abc" como 12: un
			 * codigo raro, de los que se pueden haber puesto a mano, moveria
			 * la cuenta y dejaria huecos sin explicacion.
			 */
			if ($numero !== '' && ctype_digit($numero)) {
				$valor = (int) $numero;

				if ($valor > $maximo) {
					$maximo = $valor;
				}
			}
		}

		return self::PREFIJO_CODIGO . str_pad(
			(string) ($maximo + 1),
			self::LARGO_NUMERO_CODIGO,
			'0',
			STR_PAD_LEFT
		);
	}

	/**
	 * Da de alta un material con el codigo ya puesto.
	 *
	 * La cuenta y el alta van en la misma transaccion y se reintentan si
	 * chocan con el indice unico. Sin el reintento, dos materiales dados de
	 * alta a la vez (dos personas, o la misma en dos pestanas) podrian sacar
	 * el mismo codigo: las dos leerian el mismo maximo y la segunda fallaria
	 * con un error de la base que no le dice a nadie de que se trata. Con el
	 * reintento, a una solo le basta con intentarlo otra vez y la otra se
	 * guarda sin que se note.
	 */
	public static function crearConCodigo(array $atributos, int $intentos = 3): self
	{
		// Lo que venga en codigo se tira: lo pone generarCodigo
		unset($atributos[self::CODIGO]);

		$ultimoError = null;

		for ($intento = 1; $intento <= $intentos; $intento++) {
			try {
				return DB::transaction(function () use ($atributos) {
					$producto = new self($atributos);
					$producto->codigo = self::generarCodigo();
					$producto->save();

					return $producto;
				});
			} catch (UniqueConstraintViolationException $e) {
				$ultimoError = $e;
			}
		}

		/*
		 * Tres veces seguidas sin poder colocar un codigo libre. Con el
		 * indice unico puesto, eso ya no es una carrera: hay algo mas
		 * atascando la tabla, y mas vale que se entere que darle un codigo
		 * repetido sin avisar.
		 */
		throw new RuntimeException(
			'No se pudo asignar un código libre al material después de '
			. $intentos . ' intentos. Revise la tabla productos.',
			0,
			$ultimoError
		);
	}

	/*
	 * Como se muestra un producto en los combos y en las pantallas.
	 *
	 * El producto no tiene columna "nombre corto": se describe con el
	 * codigo y el nombre, y la unidad detras, porque en un consumo de
	 * 200 lo que importa es saber si son kilos o toneladas.
	 */
	public function getNombreCompletoAttribute(): string
	{
		return $this->nombre . ' (' . $this->unidad_medida . ')';
	}

	/**
	 * El saldo de este producto en el almacen.
	 */
	public function inventario()
	{
		return $this->hasOne(InventarioProducto::class);
	}

	/**
	 * Todos los movimientos de este producto, entradas y salidas.
	 */
	public function movimientos()
	{
		return $this->hasMany(MovimientosInventario::class);
	}

	/**
	 * Cuanto hay en el almacen ahora mismo.
	 *
	 * Sale del saldo resumido y no de sumar el kardex: el kardex es la
	 * fuente historica, pero recorrerlo entero para cada fila de un
	 * listado seria lento, y el saldo se mantiene con el mismo criterio
	 * en cada movimiento.
	 */
	public function getExistenciaAttribute(): float
	{
		if ($this->relationLoaded('inventario')) {
			return (float) ($this->inventario?->cantidad_actual ?? 0);
		}

		return (float) ($this->inventario()->value('cantidad_actual') ?? 0);
	}

	/**
	 * A cuanto costo promedio sale de aqui un kilo, un litro o lo que sea.
	 */
	public function getCostoPromedioAttribute(): float
	{
		if ($this->relationLoaded('inventario')) {
			return (float) ($this->inventario?->cpp_actual ?? 0);
		}

		return (float) ($this->inventario()->value('cpp_actual') ?? 0);
	}

	/**
	 * Cuanto vale en NIO todo lo que hay de este producto.
	 */
	public function getValorInventarioAttribute(): float
	{
		if ($this->relationLoaded('inventario')) {
			return (float) ($this->inventario?->valor_actual ?? 0);
		}

		return (float) ($this->inventario()->value('valor_actual') ?? 0);
	}

	/**
	 * Si el material esta por debajo del minimo con el que se quiere
	 * tener en el almacen.
	 *
	 * Es un aviso de reponer, no un estado: no impide consumir ni
	 * comprar. Solo dice que conviene mirar.
	 */
	public function estaPorDebajoDelMinimo(): bool
	{
		$minimo = (float) $this->stock_minimo;

		return $minimo > 0 && $this->existencia < $minimo;
	}
}

<?php

namespace App\Models;

use App\Models\Base\TiposIngreso as BaseTiposIngreso;
use Illuminate\Support\Facades\DB;

/**
 * Los tipos de ingreso, que es el catalogo de por que entro dinero en una orden.
 *
 * Son cuatro y estan puestos a mano: servicio de procesamiento, participacion
 * en oro, venta de oro y otro. Esta pantalla no decide cuales son —eso es del
 * taller—: lo que hace es que se puedan corregir, desactivar y dar de baja sin
 * tener que entrar en la base de datos a mano, que es como se hizo estos.
 */
class TiposIngreso extends BaseTiposIngreso
{
	protected $fillable = [
		self::NOMBRE,
		self::DESCRIPCION,
		self::ESTADO
	];

	/**
	 * Los tipos que se ofrecen al registrar un ingreso.
	 *
	 * Solo los activos, y por nombre. El filtro vive aqui y no en el desplegable
	 * de la pantalla, para que ningun camino lo esquive: un tipo desactivado
	 * tiene que salir de todas partes a la vez, no de unas y no de otras.
	 */
	public function scopeParaRegistrar($query)
	{
		return $query->where(self::ESTADO, true)
			->orderBy(self::NOMBRE);
	}

	/**
	 * Donde se usa este tipo, y cuantas veces.
	 *
	 * Se cuentan TODAS las filas de ingresos, incluidas las dadas de baja, y no
	 * solo las vivas, porque la clave foranea sigue escribiendo el numero en las
	 * borradas. La FK es restrict —no lleva ON DELETE, y el valor por defecto de
	 * MySQL es restringir—, de modo que borrar el tipo con una fila dada de baja
	 * tambien lo impide. Si aqui se contaran solo las vivas, el boton dejaria
	 * pulsar el borrar y reventaria con un error de MySQL en vez de con un aviso
	 * que dice lo que hay que hacer.
	 *
	 * @return array<int, array{tabla: string, donde: string, cuantas: int, dadasDeBaja: int}>
	 */
	public function usos(): array
	{
		try {
			$filas = DB::table('ingresos')
				->where('tipo_ingreso_id', $this->id)
				->selectRaw('COUNT(*) as cuantas, SUM(deleted_at IS NOT NULL) as dadas')
				->first();
		} catch (\Throwable $e) {
			return [];
		}

		if (! $filas || (int) $filas->cuantas === 0) {
			return [];
		}

		return [
			[
				'tabla' => 'ingresos',
				'donde' => 'ingresos',
				'cuantas' => (int) $filas->cuantas,
				'dadasDeBaja' => (int) $filas->dadas,
			],
		];
	}

	/**
	 * Si este tipo se puede borrar.
	 *
	 * La respuesta no es "si no esta en uso" a secas: es que se pueda. Lo de
	 * que este en uso y lo de que no se pueda se separan porque hay un caso en
	 * que las dos cosas se pueden decir a la vez y aqui no: los tipos de ingreso
	 * no tienen ninguna condicion que los vuelva intocables, a diferencia de la
	 * moneda base del taller. Lo unico que protege un tipo de ingreso es que
	 * haya ingresos con el.
	 *
	 * Se deja el metodo aunque hoy solo lo pregunte el boton de borrar, porque
	 * el mismo-trait lo necesitaran las demas pantallas que manejen un catalogo,
	 * y porque la pregunta "se puede borrar" es del modelo y no de la pantalla.
	 */
	public function sePuedeBorrar(): bool
	{
		return $this->usos() === [];
	}

	/**
	 * "en 3 ingresos", y si alguno esta dado de baja, se dice.
	 *
	 * Va aqui y no en la vista porque la frase la tienen que entender el aviso
	 * del controlador y el de la ficha, y dos copias de la misma frase se separan
	 * en cuanto una cambia. Y el matiz de las dadas de baja se dice por el mismo
	 * motivo: si el usuario ve "en 3 ingresos" y los tres los borro, no va a
	 * entender por que no le deja dar de baja uno.
	 */
	public function fraseDeUsos(): string
	{
		$usos = $this->usos();

		if ($usos === []) {
			return '';
		}

		$partes = [];

		foreach ($usos as $uso) {
			$cuantas = $uso['cuantas'];

			/*
			 * El sitio va en singular y el plural se pone aqui.
			 *
			 * El sitio es "ingresos" a proposito: es el nombre de la tabla, que
			 * es como lo nombra la documentacion. Por eso antes salia "en 2
			 * ingresoss" al aniadirle una s encima, y "en 1 ingresos" al no
			 * aniadirla. Ninguna de las dos formas esta bien, y las dos se
			 * cuelan en cuanto se cambia el texto sin probarlo.
			 *
			 * Lo que se hace es quitarle la s al sitio y ponerla aqui cuando
			 * toca, de modo que las dos formas salen bien sin tener que mirar
			 * cual era. Y con un solo ingreso —el caso mas comun de todos,
			 * porque es el de quien esta probando— sale "1 ingreso", que es lo
			 * que tiene que leerse bien: es el aviso que dice que hay que
			 * desactivar en vez de borrar.
			 */
			$palabra = rtrim($uso['donde'], 's');

			$parte = $cuantas . ' ' . $palabra . ($cuantas === 1 ? '' : 's');

			if ($uso['dadasDeBaja'] > 0) {
				$dadas = $uso['dadasDeBaja'];

				$parte .= ' (' . $dadas . ' dado' . ($dadas === 1 ? '' : 's') . ' de baja)';
			}

			$partes[] = $parte;
		}

		$cuantas = count($partes);

		if ($cuantas === 1) {
			return $partes[0];
		}

		$ultima = array_pop($partes);

		return implode(', ', $partes) . ' y ' . $ultima;
	}
}

<?php

namespace App\Models;

use App\Models\Base\Caja as BaseCaja;
use Illuminate\Support\Facades\DB;

/**
 * Las cajas: donde entra el dinero cuando el cliente paga.
 *
 * Una caja es un hecho fisico del taller, no una clasificacion del gasto. Puede
 * ser la caja chica de la recepcion, el fondo con que trabaja el taller, el
 * efectivo del vehiculo que va a buscar el oro. El nombre lo pone el taller y
 * esta pantalla se limita a que se pueda escribir, corregir, desactivar y dar de
 * baja sin salir de la aplicacion.
 *
 * Y POR QUE ESTA PANTALLA SI PUEDE CREAR, Y LA DE LOS TIPOS DE INGRESO NO.
 *
 * Los tipos de ingreso los puso el taller a mano en la base —cuatro, de los que
 * el sabe— y la pantalla no los anade. Aqui es al reves, y la diferencia no es
 * una mania sino que la tabla estaba vacia: una pantalla que solo puede
 * corregir y desactivar filas que no existen se abre y no hace nada. La caja se
 * crea cuando el taller tiene la caja, que es un hecho —se abre la caja chica
 * un dia u otro— y no una opinion sobre como se lleva la contabilidad.
 *
 * CUANDO SE USE EN LOS COBROS.
 *
 * La tabla cobros tiene la caja_id, y esa pantalla todavia no existe. Cuando
 * exista, la caja se elegira de las activas, con el mismo paraRegistrar() que
 * esta usando el catalogo de tipos de ingreso: el filtro vive aqui y no en el
 * desplegable de la pantalla, para que ningun camino lo esquive.
 *
 * Y COMO EN EL RESTO DEL SISTEMA: un nombre puede repetirse.
 *
 * No hay indice unico, igual que en los tipos de ingreso, las categorias de
 * costo y los tipos de pago de empleado. Un taller puede tener dos fondos con
 * el mismo nombre corto y distinguirlos por la descripcion, y lo que obliga a no
 * repetir un nombre es una regla inventada, no el negocio. La consecuencia se
 * acepta a conciencia: si el taller repite un nombre, "cuanto hay en la caja
 * chica" saldra partido en dos filas. Es su decision y por eso no se le
 * impone una.
 */
class Cajas extends BaseCaja
{
	protected $fillable = [
		self::NOMBRE,
		self::DESCRIPCION,
		self::ESTADO
	];

	/**
	 * Las cajas que se ofrecen al registrar un cobro.
	 *
	 * Solo las activas, y por nombre. El filtro vive aqui y no en el desplegable
	 * de la pantalla del cobro, para que un caja desactivada salga de todas
	 * partes a la vez: una caja desactivada es una que se dejo de usar, y lo que
	 * se registro con ella antes sigue siendo verdad.
	 */
	public function scopeParaRegistrar($query)
	{
		return $query->where(self::ESTADO, true)
			->orderBy(self::NOMBRE);
	}

	/**
	 * Los cobros de esta caja, y cuantos hay.
	 *
	 * Se cuentan TODAS las filas de cobros, incluidas las dadas de baja, y no
	 * solo las vivas, porque la clave foranea sigue escribiendo el numero en las
	 * borradas. La FK es restrict —"no lleva ON DELETE, y el valor por defecto de
	 * MySQL es restringir"— de modo que borrar la caja con un cobro dado de baja
	 * tambien lo impide. Si aqui se contaran solo los vivos, el boton dejaria
	 * pulsar el borrar y reventaria con un error de MySQL en vez de con un aviso
	 * que dice lo que hay que hacer.
	 *
	 * Y el try se traga el error a proposito. Esta consulta se ejecuta una vez
	 * por fila de la lista, y la lista se puede abrir antes de que la tabla de
	 * cobros exista —es lo que pasa con una instalacion nueva— y sin esto la
	 * pantalla de cajas no abriria por un problema que es de otra tabla.
	 *
	 * @return array<int, array{tabla: string, donde: string, cuantas: int, dadasDeBaja: int}>
	 */
	public function usos(): array
	{
		try {
			$filas = DB::table('cobros')
				->where('caja_id', $this->id)
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
				'tabla' => 'cobros',
				'donde' => 'cobros',
				'cuantas' => (int) $filas->cuantas,
				'dadasDeBaja' => (int) $filas->dadas,
			],
		];
	}

	/**
	 * Si esta caja se puede borrar.
	 *
	 * La respuesta no es "si no esta en uso" a secas: es que se pueda. Lo de que
	 * este en uso y lo de que no se pueda se separan porque hay un caso en que las
	 * dos cosas se pueden decir a la vez y aqui no: las cajas no tienen ninguna
	 * condicion que las vuelva intocables, a diferencia de la moneda base del
	 * taller. Lo unico que protege una caja es que haya cobros con ella.
	 */
	public function sePuedeBorrar(): bool
	{
		return $this->usos() === [];
	}

	/**
	 * "en 3 cobros", y si alguno esta dado de baja, se dice.
	 *
	 * Va aqui y no en la vista porque la frase la tienen que entender el aviso
	 * del controlador y el de la ficha, y dos copias de la misma frase se separan
	 * en cuanto una cambia. Y el matiz de las dadas de baja se dice por el mismo
	 * motivo: si el usuario ve "en 3 cobros" y los tres los borro, no va a
	 * entender por que no le deja dar de baja una.
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
			 * El sitio es "cobros" a proposito: es el nombre de la tabla, que es
			 * como lo nombra la documentacion. Por eso antes salia "en 2
			 * cobross" al aniadirle una s encima, y "en 1 cobros" al no
			 * aniadirla. Ninguna de las dos formas esta bien, y las dos se
			 * cuelan en cuanto se cambia el texto sin probarlo.
			 *
			 * Lo que se hace es quitarle la s al sitio y ponerla aqui cuando
			 * toca, de modo que las dos formas salen bien sin tener que mirar
			 * cual era. Y con un solo cobro —"el caso mas comun de todos,
			 * porque es el de quien esta probando"— sale "1 cobro", que es lo
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

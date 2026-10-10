<?php

namespace App\Models;

use App\Models\Base\MetodosPago as BaseMetodosPago;
use Illuminate\Support\Facades\DB;

/**
 * Los metodos de pago: como recibe el taller el dinero de un cobro.
 *
 * Efectivo, transferencia, tarjeta, el talon con que se paga despues. Es el
 * catalogo que se elige al registrar un cobro, y no el mismo que los metodos de
 * pago del empleado, que son otra cosa: aquellos son como se le paga a la gente
 * que trabaja aqui, y estos como se le paga a quien compra.
 *
 * Y POR QUE ESTA PANTALLA PUEDE CREAR, Y POR QUE ESO NO ES UNA EXCEPCION.
 *
 * Los tipos de ingreso no los anade, porque son una clasificacion de como entra
 * el dinero y eso lo decide el taller, que los puso a mano en la base. Los
 * metodos de pago si, y el precedente mas cercano esta en el mismo proyecto: los
 * tipos de pago de empleado —que son exactamente la misma idea, como se paga una
 * cosa— tienen pantalla con alta, y las seis filas que hay las metio el taller
 * uno a uno desde ella. Un catalogo de formas de cobrar es una lista de hechos
 * del taller, no una teoria sobre como se lleva la contabilidad: el taller
 * acepta efectivo y transferencia, o acepta targetas, y la pantalla tiene que
 * poder recoger lo que contourne.
 *
 * Ademas la tabla esta vacia, y una pantalla que solo puede corregir y
 * desactivar filas que no existen se abre y no hace nada.
 *
 * COMO EN EL RESTO DEL SISTEMA: un nombre puede repetirse.
 *
 * No hay indice unico, igual que en los tipos de ingreso, las categorias de
 * costo, los tipos de pago de empleado y las cajas. Un taller puede tener dos
 * formas de cobrar con el mismo nombre corto y distinguirlas por la descripcion, y
 * lo que obliga a no repetir un nombre es una regla inventada, no el negocio. A
 * diferencia de la caja, aqui un nombre repetido no estropea ninguna suma: los
 * metodos de pago no se agregan entre si —"cuanto se cobro en efectivo" es una
 * pregunta que se hace sobre los cobros, no sobre el catalogo—, asi que un
 * duplicado sale dos veces en el desplegable y ya esta.
 */
class MetodosPagos extends BaseMetodosPago
{
	protected $fillable = [
		self::NOMBRE,
		self::DESCRIPCION,
		self::ESTADO
	];

	/**
	 * Los metodos que se ofrecen al registrar un cobro.
	 *
	 * Solo los activos, y por nombre. El filtro vive aqui y no en el desplegable
	 * de la pantalla del cobro, para que ningun camino lo esquive: un metodo
	 * desactivado es uno que se dejo de usar, y los cobros que ya se
	 * registraron con el siguen siendo verdad.
	 */
	public function scopeParaRegistrar($query)
	{
		return $query->where(self::ESTADO, true)
			->orderBy(self::NOMBRE);
	}

	/**
	 * Los cobros, movimientos y cierres que usan este método.
	 *
	 * Se cuentan TODAS las filas de cobros, incluidas las dadas de baja, y no
	 * solo las vivas, porque la clave foranea sigue escribiendo el numero en las
	 * borradas. La FK es restrict —"no lleva ON DELETE, y el valor por defecto de
	 * MySQL es restringir"— de modo que borrar el metodo con un cobro dado de
	 * baja tambien lo impide. Si aqui se contaran solo los vivos, el boton
	 * dejaria pulsar el borrar y reventaria con un error de MySQL en vez de con un
	 * aviso que dice lo que hay que hacer.
	 *
	 * Y el try se traga el error a proposito. Esta consulta se ejecuta una vez
	 * por fila de la lista, y la lista se puede abrir antes de que la tabla de
	 * cobros tenga datos, que es lo que pasa con una instalacion nueva. Sin esto
	 * la pantalla no abriria por un problema que es de otra tabla.
	 *
	 * @return array<int, array{tabla: string, donde: string, cuantas: int, dadasDeBaja: int}>
	 */
	public function usos(): array
	{
		try {
			$filas = DB::table('cobros')
				->where('metodo_pago_id', $this->id)
				->selectRaw('COUNT(*) as cuantas, SUM(deleted_at IS NOT NULL) as dadas')
				->first();
		} catch (\Throwable $e) {
			return [];
		}

		$usos = [];

		if ($filas && (int) $filas->cuantas > 0) {
			$usos[] = [
				'tabla' => 'cobros',
				'donde' => 'cobros',
				'cuantas' => (int) $filas->cuantas,
				'dadasDeBaja' => (int) $filas->dadas,
			];
		}

		foreach ([
			'movimientos_caja' => 'movimiento',
			'detalle_cierres_caja' => 'detalle de cierre',
		] as $tabla => $donde) {
			try {
				$cuantas = DB::table($tabla)->where('metodo_pago_id', $this->id)->count();
			} catch (\Throwable $e) {
				continue;
			}

			if ($cuantas > 0) {
				$usos[] = [
					'tabla' => $tabla,
					'donde' => $donde,
					'cuantas' => $cuantas,
					'dadasDeBaja' => 0,
				];
			}
		}

		return $usos;
	}

	/**
	 * Si este metodo se puede borrar.
	 *
	 * La respuesta no es "si no esta en uso" a secas: es que se pueda. Lo de que
	 * este en uso y lo de que no se pueda se separan porque hay un caso en que las
	 * dos cosas se pueden decir a la vez y aqui no: los metodos de pago no tienen
	 * ninguna condicion que los vuelva intocables, a diferencia de la moneda base
	 * del taller. Lo unico que protege un metodo de pago es que haya cobros con el.
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

<?php

namespace App\Models;

use App\Models\Base\PreciosOro as BasePreciosOro;
use App\Models\Concerns\ClaveUnica;

class PreciosOro extends BasePreciosOro
{
	/*
	 * La clave de un precio es el trio fecha, unidad y moneda, y es un indice
	 * unico en una tabla que borra de forma logica. Es la misma combinacion
	 * que rompia al tipo de cambio, y el trait es el que la resuelve: reviving
	 * la fila en vez de crear una segunda.
	 */
	use ClaveUnica;

	protected $fillable = [
		self::FECHA,
		self::PRECIO,
		self::UNIDAD,
		self::MONEDA_ID,
		self::FUENTE,
		self::OBSERVACIONES
	];

	/**
	 * Las unidades en las que se puede guardar el precio.
	 *
	 * Se validan contra esta lista y no contra lo que le venga al usuario, y
	 * la razon es la de siempre: la columna es una cadena de veinte
	 * caracteres y "g", "Gramo" y "gramo" therein tres unidades distintas que
	 * la base no distingue. Con lista cerrada, el gramo es siempre el gramo.
	 *
	 * Las tres estan porque las tres se usan: el gramo es con lo que se
	 * recupera el oro en todo el sistema —recuperaciones, liquidaciones y
	 * cierres llevan gramos—, la onza troy es como se cotiza en los mercados
	 * internacionales, y el quilogramo aparece en documentos de compra de
	 * mineral. Meter solo el gramo obligaria a convertir a mano cada vez que
	 * el usuario lee el precio de otra manera.
	 */
	public const UNIDADES = [
		'gramo' => 'Gramo',
		'onza troy' => 'Onza troy',
		'quilogramo' => 'Quilogramo',
	];

	/**
	 * Cuantos gramos vale una unidad.
	 *
	 * Solo se necesita para valorar cuando el precio guardado no es del gramo,
	 * porque el sistema entero guarda el oro en gramos. La onza troy son
	 * 31,1034768 gramos, que no son 31 exactos: el oro se pesa en onzas troy,
	 * que son unidades troy de 31,1034768 gramos, y no en onzas comunes de
	 * 28,35. Es un numero que parece un descuido y no lo es, y por eso se
	 * escribe con todos sus decimales y con su nombre al lado.
	 */
	private const GRAMOS_POR_UNIDAD = [
		'gramo' => 1.0,
		'onza troy' => 31.1034768,
		'quilogramo' => 1000.0,
	];

	/**
	 * El precio vigente de una unidad en una fecha.
	 *
	 * Devuelve null cuando no hay ninguno, y no el ultimo conocido: eso lo
	 * decide quien llama. Aqui solo se busca.
	 *
	 * Se toma el registro mas reciente cuya fecha sea menor o igual a la
	 * pedida, igual que hace el tipo de cambio, y por el mismo motivo: el
	 * banco no publica un precio todos los dias, y el fin de semana y los
	 * dias festivos el taller sigue trabajando.
	 *
	 * Los precios en cero se saltan. Un cero en esta tabla no significa que
	 * el oro valia cero —eso no ha pasado nunca— sino que ese dia no se
	 * sabe el precio, que es lo que se escribe cuando se carga la serie y aun
	 * no se hamirado el dato. Si se devolviera como si fuera un precio, una
	 * valoracion multiplicaria gramos por cero y pondria cero en el
	 * documento, que es el peor resultado posible: sale un numero y no avisa
	 * de nada. Saltandolo, la valoracion cae en el ultimo precio que si se
	 * sabe, que es lo que se ha hecho siempre en el taller, y si no hay
	 * ninguno devuelve null y quien llama avisa de que no hay con que
	 * valorar.
	 *
	 * @return static|null
	 */
	public static function vigentePara(string $fecha, string $unidad, int $monedaId): ?self
	{
		$registro = static::where(self::MONEDA_ID, $monedaId)
			->where(self::UNIDAD, $unidad)
			->where(self::PRECIO, '>', 0)
			->whereDate(self::FECHA, '<=', $fecha)
			->orderByDesc(self::FECHA)
			->orderByDesc(self::ID)
			->first();

		if ($registro === null) {
			return null;
		}

		if (! is_numeric($registro->precio)) {
			return null;
		}

		return (float) $registro->precio > 0 ? $registro : null;
	}

	/**
	 * El precio de un gramo, aunque el guardado sea de otra unidad.
	 *
	 * Devuelve null si no hay con que, y no inventa. Se usa para valorar, que
	 * es donde importa: el sistema guarda el oro en gramos, y si el precio que
	 * se cargo es de onza troy hay que convertirlo antes de multiplicar.
	 *
	 * @return array{precio: float, de_que_dia: string, unidad: string, gramo: float}|null
	 */
	public static function precioDelGramo(string $fecha, int $monedaId): ?array
	{
		/*
		 * Se mira primero el gramo, que es la unidad con la que esta escrito
		 * todo el oro del sistema, y solo si no hay ninguno se recurre a las
		 * otras dos. El orden importa: si el mismo dia hay un precio de gramo
		 * y otro de onza, gana el del gramo, y no porque uno valga mas que el
		 * otro sino porque es el que esta en las unidades con las que se
		 * calcula. Por eso se dice de que unidad salio: quien valora tiene que
		 * poder enseñarlo.
		 */
		foreach (self::ordenDeBusqueda() as $unidad) {
			$registro = static::vigentePara($fecha, $unidad, $monedaId);

			if ($registro === null) {
				continue;
			}

			$precio = (float) $registro->precio;
			$gramosPorUnidad = self::GRAMOS_POR_UNIDAD[$unidad];

			return [
				'precio' => $precio,
				'de_que_dia' => $registro->fecha->toDateString(),
				'unidad' => $unidad,
				'gramo' => $precio / $gramosPorUnidad,
			];
		}

		return null;
	}

	/**
	 * Las unidades por las que se busca un precio, en orden de preferencia.
	 *
	 * @return array<int, string>
	 */
	private static function ordenDeBusqueda(): array
	{
		return array_keys(self::GRAMOS_POR_UNIDAD);
	}

	/**
	 * Si esta fila dice que no se sabe el precio de ese dia.
	 *
	 * No es un metodo sobre el valor, es una pregunta sobre lo que significa
	 * el cero, y la hacen la lista y la valoracion. Vive aqui para que las dos
	 * digan lo mismo: si el gramo esta en cero, el gramo no vale cero, lo que
	 * pasa es que de ese dia no se sabe cuanto valia.
	 */
	public function precioDesconocido(): bool
	{
		return (float) $this->precio <= 0;
	}
}

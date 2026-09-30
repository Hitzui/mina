<?php

namespace App\Models;

use App\Models\Base\ValoracionesOro as BaseValoracionesOro;
use App\Models\Concerns\ClaveUnica;

class ValoracionesOro extends BaseValoracionesOro
{
	/*
	 * La moneda en la que se busca el precio del gramo.
	 *
	 * Este NO es una columna de la tabla, y por eso no sale del modelo base:
	 * es el nombre de un campo del formulario y de la peticion del calculo.
	 * En la tabla no hay porque de esa moneda no guarda nada —la que guarda el
	 * precio es la de precios_oro, y la que guarda el valor es MONEDA_ID—: lo
	 * unico que se guarda es el id de la fila de precio que se uso, y en esa
	 * fila esta su moneda. O sea que el dato si queda escrito, pero en la otra
	 * tabla y por medio del precio, no en una columna propia.
	 *
	 * Se declara aqui y no se escribe a pelo en el controlador porque sale en
	 * cuatro sitios —las reglas de validacion, los dos mensajes de error y la
	 * llamada al servicio— y si el nombre viviera suelto habria que acordarse
	 * de cambiarlo en los cuatro. Con una constante, o se cambia en uno o se
	 * rompe el formulario de forma visible.
	 */
	public const PRECIO_MONEDA = 'precio_moneda';

	/*
	 * La clave de una valoracion es la recuperacion: una recuperacion, un
	 * valor. Es un indice unico en una tabla que borra de forma logica, que es
	 * la misma combinacion que rompia al tipo de cambio y al precio del oro, y
	 * el trait es el que la resuelve reviving la fila en vez de crear una
	 * segunda.
	 *
	 * El valor no se escribe nunca desde aqui: lo pone el servicio que
	 * calcula, y por eso el campo no aparece entre los que se rellenan. Es lo
	 * unico que impide que un documento diga una cifra y la cuenta del taller
	 * diga otra.
	 */
	use ClaveUnica;

	protected $fillable = [
		self::RECUPERACION_ID,
		self::PRECIO_ORO_ID,
		self::VALOR,
		self::MONEDA_ID,
		self::FECHA,
		self::OBSERVACIONES
	];

	/*
	 * La recuperacion de la que sale esta valoracion.
	 *
	 * Se reescribe la relacion porque la del modelo base apunta a
	 * App\Models\Recuperacione, que es el modelo que Reliese genero y que esta
	 * aqui sin usar: el del taller es App\Models\Recuperaciones, en plural, que
	 * es el que tiene los metodos —gramosValorados(), purezaEnPorcentaje()— y
	 * el que usan el resto de la aplicacion.
	 *
	 * Sin reescribirla, $valoracion->recuperacion traia una recuperacion sin
	 * metodos: se podia leer la fecha y los gramos, que vienen de la base, pero
	 * cualquier llamada propia fallaba. Y un fallo asi es de los que no se ven
	 * hasta que alguien llama al metodo, que es un mes despues.
	 *
	 * El nombre de la relacion es el mismo —"recuperacion"— y solo cambia la
	 * clase de la que viene, que es lo unico que se puede cambiar sin tocar los
	 * sitios que la usan.
	 */
	public function recuperacion()
	{
		return $this->belongsTo(Recuperaciones::class, ValoracionesOro::RECUPERACION_ID);
	}

	/**
	 * Los gramos que se valoraron en esta cifra.
	 *
	 * O los finos, si la recuperacion tiene pureza, o los que salieron si no
	 * la tiene. Es la misma regla que aplica el servicio que calcula el
	 * valor, puesta aqui para poder pintar el detalle de una valoracion vieja
	 * sin tener que volver a mirar la recuperacion.
	 *
	 * @return array{gramos: float, pureza: ?float, valorados: float, usa_pureza: bool}
	 */
	public function gramosValorados(): array
	{
		$pureza = $this->recuperacion?->pureza;

		$gramos = $this->recuperacion ? (float) $this->recuperacion->gramos : 0.0;

		$usaPureza = $pureza !== null && (float) $pureza > 0;

		return [
			'gramos' => $gramos,
			'pureza' => $pureza === null ? null : (float) $pureza,
			'valorados' => $usaPureza ? $gramos * (float) $pureza : $gramos,
			'usa_pureza' => $usaPureza,
		];
	}
}

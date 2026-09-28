<?php

namespace App\Models;

use App\Models\Base\Moneda as BaseMoneda;
use App\Models\Concerns\ClaveUnica;
use Illuminate\Support\Facades\DB;

class Moneda extends BaseMoneda
{
	/*
	 * El codigo es unico en la base y la tabla borra de forma logica, que es
	 * justo la combinacion que hace que un create() a pelo reviente con un
	 * "Duplicate entry" cuando se vuelve a poner el codigo de una moneda que
	 * se habia dado de baja. El trait lo resuelve reviving la fila.
	 *
	 * Y revive con seguridad porque el controlador no deja borrar una moneda
	 * que se este usando: la que se revive esta vacia.
	 */
	use ClaveUnica;

	/**
	 * Las once tablas que guardan una moneda, con el nombre del sitio donde se
	 * ve lo que esta guardado con ella.
	 *
	 * El nombre que se enseña al usuario no es el de la tabla sino el del
	 * sitio donde va a buscarlo para entender el aviso. "No se puede borrar:
	 * hay 2 compras" se entiende; "integrity constraint violation on
	 * fk_compra_moneda" no.
	 *
	 * La clave de cada entrada es la tabla, y el valor el nombre. Se escribe
	 * a mano y no se descubre en caliente, por dos razones. La primera es que
	 * el orden en que salen las cosas en el aviso tiene que ser el mismo
	 * siempre. La segunda es que recorrer el diccionario buscando el nombre de
	 * cada tabla es una consulta por tabla, y la de una tabla que todavia no
	 * habria que tragarse la excepcion; y una tabla que se anade con una
	 * migracion nueva no la encuentra nadie, porque aqui no se busca.
	 *
	 * Una tabla de esta lista que no exista no se nota: la excepcion se come y
	 * la moneda se sigue pudiendo borrar, que es lo que corresponde a una tabla
	 * que todavia no existe.
	 */
	private const TABLAS_CON_MONEDA = [
		'compras' => 'compras',
		'tipos_cambio' => 'tipos de cambio',
		'empleados_pagos' => 'pagos a empleados',
		'trabajos_empleados' => 'trabajos de empleados',
		'movimientos_costos' => 'costos',
		'movimientos_inventario' => 'movimientos del almacén',
		'valoraciones_oro' => 'valoraciones del oro',
		'precios_oro' => 'precios del oro',
		'acuerdos_orden' => 'acuerdos de órdenes',
		'ingresos' => 'ingresos',
		'cobros' => 'cobros',
	];

	protected $fillable = [
		self::CODIGO,
		self::NOMBRE,
		self::SIMBOLO,
		self::ES_MONEDA_BASE,
		self::ESTADO
	];

	/**
	 * La moneda en la que esta el taller.
	 *
	 * Devuelve null si no hay ninguna marcada, que no deberia pasar: es lo que
	 * hace que el almacen no sepa con que valor tasar el material que entra.
	 * Se deja que devuelva null en vez de inventar una, para que quien lo
	 * llame tenga que decidir que hacer y no reciba un numero cualquiera.
	 */
	public static function base(): ?self
	{
		return static::where(self::ES_MONEDA_BASE, true)->first();
	}

	/**
	 * Si esta moneda se puede borrar.
	 *
	 * No se puede si es la base —porque es la que recibe todas las
	 * conversiones— ni si algo la esta usando. En los dos casos lo que queda
	 * es desactivarla, que la saca de los desplegables sin tocar lo que ya se
	 * registro con ella.
	 *
	 * Vive aqui y no en el controlador porque es una pregunta sobre la
	 * moneda, no sobre quien la esta mirando: la hacen el boton de eliminar,
	 * el boton de desactivar y la ficha, y si viviera en el controlador
	 * habria que repetirla en los tres sitios.
	 */
	public function sePuedeBorrar(): bool
	{
		return ! $this->es_moneda_base && $this->usos() === [];
	}

	/**
	 * Donde se usa esta moneda, y cuantas veces.
	 *
	 * Son once consultas, y solo se hacen al abrir la ficha de una moneda o
	 * al intentar borrarla. En la lista no se necesitan, y cargarlas por cada
	 * fila de la tabla seria hacer once veces el trabajo que nadie mira.
	 *
	 * @return array<int, array{tabla: string, donde: string, cuantas: int}>
	 */
	public function usos(): array
	{
		$usos = [];

		foreach (self::TABLAS_CON_MONEDA as $tabla => $donde) {
			try {
				$cuantas = DB::table($tabla)->where('moneda_id', $this->id)->count();
			} catch (\Throwable $e) {
				continue;
			}

			if ($cuantas > 0) {
				$usos[] = [
					'tabla' => $tabla,
					'donde' => $donde,
					'cuantas' => $cuantas,
				];
			}
		}

		return $usos;
	}

	/**
	 * "en 2 compras, 30 tipos de cambio y 4 pagos a empleados".
	 *
	 * Va aqui y no en la vista porque la frase la tienen que entender el
	 * aviso del controlador y el de la ficha, y dos copias de la misma frase
	 * se separan en cuanto una cambia.
	 */
	public function fraseDeUsos(): string
	{
		$partes = [];

		foreach ($this->usos() as $uso) {
			$partes[] = $uso['cuantas'] . ' ' . $uso['donde'];
		}

		$cuantas = count($partes);

		if ($cuantas === 0) {
			return '';
		}

		if ($cuantas === 1) {
			return $partes[0];
		}

		$ultima = array_pop($partes);

		return implode(', ', $partes) . ' y ' . $ultima;
	}
}

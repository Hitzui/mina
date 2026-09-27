<?php

namespace App\Models;

use App\Models\Base\CategoriasCosto as BaseCategoriasCosto;

class CategoriasCosto extends BaseCategoriasCosto
{
	protected $fillable = [
		self::NOMBRE,
		self::DESCRIPCION,
		self::ESTADO,
		self::AUTOMATICA,
	];

	/**
	 * Las categorias que se cargan a mano desde las pantallas de costo.
	 *
	 * Quedan fuera las marcadas como automaticas: la mano de obra se suma
	 * sola desde trabajos_empleados y la depreciacion desde
	 * proceso_equipos. Registrarlas aqui las contaria dos veces, y el
	 * error no se veria en ninguna parte: el total simplemente saldría
	 * mas alto.
	 *
	 * El filtro vive en el modelo y no en el combo de la pantalla, para
	 * que ningun camino lo esquive.
	 */
	public function scopeRegistrables($query)
	{
		return $query->where(self::AUTOMATICA, false);
	}

	/**
	 * Las que se muestran en los combos: activas y que se pueden cargar.
	 */
	public function scopeParaRegistrar($query)
	{
		return $query->registrables()
			->where(self::ESTADO, true)
			->orderBy(self::NOMBRE);
	}

	/**
	 * Donde se calcula esta categoria, para poder explicarlo en la
	 * pantalla y no tener que repetirlo en cada sitio.
	 */
	public function origenDeCalculo(): ?string
	{
		return match ($this->nombre) {
			'Mano de obra' => 'Suma de los trabajos de los empleados registrados en el proceso.',
			'Depreciación', 'Depreciacion' => 'Suma de la depreciación de los equipos usados en el proceso.',
			default => null,
		};
	}
}

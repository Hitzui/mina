<?php

namespace App\Models;

use App\Models\Base\ProcesosOrden as BaseProcesosOrden;

class ProcesosOrden extends BaseProcesosOrden
{
	/*
	 * Los estados del proceso, con su nombre y su color de una vez.
	 *
	 * Son los mismos cuatro que los de la orden y con la misma numeracion,
	 * porque describen lo mismo: por donde va el trabajo. Lo que cambia son
	 * las palabras, en masculino, porque el sustantivo es "el proceso".
	 *
	 * El estado ya no se escribe a mano. Sale de las fechas, que son lo que
	 * de verdad se teclea, y eso es lo que hacia que antes mintiera: un
	 * proceso que empezo y termino hace dias seguia diciendo "Pendiente"
	 * porque el numero se puso en 1 al crear y no habia donde moverlo.
	 *
	 * La excepcion es Cancelado: un proceso abandonado no se deduce de las
	 * fechas, porque tiene las mismas que uno que se termino a tiempo. Ese
	 * si se marca a mano, y es el unico uso que queda de la columna.
	 */
	public const ESTADO_CANCELADO = 0;
	public const ESTADO_PENDIENTE = 1;
	public const ESTADO_EN_PROCESO = 2;
	public const ESTADO_FINALIZADO = 3;

	public const ESTADOS = [
		self::ESTADO_CANCELADO => ['texto' => 'Cancelado', 'color' => 'bg-danger', 'hex' => '#e7515a'],
		self::ESTADO_PENDIENTE => ['texto' => 'Pendiente', 'color' => 'bg-secondary', 'hex' => '#888ea8'],
		self::ESTADO_EN_PROCESO => ['texto' => 'En proceso', 'color' => 'bg-warning', 'hex' => '#e2a03f'],
		self::ESTADO_FINALIZADO => ['texto' => 'Finalizado', 'color' => 'bg-success', 'hex' => '#00ab55'],
	];

	/**
	 * En que estados el proceso ya no admite datos nuevos.
	 *
	 * Finalizado y Cancelado cierran el proceso por el mismo motivo que
	 * cierran la orden: el trabajo se acabo, o se tiro la toalla.
	 */
	public const ESTADOS_CERRADOS = [
		self::ESTADO_FINALIZADO,
		self::ESTADO_CANCELADO,
	];

	/**
	 * El estado que le toca al proceso, deducido de las fechas.
	 *
	 * La regla, en orden:
	 *
	 *   - Cancelado: se marco a mano. Se mira primero porque es la unica
	 *     marca que no se deduce de nada.
	 *   - Sin fecha de inicio: no ha empezado. Pendiente.
	 *   - Con fecha de inicio y sin fecha de fin, o con la fecha de fin
	 *     todavia por delante: se empezo y no se acabo. En proceso.
	 *   - Con la fecha de fin ya pasada: se termino. Finalizado.
	 *
	 * Que la fecha de fin se compare con hoy y no basta con que este
	 * puesta importa: un proceso planificado para el dia 30 sigue siendo
	 * "En proceso" el dia 27, no "Finalizado" por tener la fecha puesta.
	 * Marcarlo como terminado antes de tiempo seria mentir otra vez, que
	 * es justo lo que se vino a arreglar.
	 */
	public function estadoCalculado(): int
	{
		if ($this->estaCancelado()) {
			return self::ESTADO_CANCELADO;
		}

		if ($this->fecha_inicio === null) {
			return self::ESTADO_PENDIENTE;
		}

		if ($this->fecha_fin === null || $this->fecha_fin->isFuture()) {
			return self::ESTADO_EN_PROCESO;
		}

		return self::ESTADO_FINALIZADO;
	}

	/**
	 * Si el proceso se marco como cancelado.
	 */
	public function estaCancelado(): bool
	{
		return (int) $this->estado === self::ESTADO_CANCELADO;
	}

	/**
	 * Nombre del estado, para textos.
	 *
	 * Un estado fuera del catalogo sale como "Pendiente" en vez de romper
	 * la pantalla: el numero lo pone el calculo, y con esto no puede
	 * quedar sin definir.
	 */
	public function estadoTexto(): string
	{
		return self::ESTADOS[$this->estadoCalculado()]['texto'] ?? 'Pendiente';
	}

	/**
	 * Clase de Bootstrap del estado, para pintar una etiqueta.
	 */
	public function estadoClase(): string
	{
		return self::ESTADOS[$this->estadoCalculado()]['color'] ?? 'bg-secondary';
	}

	/**
	 * Color en hexadecimal, para lo que no usa clases de Bootstrap.
	 */
	public function estadoColor(): string
	{
		return self::ESTADOS[$this->estadoCalculado()]['hex'] ?? '#888ea8';
	}

	/**
	 * La etiqueta tal como se ve en el listado y en la ficha.
	 */
	public function estadoEtiqueta(): string
	{
		return sprintf(
			'<span class="badge %s">%s</span>',
			$this->estadoClase(),
			e($this->estadoTexto())
		);
	}

	/**
	 * Si el proceso esta en un estado en el que ya no se admiten datos.
	 */
	public function estaCerrado(): bool
	{
		return in_array($this->estadoCalculado(), self::ESTADOS_CERRADOS, true);
	}

	/**
	 * Como se muestra un proceso en los combos y en las pantallas.
	 *
	 * El proceso no tiene columna "nombre": se describe con el codigo y,
	 * si tiene, la etapa. Sin esto cada pantalla tiene que armarlo a mano
	 * y es facil que una se quede en blanco (que es lo que pasaba en el
	 * detalle del trabajo de un empleado: pedia ->nombre, que no existe,
	 * y por eso siempre caia en la etiqueta de trabajo general).
	 */
	public function getNombreCompletoAttribute(): string
	{
		$etapa = $this->etapa?->nombre;

		return $etapa
			? $this->codigo . ' - ' . $etapa
			: (string) $this->codigo;
	}

	/**
	 * Los trabajos de los empleados que se registran en este proceso.
	 */
	public function trabajos_empleados()
	{
		return $this->hasMany(TrabajosEmpleado::class, TrabajosEmpleado::PROCESO_ORDEN_ID);
	}

	/**
	 * Equipos usados en este proceso, con su periodo de uso.
	 */
	public function equipos()
	{
		return $this->hasMany(ProcesoEquipo::class, ProcesoEquipo::PROCESO_ORDEN_ID);
	}

	/**
	 * Cuanto se le paga a los empleados por este proceso: la suma de los
	 * totales de los trabajos registrados.
	 *
	 * Se calcula al momento y no se guarda: asi nunca puede quedar
	 * desfasado respecto a los trabajos, que es el problema tipico de
	 * mantener un total duplicado.
	 *
	 * Usa withTrashed() a proposito para que el total refleje el
	 * historial, incluidos los trabajos que se han borrado logicamente.
	 * Si lo que se quiere es solo lo que esta vigente, quitarlo.
	 */
	public function getCostoEmpleadosAttribute(): float
	{
		// Con la relacion ya cargada no hay que volver a consultar
		if ($this->relationLoaded('trabajos_empleados')) {
			return (float) $this->trabajos_empleados
				->sum(fn ($trabajo) => (float) $trabajo->total);
		}

		return (float) $this->trabajos_empleados()->withTrashed()->sum('total');
	}

	/**
	 * Depreciacion de los equipos que se usaron en este proceso.
	 *
	 * El detalle de cada equipo si queda guardado en proceso_equipos; el
	 * total se suma al momento para que nunca quede desfasado.
	 */
	public function getCostoEquiposAttribute(): float
	{
		return ProcesoEquipo::depreciacionDeProceso($this->id);
	}

	/**
	 * Los otros costos del proceso: energia, agua, materia prima y demas
	 * conceptos que se cargan desde la pantalla de costos.
	 *
	 * Se suma al momento desde movimientos_costos. No se guarda un total
	 * acumulado en el proceso porque entonces cualquier movimiento
	 * registrado desde otra pantalla dejaria el proceso desfasado.
	 */
	public function getCostoOtrosAttribute(): float
	{
		return (float) MovimientosCosto::query()
			->deProceso($this->id)
			->sum(MovimientosCosto::COSTO_TOTAL);
	}

	/**
	 * La materia prima que se consumio en este proceso: cemento, quimicos
	 * y demas.
	 *
	 * Viene del kardex y no de la tabla de costos, porque es material que
	 * sale fisicamente del almacen: se controla con existencias y su costo
	 * unitario lo fija el promedio de lo que habia dentro.
	 *
	 * Se suma al momento, igual que los otros componentes, para que no
	 * pueda quedar desfasado respecto a los consumos registrados.
	 */
	public function getCostoMateriaPrimaAttribute(): float
	{
		// Con la relacion ya cargada no hay que volver a consultar
		if ($this->relationLoaded('movimientos_materia_prima')) {
			return (float) $this->movimientos_materia_prima
				->sum(fn ($movimiento) => (float) $movimiento->importe_nio);
		}

		return (float) MovimientosInventario::query()
			->deProceso($this->id)
			->where(MovimientosInventario::TIPO, MovimientosInventario::TIPO_SALIDA)
			->sum(MovimientosInventario::COSTO_TOTAL_NIO);
	}

	/**
	 * Los movimientos de material de este proceso.
	 */
	public function movimientos_materia_prima()
	{
		return $this->hasMany(MovimientosInventario::class, MovimientosInventario::PROCESO_ORDEN_ID);
	}

	/**
	 * Costo total del proceso: mano de obra, depreciacion de equipos,
	 * materia prima y los demas costos registrados.
	 */
	public function getCostoTotalAttribute(): float
	{
		return $this->costo_empleados
			+ $this->costo_equipos
			+ $this->costo_materia_prima
			+ $this->costo_otros;
	}

	/**
	 * El costo del proceso desglosado por concepto.
	 *
	 * Devuelve una lista de filas con nombre, importe y de donde sale
	 * cada una, para que la pantalla muestre de donde viene cada numero en
	 * vez de un total que nadie puede desarmar.
	 *
	 * Los dos conceptos que calcula el sistema aparecen siempre, esten o
	 * no tengan movimientos: si su importe es cero, es informacion utile
	 * (dice que aun no se registro nada), no ruido. Lo mismo con las
	 * categorias que se pueden cargar a mano y todavia no tienen ninguna:
	 * asi se ve que faltan, no que no existen.
	 *
	 * @return \Illuminate\Support\Collection<int, array{nombre: string, importe: float, origen: string, automatico: bool}>
	 */
	public function costosDesglosados()
	{
		$filas = [
			[
				'nombre' => 'Mano de obra',
				'importe' => $this->costo_empleados,
				'origen' => 'Trabajos de los empleados registrados en el proceso.',
				'automatico' => true,
			],
			[
				'nombre' => 'Depreciación de equipos',
				'importe' => $this->costo_equipos,
				'origen' => 'Equipos asignados al proceso, según los días de uso.',
				'automatico' => true,
			],
			[
				'nombre' => 'Materia prima',
				'importe' => $this->costo_materia_prima,
				'origen' => 'Material consumido del almacén en este proceso, '
					. 'valorado al costo promedio del inventario.',
				'automatico' => true,
			],
		];

		// Se agrupa en la base, no en php: una consulta y no una por fila
		$porCategoria = MovimientosCosto::query()
			->deProceso($this->id)
			->selectRaw('categoria_costo_id, SUM(costo_total) AS importe')
			->groupBy('categoria_costo_id')
			->get()
			->keyBy('categoria_costo_id');

		$conMovimiento = [];

		foreach ($porCategoria as $categoriaId => $movimiento) {
			$categoria = CategoriasCosto::find($categoriaId);
			$conMovimiento[] = (int) $categoriaId;

			$filas[] = [
				'nombre' => $categoria?->nombre ?? 'Categoría eliminada',
				'importe' => (float) $movimiento->importe,
				'origen' => $categoria?->descripcion
					?: 'Costo registrado a mano en este proceso.',
				'automatico' => false,
			];
		}

		// Las que todavia no tienen movimientos, para que se vea que faltan
		foreach (CategoriasCosto::paraRegistrar()->get() as $categoria) {
			if (in_array((int) $categoria->id, $conMovimiento, true)) {
				continue;
			}

			$filas[] = [
				'nombre' => $categoria->nombre,
				'importe' => 0.0,
				'origen' => $categoria->descripcion
					?: 'Costo registrado a mano en este proceso.',
				'automatico' => false,
			];
		}

		return collect($filas);
	}

	protected $fillable = [
		self::ORDEN_TRABAJO_ID,
		self::ETAPA_ID,
		self::FECHA_INICIO,
		self::FECHA_FIN,
		self::PESO_ENTRADA,
		self::PESO_SALIDA,
		self::ESTADO,
		self::OBSERVACIONES,
		self::CODIGO
	];
}

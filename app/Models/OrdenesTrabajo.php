<?php

namespace App\Models;

use App\Models\Base\OrdenesTrabajo as BaseOrdenesTrabajo;

class OrdenesTrabajo extends BaseOrdenesTrabajo
{
    /*
     * Los estados de la orden, con su nombre y su color de una vez.
     *
     * Estaban escritos sueltos en el listado y en la pantalla de la orden,
     * y el calendario los necesitaba tambien. Cada copia era una ocasion
     * de que uno se quedara sin actualizar, y el desajuste se ve enseguida:
     * una orden "En proceso" pintada de otro color.
     *
     * El color va en dos formas porque se usa en dos sitios: la clase de
     * Bootstrap, que pintan las etiquetas del listado, y el hexadecimal,
     * que es lo que necesita el calendario, que no usa clases de Bootstrap.
     */
    public const ESTADO_PENDIENTE = 1;
    public const ESTADO_EN_PROCESO = 2;
    public const ESTADO_FINALIZADA = 3;
    public const ESTADO_CANCELADA = 4;

    public const ESTADOS = [
        self::ESTADO_PENDIENTE => ['texto' => 'Pendiente', 'color' => 'bg-primary', 'hex' => '#4361ee'],
        self::ESTADO_EN_PROCESO => ['texto' => 'En proceso', 'color' => 'bg-warning', 'hex' => '#e2a03f'],
        self::ESTADO_FINALIZADA => ['texto' => 'Finalizada', 'color' => 'bg-success', 'hex' => '#00ab55'],
        self::ESTADO_CANCELADA => ['texto' => 'Cancelada', 'color' => 'bg-danger', 'hex' => '#e7515a'],
    ];

	protected $fillable = [
		self::CODIGO,
		self::CLIENTE_ID,
		self::FECHA,
		self::DESCRIPCION,
		self::PESO_MINERAL,
		self::UNIDAD_PESO,
		self::ESTADO
	];

    /**
     * Nombre del estado, para textos.
     *
     * Si el estado no esta en el catalogo sale "Desconocido" en vez de
     * fallar: un estado nuevo en la base no debe romper una pantalla
     * entera.
     */
    public function estadoTexto(): string
    {
        return self::ESTADOS[$this->estado]['texto'] ?? 'Desconocido';
    }

    /**
     * Clase de Bootstrap del estado, para pintar una etiqueta.
     */
    public function estadoClase(): string
    {
        return self::ESTADOS[$this->estado]['color'] ?? 'bg-secondary';
    }

    /**
     * Color en hexadecimal, para lo que no usa clases de Bootstrap.
     */
    public function estadoColor(): string
    {
        return self::ESTADOS[$this->estado]['hex'] ?? '#888ea8';
    }

    /**
     * La orden tal como se ve en una etiqueta del listado.
     */
    public function estadoEtiqueta(): string
    {
        return sprintf(
            '<span class="badge %s">%s</span>',
            $this->estadoClase(),
            e($this->estadoTexto())
        );
    }

    public function procesos_ordenes()
    {
        return $this->hasMany(ProcesosOrden::class, ProcesosOrden::ORDEN_TRABAJO_ID);
    }

    /**
     * Los trabajos de los empleados de esta orden, alcanzados a traves de
     * sus procesos: el trabajo ya no guarda orden_trabajo_id.
     */
    public function trabajos_empleados()
    {
        return $this->hasManyThrough(
            TrabajosEmpleado::class,
            ProcesosOrden::class,
            'orden_trabajo_id',
            'proceso_orden_id',
            'id',
            'id'
        );
    }
}

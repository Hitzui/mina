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
     * La numeracion es la que usa el negocio: 0 Cancelada, 1 Pendiente,
     * 2 En proceso, 3 Finalizada. Ojo con el 0: no es "sin estado", es
     * "cancelada", y por eso no puede valer como valor por defecto de un
     * formulario. Lo que si es un valor por defecto es 1, Pendiente.
     *
     * El color va en dos formas porque se usa en dos sitios: la clase de
     * Bootstrap, que pintan las etiquetas del listado, y el hexadecimal,
     * que es lo que necesita el calendario, que no usa clases de Bootstrap.
     *
     * Finalizada y Cancelada son las dos cerradas: en ninguna de las dos se
     * admiten datos nuevos en la orden.
     */
    public const ESTADO_CANCELADA = 0;
    public const ESTADO_PENDIENTE = 1;
    public const ESTADO_EN_PROCESO = 2;
    public const ESTADO_FINALIZADA = 3;

    public const ESTADOS = [
        self::ESTADO_CANCELADA => ['texto' => 'Cancelada', 'color' => 'bg-danger', 'hex' => '#e7515a'],
        self::ESTADO_PENDIENTE => ['texto' => 'Pendiente', 'color' => 'bg-primary', 'hex' => '#4361ee'],
        self::ESTADO_EN_PROCESO => ['texto' => 'En proceso', 'color' => 'bg-warning', 'hex' => '#e2a03f'],
        self::ESTADO_FINALIZADA => ['texto' => 'Finalizada', 'color' => 'bg-success', 'hex' => '#00ab55'],
    ];

    /**
     * En que estados la orden esta cerrada y ya no admite datos nuevos.
     *
     * Finalizada y Cancelada se parecen en eso: el trabajo se acabo, o se
     * tiro la toalla, y en los dos casos la orden se cierra. Por eso se
     * pregunta por la lista y no por un estado suelto.
     */
    public const ESTADOS_CERRADOS = [
        self::ESTADO_CANCELADA,
        self::ESTADO_FINALIZADA,
    ];

    /**
     * Si la orden esta en un estado en el que ya no se admiten datos.
     */
    public function estaCerrada(): bool
    {
        return in_array(
            (int) $this->estado,
            self::ESTADOS_CERRADOS,
            true
        );
    }

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

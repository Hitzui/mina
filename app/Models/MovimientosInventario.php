<?php

namespace App\Models;

use App\Models\Base\MovimientosInventario as BaseMovimientosInventario;
use Illuminate\Validation\ValidationException;

class MovimientosInventario extends BaseMovimientosInventario
{
    /*
     * Los tipos de movimiento, con el signo que aportan al saldo.
     *
     * La columna "tipo" es un texto libre, y ahi es donde se cuelan los
     * errores: "entrada" y "Entrada" son dos cosas distintas para la base
     * y solo una suma stock. Se deja la columna como esta (es la que
     * tiene el modelo entregado y no se va a cambiar el esquema), pero
     * toda lectura del saldo pasa por estos metodos, que no distinguen
     * mayusculas, en vez de por un == sobre el texto.
     *
     * signo = +1 mete material en el almacen, -1 lo saca.
     */
    /*
     * La compra de la que viene el movimiento, si viene de una. La columna se
     * agrego despues con una migracion, asi que el modelo base, que se
     * genero cuando se creo la tabla, no la conoce.
     */
    public const COMPRA_ID = 'compra_id';

    public const TIPO_ENTRADA = 'entrada';
    public const TIPO_SALIDA = 'salida';
    public const TIPO_AJUSTE_POSITIVO = 'ajuste_positivo';
    public const TIPO_AJUSTE_NEGATIVO = 'ajuste_negativo';

    public const TIPOS = [
        self::TIPO_ENTRADA => ['texto' => 'Entrada', 'signo' => 1],
        self::TIPO_SALIDA => ['texto' => 'Salida', 'signo' => -1],
        self::TIPO_AJUSTE_POSITIVO => ['texto' => 'Ajuste a favor', 'signo' => 1],
        self::TIPO_AJUSTE_NEGATIVO => ['texto' => 'Ajuste en contra', 'signo' => -1],
    ];

    protected $fillable = [
        self::PRODUCTO_ID,
        self::ORDEN_TRABAJO_ID,
        self::PROCESO_ORDEN_ID,
        self::COMPRA_ID,
        self::TIPO,
        self::FECHA,
        self::CANTIDAD,
        self::MONEDA_ID,
        self::COSTO_UNITARIO,
        self::COSTO_TOTAL,
        self::REFERENCIA,
        self::OBSERVACIONES,
        self::COSTO_UNITARIO_NIO,
        self::COSTO_TOTAL_NIO
    ];

    /**
     * El producto que se movio.
     */
    public function producto()
    {
        return $this->belongsTo(Producto::class, Producto::ID);
    }

    /**
     * La orden a la que pertenece el movimiento, si lo tiene.
     */
    public function orden_trabajo()
    {
        return $this->belongsTo(OrdenesTrabajo::class, OrdenesTrabajo::ID);
    }

    /**
     * El proceso que consumio el material, si el consumo fue en un proceso.
     *
     * Es la diferencia entre "salio de almacen" y "se uso en la etapa de
     * triturado": solo el segundo es un costo del proceso.
     */
    public function proceso_orden()
    {
        return $this->belongsTo(ProcesosOrden::class, ProcesosOrden::ID);
    }

    public function moneda()
    {
        return $this->belongsTo(Moneda::class, Moneda::ID);
    }

    /**
     * Normaliza el tipo a uno del catalogo, sin distinguir mayusculas.
     *
     * Devuelve null si no es ninguno conocido, para que quien llame decida
     * que hacer en vez de tomar un texto raro como si fuera una salida.
     */
    public function tipoNormalizado(): ?string
    {
        $tipo = mb_strtolower(trim((string) $this->tipo));
        $tipo = str_replace(['-', ' '], '_', $tipo);

        /*
         * Primero los exactos. Este paso tiene que ir antes que el
         * reconocimiento por prefijos: si no, "ajuste_negativo" entra por
         * la rama de "ajuste" que busca las coletillas y, al no ser
         * positivo ni "ajuste" a secas, se cae y devuelve null, que es
         * justo el caso que si existe en el catalogo.
         */
        if (array_key_exists($tipo, self::TIPOS)) {
            return $tipo;
        }

        /*
         * "ajuste" a secas, y "entrada"/"salida" con coletilla, se llevan
         * al tipo mas proximo en vez de rechazar el movimiento. Un rechazo
         * aqui dejaria al usuario sin poder registrar un consumo porque el
         * tipo venia escrito de otra manera.
         */
        if (str_starts_with($tipo, 'ajuste')) {
            return str_contains($tipo, 'negativ')
                || str_contains($tipo, 'contra')
                    ? self::TIPO_AJUSTE_NEGATIVO
                    : self::TIPO_AJUSTE_POSITIVO;
        }

        if (str_starts_with($tipo, 'entrada')) {
            return self::TIPO_ENTRADA;
        }

        if (str_starts_with($tipo, 'salida') || str_starts_with($tipo, 'consumo')) {
            return self::TIPO_SALIDA;
        }

        return null;
    }

    /**
     * El signo que este movimiento aporta al saldo del almacen.
     *
     * +1 entra material, -1 sale. Un tipo desconocido devuelve 0, que no
     * mueve el saldo: es preferible ignorar un movimiento raro a soltar
     * existencias por confundir "Ajuste" con "Ajuste en contra".
     */
    public function signo(): int
    {
        $tipo = $this->tipoNormalizado();

        return $tipo === null ? 0 : self::TIPOS[$tipo]['signo'];
    }

    public function esEntrada(): bool
    {
        return $this->signo() > 0;
    }

    public function esSalida(): bool
    {
        return $this->signo() < 0;
    }

    public function nombreTipo(): string
    {
        $tipo = $this->tipoNormalizado();

        return $tipo === null
            ? (string) ($this->tipo ?: 'Sin tipo')
            : self::TIPOS[$tipo]['texto'];
    }

    /**
     * Los consumos de un proceso concreto.
     *
     * Lo que sale del almacen sin proceso (una venta, una muestra) no es
     * costo de nadie, y por eso no entra en el desglose de un proceso.
     */
    public function scopeDeProceso($query, int $procesoOrdenId)
    {
        return $query->where(self::PROCESO_ORDEN_ID, $procesoOrdenId);
    }

    /**
     * Cuanto sale de este material en NIO.
     *
     * Para las salidas tiene que preferirse el equivalente ya guardado y
     * solo caer al calculo cuando el movimiento es viejo y no lo tiene.
     * Es el mismo criterio que sigue el resto del sistema con los costos.
     */
    public function getImporteNioAttribute(): float
    {
        if ($this->costo_total_nio !== null) {
            return (float) $this->costo_total_nio;
        }

        if ($this->costo_unitario_nio !== null) {
            return round((float) $this->cantidad * (float) $this->costo_unitario_nio, 2);
        }

        return 0.0;
    }

    /**
     * Calcula el total y su equivalente en NIO.
     *
     * El total sale siempre de cantidad por costo unitario y nunca se
     * acepta del formulario, por la misma razon que en MovimientosCosto: si
     * se recibiera ya calculado, un valor equivocado se guardaria como si
     * fuera verdad.
     *
     * El costo unitario de una salida lo pone el inventario, no la
     * pantalla, asi que entra ya resuelto en el atributo. Esta funcion no
     * lo decide: solo multiplica.
     */
    public function calcularTotales(?float $tipoCambio): self
    {
        $this->costo_total = round(
            (float) $this->cantidad * (float) $this->costo_unitario,
            2
        );

        if ($tipoCambio !== null) {
            $this->costo_unitario_nio = round(
                (float) $this->costo_unitario * $tipoCambio,
                4
            );
            $this->costo_total_nio = round(
                $this->costo_total * $tipoCambio,
                2
            );
        } elseif ($this->esSalida()) {
            /*
             * Una salida de inventario siempre va en NIO: el costo unitario
             * sale del promedio del almacen, que ya esta en NIO. Si aqui no
             * se rellena, el costo del proceso se quedaria en cero sin que
             * nada lo avise, que es peor que un error visible.
             */
            $this->costo_unitario_nio = round((float) $this->costo_unitario, 4);
            $this->costo_total_nio = $this->costo_total;
        }

        return $this;
    }

    /**
     * El proceso del movimiento, si lo tiene, es de esta misma orden.
     *
     * Igual que en los costos: con las dos columnas en la fila, nada
     * impide guardar un proceso de una orden junto al id de otra, y el
     * consumo apareceria en un proceso que no lo gasto.
     *
     * @throws ValidationException
     */
    public function validarProcesoPertenece(): void
    {
        if ($this->proceso_orden_id === null) {
            return;
        }

        $proceso = ProcesosOrden::find($this->proceso_orden_id);

        if ($proceso === null) {
            throw ValidationException::withMessages([
                self::PROCESO_ORDEN_ID => 'El proceso seleccionado no existe.',
            ]);
        }

        if ($proceso->orden_trabajo_id === null) {
            throw ValidationException::withMessages([
                self::PROCESO_ORDEN_ID => 'El proceso seleccionado no pertenece a ninguna orden.',
            ]);
        }

        if ((int) $proceso->orden_trabajo_id !== (int) $this->orden_trabajo_id) {
            throw ValidationException::withMessages([
                self::PROCESO_ORDEN_ID => 'El proceso seleccionado pertenece a otra orden de trabajo.',
            ]);
        }
    }
}

<?php

namespace App\Models;

use App\Models\Base\Compra as BaseCompra;
use App\Models\Concerns\GeneraCodigo;

/**
 * Cabecera de una compra a un proveedor.
 *
 * Es la contraparte del almacen: una compra finalizada mete el material en el
 * kardex, y por eso el estado importa mas aqui que en ninguna otra tabla. Con
 * la compra en pendiente o en proceso no ha entrado nada; al finalizarla, cada
 * linea del detalle genera una entrada de almacen.
 *
 * Los totales no se aceptan del formulario: se suman de las lineas. La regla
 * del documento lo pide ("la suma de los detalles debe coincidir con los
 * totales de la cabecera") y, mas que eso, si el total se escribiera a mano
 * el almacen recibiria material por un importe que no es el de la linea, y el
 * costo promedio del inventario saldria descuadrado sin avisar.
 */
class Compra extends BaseCompra
{
    use GeneraCodigo;

    public const PREFIJO_CODIGO = 'CMP-';
    public const LARGO_NUMERO_CODIGO = 6;

    /*
     * Los mismos cuatro estados que la orden y el proceso, con la misma
     * numeracion, porque describen lo mismo. Lo que decide aqui es si el
     * material entra al almacen: solo al finalizar.
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

    protected $fillable = [
        self::CODIGO,
        self::PROVEEDOR_ID,
        self::FECHA,
        self::NUMERO_DOCUMENTO,
        self::SUBTOTAL,
        self::IMPUESTO,
        self::TOTAL,
        self::MONEDA_ID,
        self::ESTADO,
        self::OBSERVACIONES
    ];

    protected $casts = [
        self::ID => 'int',
        self::PROVEEDOR_ID => 'int',
        self::MONEDA_ID => 'int',
        self::ESTADO => 'int',
        self::FECHA => 'date',
        self::SUBTOTAL => 'float',
        self::IMPUESTO => 'float',
        self::TOTAL => 'float',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    /**
     * El proveedor al que se le compro.
     *
     * La clave foranea se nombra porque el nombre de la clase es
     * "Proveedore" y de ahi sale "proveedore_id", que no existe.
     */
    public function proveedor()
    {
        return $this->belongsTo(Proveedore::class, self::PROVEEDOR_ID);
    }

    public function moneda()
    {
        return $this->belongsTo(Moneda::class, self::MONEDA_ID);
    }

    /**
     * Las lineas de material de la compra.
     */
    public function detalles()
    {
        return $this->hasMany(DetalleCompra::class, DetalleCompra::COMPRA_ID);
    }

    /**
     * Los movimientos de almacen que genero esta compra.
     *
     * ConTrashed a proposito: una compra que se corrige deja movimientos
     * dados de baja, y hay que verlos para poder deshacer los que queden.
     */
    public function movimientos()
    {
        return $this->hasMany(MovimientosInventario::class, MovimientosInventario::COMPRA_ID)
            ->withTrashed();
    }

    /**
     * Si el material de esta compra ya entro al almacen.
     */
    public function esFinalizada(): bool
    {
        return (int) $this->estado === self::ESTADO_FINALIZADA;
    }

    public function estadoTexto(): string
    {
        return self::ESTADOS[(int) $this->estado]['texto'] ?? 'Pendiente';
    }

    public function estadoClase(): string
    {
        return self::ESTADOS[(int) $this->estado]['color'] ?? 'bg-primary';
    }

    public function estadoEtiqueta(): string
    {
        return sprintf(
            '<span class="badge %s">%s</span>',
            $this->estadoClase(),
            e($this->estadoTexto())
        );
    }

    /**
     * El total en NIO, con el tipo de cambio de la moneda y la fecha.
     *
     * Se calcula al leer y no se guarda, porque compras no tiene columnas de
     * equivalente. El tipo de cambio se centraliza en el mismo sitio que el
     * resto del sistema, para que una compra en dolares y un costo en dolares
     * no se conviertan con paridades distintas.
     */
    public function getTotalNioAttribute(): ?float
    {
        if ($this->moneda_id === null || $this->fecha === null) {
            return null;
        }

        $cambio = TiposCambio::vigentePara(
            (int) $this->moneda_id,
            $this->fecha->toDateString()
        );

        if ($cambio === null) {
            return null;
        }

        return round((float) $this->total * $cambio, 2);
    }

    /**
     * Suma los totales de las lineas y los deja en la cabecera.
     *
     * El impuesto y el total se calculan aqui, no vienen del formulario: el
     * subtotal es lo que dicen las lineas, y el impuesto se aplica sobre ese
     * subtotal con el porcentaje que se le pase.
     *
     * @param  float|null  $porcentajeImpuesto  El impuesto como porcentaje
     *                                           sobre el subtotal. Null o 0
     *                                           deja el impuesto en cero.
     */
    public function calcularTotales(?float $porcentajeImpuesto = null): self
    {
        $subtotal = (float) $this->detalles()->sum('subtotal');

        $impuesto = $porcentajeImpuesto !== null && $porcentajeImpuesto > 0
            ? round($subtotal * ($porcentajeImpuesto / 100), 2)
            : 0.0;

        $this->subtotal = round($subtotal, 2);
        $this->impuesto = $impuesto;
        $this->total = round($subtotal + $impuesto, 2);

        return $this;
    }
}

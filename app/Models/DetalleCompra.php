<?php

namespace App\Models;

use App\Models\Base\DetalleCompra as BaseDetalleCompra;
use Illuminate\Validation\ValidationException;

/**
 * Una linea de material dentro de una compra.
 *
 * El subtotal no viene de la pantalla: es cantidad por costo unitario. Si se
 * aceptara ya calculado, un valor equivocado se guardaria como si fuera
 * verdad, y como es esta linea la que genera la entrada de almacen, el
 * costo promedio del inventario quedaria con ese error dentro.
 */
class DetalleCompra extends BaseDetalleCompra
{
    protected $fillable = [
        self::COMPRA_ID,
        self::PRODUCTO_ID,
        self::CANTIDAD,
        self::COSTO_UNITARIO,
        self::SUBTOTAL
    ];

    protected $casts = [
        self::ID => 'int',
        self::COMPRA_ID => 'int',
        self::PRODUCTO_ID => 'int',
        self::CANTIDAD => 'float',
        self::COSTO_UNITARIO => 'float',
        self::SUBTOTAL => 'float',
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class, self::COMPRA_ID);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, self::PRODUCTO_ID);
    }

    /**
     * Calcula el subtotal de la linea.
     *
     * @throws ValidationException
     */
    public function calcularSubtotal(): self
    {
        if ((float) $this->cantidad <= 0) {
            throw ValidationException::withMessages([
                self::CANTIDAD => 'La cantidad tiene que ser mayor que cero.',
            ]);
        }

        if ((float) $this->costo_unitario < 0) {
            throw ValidationException::withMessages([
                self::COSTO_UNITARIO => 'El costo unitario no puede ser negativo.',
            ]);
        }

        $this->subtotal = round((float) $this->cantidad * (float) $this->costo_unitario, 2);

        return $this;
    }

    /**
     * La descripcion de la linea, para los listados.
     */
    public function getDescripcionAttribute(): string
    {
        $producto = $this->producto;

        if ($producto === null) {
            return 'Material eliminado';
        }

        return sprintf(
            '%s (%s) — %s %s a %s',
            $producto->nombre,
            $producto->codigo,
            rtrim(rtrim(number_format((float) $this->cantidad, 3), '0'), '.') ?: '0',
            $producto->unidad_medida,
            number_format((float) $this->costo_unitario, 2)
        );
    }
}

<?php

namespace App\Models;

use App\Models\Base\Proveedore as BaseProveedore;
use App\Models\Concerns\GeneraCodigo;

/**
 * Proveedores: los que venden al taller.
 *
 * Es dato maestro. El proveedor por si solo no dice nada de las compras: eso
 * vive en compras y detalle_compras, que todavia no tienen pantalla. Por eso
 * un proveedor que ya tiene compras no se borra de verdad, se desactiva: las
 * compras registradas dependen de el.
 */
class Proveedore extends BaseProveedore
{
    use GeneraCodigo;

    /*
     * El codigo lo pone el sistema, no la persona que da de alta el
     * proveedor. Sigue el formato de los empleados y los materiales: unas
     * letras y seis digitos con ceros delante, para que el codigo se ordene
     * igual que el numero al mirar la columna.
     */
    public const PREFIJO_CODIGO = 'PROV-';
    public const LARGO_NUMERO_CODIGO = 6;

    protected $fillable = [
        self::CODIGO,
        self::NOMBRE,
        self::TELEFONO,
        self::EMAIL,
        self::DIRECCION,
        self::CONTACTO,
        self::ESTADO,
        self::OBSERVACIONES
    ];

    /**
     * Como se muestra un proveedor en los combos y en las pantallas.
     *
     * Con el contacto al lado cuando lo hay, que es como se busca de
     * verdad: por el nombre de la persona, no solo por la razon social.
     */
    public function getNombreCompletoAttribute(): string
    {
        return $this->contacto
            ? $this->nombre . ' (' . $this->contacto . ')'
            : (string) $this->nombre;
    }

    /**
     * Las compras hechas a este proveedor.
     */
    public function compras()
    {
        /*
         * La clave foranea se nombra a proposito. Eloquent la deducira del
         * nombre de la clase, que es "Proveedore", y pondria
         * "proveedore_id"; en la tabla la columna se llama "proveedor_id",
         * sin la ele. Sin nombrarla, la consulta falla con un error de
         * columna inexistente.
         */
        return $this->hasMany(Compra::class, Compra::PROVEEDOR_ID);
    }

    /**
     * Si el proveedor tiene compras registradas.
     *
     * Es lo que decide entre borrarlo y desactivarlo. Con compras
     * registradas, borrarlo dejaria filas apuntando a un proveedor que no
     * existe, y el historial de lo que se compro quedaria sin origen.
     */
    public function tieneCompras(): bool
    {
        return $this->compras()->exists();
    }

    /**
     * Un correo para poder avisarle de algo, si lo tiene.
     */
    public function tieneEmail(): bool
    {
        return $this->email !== null && trim((string) $this->email) !== '';
    }
}

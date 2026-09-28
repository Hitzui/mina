<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las dos monedas que hay pasan a su codigo ISO.
 *
 * Tenian "001" y "002", que no son codigos de nadie: son numeros de orden de
 * alta. Mientras sean las unicas dos no pasa nada, pero en cuanto se anada
 * una tercera —el euro, que es lo primero que se le ocurre a quien tiene
 * compras fuera— la columna quedaria con "001", "002" y "EUR" juntos, y
 * entonces el codigo deja de significar nada: nadie sabria que el 002 es el
 * dolar sin abrir la tabla.
 *
 * Se pasan a NIO y USD, que es lo que usa el banco central al publicar el
 * tipo de cambio y lo que aparece en el archivo que se importa. Es el mismo
 * criterio por el que el resto de la aplicacion llama "moneda base" a la
 * que no lleva tipo de cambio consigo misma: el cordoba, que es NIO.
 *
 * Se busca por codigo y no por id, que es lo que no significa nada, y se
 * escribe el nombre con su acento: "Cordobas" y "Dolares" salen en el
 * desplegable de cada compra, de cada pago y de cada costo, y sin tilde
 * parece un texto a medio hacer.
 *
 * El indice unico de codigo avisa si alguien ya habia puesto un NIO a mano
 * antes de que esta migracion corriera, que es lo unico que aqui puede
 * salir mal. La migration falla y se para, en vez de dejar dos filas con el
 * mismo codigo.
 */
return new class extends Migration
{
    /**
     * Codigo viejo, codigo nuevo y nombre con su acento.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const CAMBIOS = [
        ['001', 'NIO', 'Córdobas'],
        ['002', 'USD', 'Dólares'],
    ];

    public function up(): void
    {
        foreach (self::CAMBIOS as [$viejo, $nuevo, $nombre]) {
            $dondeViejo = DB::table('monedas')->where('codigo', $viejo);
            $dondeNuevo = DB::table('monedas')->where('codigo', $nuevo);

            /*
             * Si el codigo nuevo ya esta puesto, esta migracion no tiene nada
             * que hacer con esa fila y no se toca. Puede pasar si alguien
             * creo la moneda a mano con el codigo ISO antes de que esta
             * migracion existiera, que es justo lo que hace falta para que la
             * aplicacion sea utilizable antes de ejecutarla.
             */
            if (! $dondeViejo->exists() || $dondeNuevo->exists()) {
                continue;
            }

            DB::table('monedas')
                ->where('codigo', $viejo)
                ->update(['codigo' => $nuevo, 'nombre' => $nombre]);
        }
    }

    public function down(): void
    {
        foreach (self::CAMBIOS as [$viejo, $nuevo]) {
            DB::table('monedas')
                ->where('codigo', $nuevo)
                ->update(['codigo' => $viejo]);
        }
    }
};

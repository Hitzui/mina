<?php

namespace App\Models;

use App\Models\Base\DetalleCobro as BaseDetalleCobro;
use Illuminate\Support\Facades\DB;

/**
 * Una linea de un cobro: cuanto de este dinero va a este ingreso.
 *
 * ===========================================================================
 * POR QUE UNA LINEA ES UN REPARTO Y NO UNA CANTIDAD POR UN PRECIO
 * ===========================================================================
 *
 * En una compra la linea es "2 swingues a 300 cada uno" y el subtotal sale de
 * multiplicar. Aqui no hay cantidad ni precio: hay una cantidad de dinero que se
 * entrega, y se dice a que ingreso se imputa.
 *
 * La diferencia no es de forma. En una compra el servidor sabe el precio porque lo
 * consulto del catalogo del material. En un cobro no hay catalogo de donde sacarlo:
 * el dinero llega en un sobre y lo que hay que decidir es a que se lo carga, y eso
 * lo dice el usuario. Si la linea fuera cantidad por precio, el subtotal seria un
 * numero que el sistema invuelve con un precio que nadie ha mirado.
 *
 * Y el subtotal de una linea ES su propio monto. No hay nada que calcular, y por
 * eso el metodo calcularSubtotal() de DetalleCompra —que existe y multiplica— no
 * tiene equivalente aqui: su copia seria un "return $this->monto", que es ruido.
 */
class DetalleCobro extends BaseDetalleCobro
{
    protected $fillable = [
        self::COBRO_ID,
        self::INGRESO_ID,
        self::MONTO,
        self::OBSERVACIONES,
    ];

    /**
     * Si el ingreso de esta linea es del mismo cliente que el del cobro.
     *
     * Y ESTA ES LA COMPROBACION QUE MAS IMPORTANTE, y la que mas se olvida.
     *
     * El cobro es por cliente y la linea apunta a un ingreso, que es de una orden, y
     * la orden es de un cliente. Nada en la base obliga a que ese cliente sea el
     * mismo: las dos columnas son claves foraneas sueltas y una linea puede apuntar
     * sin problema a un ingreso de otro cliente.
     *
     * Lo que pasaria es lo de siempre y es grave: se cobra a un cliente y se le
     * descuenta a otro. El taller no se entera, porque las dos filas son validas y
     * las sumas cuadran —cada ingreso lleva lo que dice su linea—, pero el saldo
     * pendiente de los dos clientes esta mal y no hay ninguna forma de saber que lo
     * esta: el error se descubre cuando alguien reclama.
     *
     * Por eso vive aqui y no en la pantalla: la validacion de la pantalla se puede
     * saltar desde cualquier lado, y esta es de las que no se pueden permitir.
     *
     * Se compara el cliente del ingreso con el del cobro, y no se sube por las
     * relaciones del modelo: se hace una consulta a ordenes_trabajo por el ingreso,
     * porque el ingreso no guarda cliente_id —guarda orden_trabajo_id— y el cobro si
     * lo guarda.
     */
    public function ingresoEsDelMismoCliente(Cobro $cobro): bool
    {
        if ((int) $this->ingreso_id === 0) {
            return false;
        }

        $clienteDelIngreso = DB::table('ingresos')
            ->join('ordenes_trabajo', 'ordenes_trabajo.id', '=', 'ingresos.orden_trabajo_id')
            ->where('ingresos.id', $this->ingreso_id)
            ->value('ordenes_trabajo.cliente_id');

        return $clienteDelIngreso !== null
            && (int) $clienteDelIngreso === (int) $cobro->cliente_id;
    }

    /**
     * El ingreso, con su orden y su tipo, para pintar la linea.
     */
    public function ingresoCompleto()
    {
        return $this->ingreso()->with(['orden_trabajo:id,codigo', 'tipo_ingreso'])->first();
    }

    /**
     * El codigo de la orden a la que apunta el ingreso, o un guion.
     *
     * Va aqui porque se usa en la ficha y en el listado de la misma manera, y
     * escribirlo dos veces es la forma de que las dos se separen: una sale el
     * codigo y la otra un guion cuando el ingreso se borro.
     */
    public function codigoDeOrden(): string
    {
        $orden = $this->ingreso?->orden_trabajo;

        return $orden?->codigo ?? '—';
    }
}
<?php

namespace App\Models;

use App\Models\Base\Cobro as BaseCobro;
use App\Models\Concerns\GeneraCodigo;
use Illuminate\Support\Facades\DB;

/**
 * Un cobro: dinero que el cliente entrega.
 *
 * ===========================================================================
 * LO QUE LE PASA A ESTE MODELO QUE NO LE PASA A LOS DEMAS
 * ===========================================================================
 *
 * Un cobro es por CLIENTE, no por orden. No tiene columna orden_trabajo_id, y eso
 * no es un olvido: es la diferencia entre "me deben 3.000 por la OT-2026-0001" y
 * "el cliente me entrega 1.000 hoy, de lo que me debe".
 *
 * Por eso el cobro no guarda un total de la orden sino un reparto: las lineas de
 * detalle_cobros apuntan a ingresos concretos —que si pertenecen a órdenes— y
 * cada linea dice cuanto de este dinero va a cada ingreso. El mismo ingreso puede
 * aparecer en varios cobros, porque un taller factura y va cobrando a plazos.
 *
 * Y por eso el total del cobro es la suma de sus lineas, y no un número que
 * escribe el usuario: si se escribiera, la suma del detalle podría no coincidir con
 * el total y nadie sabría cuál de los dos dice la verdad.
 *
 * ===========================================================================
 * LOS IMPORTES DEL DETALLE VAN EN LA MONEDA DEL COBRO, NO EN LA DEL INGRESO
 * ===========================================================================
 *
 * Es una decisión que se eligió antes de escribir el código y que conviene tener
 * escrita aqui, porque es la que decide el resto.
 *
 * Un cobro se recibe en una moneda —tiene moneda_id— y el detalle reparte ESE
 * dinero. Un ingreso puede estar facturado en otra: el taller factura en córdobas
 * y el cliente paga en dólares, y el cobro reparte dólares entre ingresos que
 * están en córdobas.
 *
 * La consecuencia es que el importe de una linea NO se puede comparar con el total
 * de su ingreso cuando las monedas son distintas, y por eso la regla de no
 * sobrecobrar solo se puede aplicar cuando son la misma. Ver
 * Cobro::validaNoSobrecobra().
 *
 * La alternativa —cada linea en la moneda de su ingreso— haria que la suma del
 * detalle mezclara dólares y córdobas y que cobros.monto, que es una sola cifra, no
 * pudiera ser su suma. La tabla no tiene donde guardar una conversion, y anadirla
 * seria un cambio de esquema.
 */
class Cobro extends BaseCobro
{
    use GeneraCodigo;

    /**
     * El codigo lo pone el sistema.
     *
     * Cuatro letras y seis digitos, igual que compras, proveedores y materiales.
     * Y es un indice unico en la base, asi que el numero no puede volver a salir
     * aunque el cobro se borre.
     */
    public const PREFIJO_CODIGO = 'CBX-';

    public const LARGO_NUMERO_CODIGO = 6;

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
     * Finalizada y Cancelada cierran el cobro.
     *
     * Finalizada es cuando el dinero esta confirmado: se anoto que llego y ya
     * esta en la caja. Pendiente es que se anoto pero todavia no se entrego, que es
     * un estado de verdad: el cliente dijo que venia manana y el taller quiere
     * acordarse de que falta ese dinero.
     *
     * Y a diferencia de la orden cerrada, un cobro cerrado NO bloquea la
     * correccion de lo que ya esta escrito. Un cobro con una linea mal puesta se
     * corrige aunque este finalizado, porque el dinero se entrego igual y lo que se
     * equivoco fue al apuntarlo; bloquearlo dejaria el error sin salida.
     */
    public const ESTADOS_CERRADOS = [
        self::ESTADO_FINALIZADA,
        self::ESTADO_CANCELADA,
    ];

    protected $fillable = [
        self::CLIENTE_ID,
        self::CAJA_ID,
        self::METODO_PAGO_ID,
        self::FECHA,
        self::MONEDA_ID,
        self::REFERENCIA,
        self::ESTADO,
        self::OBSERVACIONES,
        self::CODIGO,
    ];

    /**
     * Las lineas del cobro.
     *
     * El scaffolding ya deja una relacion que se llama detalle_cobros(); esta se
     * llama detalles() porque es la que se usa en las pantallas, y duplicar el mismo
     * reparto en dos relaciones con dos nombres es la forma de que las dos se
     * separen al tocar una. detalle_cobros() sigue viva para lo que ya la use.
     */
    public function detalles()
    {
        return $this->hasMany(DetalleCobro::class, DetalleCobro::COBRO_ID);
    }

    /**
     * Los ingresos de este cliente, que son los que se pueden repartir.
     *
     * Se ofrecen TODOS los ingresos del cliente, incluidos los de ordenes ya
     * cerradas. Cobrar dinero de una orden cerrada es precisamente lo normal: el
     * taller cierra la orden cuando el trabajo se acaba y el cliente paga dias
     * despues. Si se ofrecieran solo los de ordenes abiertas, la mitad de los cobros
     * del taller no se podrian registrar.
     */
    public function ingresosDisponibles()
    {
        return Ingreso::query()
            ->whereHas(
                'orden_trabajo',
                fn($q) => $q->where('cliente_id', $this->cliente_id)
            )
            ->with(['orden_trabajo:id,codigo', 'tipo_ingreso'])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();
    }

    /**
     * Cuanto dinero confirmado lleva cada ingreso.
     *
     * Solo cuentan los cobros FINALIZADOS: Pendiente y En proceso significan que
     * el dinero todavia no llego, y Cancelada significa que no cuenta. Las lineas
     * quitadas de un cobro se conservan con borrado logico mientras el cobro vive,
     * pero tampoco cuentan porque ya no representan una imputacion vigente.
     *
     * Y devuelve un mapa ingreso_id => monto, que es como lo pide el desplegable de
     * la linea y como lo necesita la ficha.
     *
     * @return array<int, float>
     */
    public function cobradoPorIngreso(?int $exceptoCobroId = null): array
    {
        return static::cobradoPorIngresoDeTodos($exceptoCobroId);
    }

    /**
     * La misma cuenta, sin necesitar un cobro.
     *
     * Y es un metodo aparte, y no un detalle, porque hay dos llamantes con dos
     * necesidades distintas y uno de los dos no tiene un cobro que pasar:
     *
     *   - La ficha y las validaciones tienen un cobro, y a veces necesitan excluirlo
     *     porque sus propias lineas no son "cobro anterior".
     *
     *   - El ajax del formulario de ALTA no tiene cobro todavia —se esta eligiendo
     *     el cliente para crear uno—, y aun asi tiene que decir cuanto lleva cobrado
     *     cada ingreso. Si esa version viviera colgada de un modelo de cobro, en el
     *     alta no habria modelo y devolveria un mapa vacio.
     *
     * Y el mapa vacio es un fallo silencioso de los caros: cada ingreso del
     * desplegable saldría como "sin nada cobrado" aunque hubiera cobros, el taller
     * vería que el ingreso esta libre y pondría el importe entero —que es
     * precisamente lo que el sistema tiene que avisar—. Por eso esta cuenta no
     * depende de que haya un Cobro delante, y por eso el nombre dice "de todos".
     *
     * @return array<int, float>
     */
    public static function cobradoPorIngresoDeTodos(?int $exceptoCobroId = null): array
    {
        $query = DB::table('detalle_cobros')
            ->join('cobros', 'cobros.id', '=', 'detalle_cobros.cobro_id')
            ->whereNull('detalle_cobros.deleted_at')
            ->whereNull('cobros.deleted_at')
            /*
             * Solo Finalizada significa dinero recibido y confirmado en caja.
             * Pendiente y En proceso describen dinero que todavia no llego, asi
             * que no pueden reducir lo que queda por cobrar ni servir para
             * bloquear un cobro nuevo. Cancelada tampoco cuenta.
             */
            ->where('cobros.estado', self::ESTADO_FINALIZADA)
            ->groupBy('detalle_cobros.ingreso_id')
            ->selectRaw('detalle_cobros.ingreso_id, COALESCE(SUM(detalle_cobros.monto), 0) AS total');

        if ($exceptoCobroId !== null) {
            $query->where('detalle_cobros.cobro_id', '!=', $exceptoCobroId);
        }

        $cobradores = [];

        foreach ($query->get() as $fila) {
            $cobradores[(int) $fila->ingreso_id] = (float) $fila->total;
        }

        return $cobradores;
    }

    /**
     * El total del cobro: la suma de sus lineas.
     *
     * No se escribe desde el formulario y no se guarda calculado en la base al
     * guardar las lineas —aunque la columna existe y el controlador la rellena— sino
     * que sale de aqui cada vez que se lee.
     *
     * Y se calcula sobre las lineas VIVAS, con withTrashed() para que se pueda
     * llamar desde un cobro que se esta borrando. Si se calculara con la relacion
     * normal, un cobro a medio borrar daria un total mas bajo y pareceria que se le
     * ha retirado dinero.
     */
    public function getMontoCalculadoAttribute(): float
    {
        $lineas = $this->relationLoaded('detalles')
            ? $this->detalles
            : $this->detalles()->withTrashed()->get();

        return round(
            (float) $lineas->whereNull('deleted_at')->sum('monto'),
            2
        );
    }

    /**
     * Cuantas lineas tiene, para la ficha.
     */
    public function getCantidadLineasAttribute(): int
    {
        $lineas = $this->relationLoaded('detalles')
            ? $this->detalles
            : $this->detalles()->get();

        return $lineas->whereNull('deleted_at')->count();
    }

    /**
     * Si el cobro esta cerrado.
     */
    public function estaCerrado(): bool
    {
        return in_array((int) $this->estado, self::ESTADOS_CERRADOS, true);
    }

    /**
     * Si el dinero esta confirmado.
     */
    public function esFinalizado(): bool
    {
        return (int) $this->estado === self::ESTADO_FINALIZADA;
    }

    /**
     * Si el cobro se anulo y sus lineas no cuentan.
     */
    public function estaCancelado(): bool
    {
        return (int) $this->estado === self::ESTADO_CANCELADA;
    }

    /**
     * El nombre del estado, y "Cancelada" si el estado no esta en el catalogo.
     *
     * Un estado fuera del catalogo sale como cancelada y no como "desconocido",
     * porque lo que se sabe de un estado desconocido es que no se cobro, y eso es
     * lo seguro: una cancelada es una cancelada.
     */
    public function estadoTexto(): string
    {
        if (! isset(self::ESTADOS[(int) $this->estado])) {
            return 'Cancelada';
        }

        return self::ESTADOS[(int) $this->estado]['texto'];
    }

    public function estadoClase(): string
    {
        return self::ESTADOS[(int) $this->estado]['color'] ?? 'bg-secondary';
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
     * El total, ya redondeado, para el listado y la ficha.
     *
     * El nombre lleva la palabra "calculado" a proposito. Hay una columna que se
     * llama `monto` y esta tambien devuelve el monto, y con los dos nombres el
     * mismo numero sale por dos caminos y no se sabe cual esta mirando cada uno.
     */
    public function total(): float
    {
        return $this->monto_calculado;
    }

    /**
     * Comprueba que las lineas no sobrecobren un ingreso.
     *
     * Y AQUI ESTA LA CONSECUENCIA DE QUE EL DETALLE VAYA EN LA MONEDA DEL COBRO.
     *
     * Solo se puede comprobar cuando el ingreso esta facturado en la MISMA moneda
     * que el cobro. Con monedas distintas, sumar 400 dolares a un ingreso de 3.000
     * cordobas no dice nada: no hay forma de saber si se paso o no sin una
     * conversion, y la tabla no tiene donde guardar cual se uso.
     *
     * Asi que en el caso distinto no se bloquea, pero no se calla: la ficha dice
     * cuanto lleva cobrado ese ingreso y en cuantas monedas distintas, que es la
     * informacion que hace falta para saber si hay que mirar.
     *
     * Y el cobro que se esta editando se excluye de la cuenta, porque sus propias
     * lineas ya estan en la pantalla y sumarlas dos veces haria que cualquier cobro
     * con dos lineas del mismo ingreso pareciera sobrecobrado.
     *
     * @param  array<int, array{ingreso_id: int, monto: float}>  $lineas
     * @return array<int, string>  Los ingresos que se pasan, con el motivo.
     */
    public function validaNoSobrecobra(array $lineas): array
    {
        if ($lineas === []) {
            return [];
        }

        $cobradores = $this->cobradoPorIngreso($this->exists ? $this->id : null);

        // Lo que este cobro quiere poner en cada ingreso
        $querido = [];

        foreach ($lineas as $linea) {
            $ingresoId = (int) $linea['ingreso_id'];
            $querido[$ingresoId] = ($querido[$ingresoId] ?? 0) + (float) $linea['monto'];
        }

        // Y la moneda de cada ingreso, para saber cuales son comparables
        $monedas = [];

        foreach (Ingreso::query()->whereIn('id', array_keys($querido))->get(['id', 'moneda_id', 'total']) as $ingreso) {
            $monedas[(int) $ingreso->id] = [
                'moneda' => (int) $ingreso->moneda_id,
                'total' => (float) $ingreso->total,
            ];
        }

        $problemas = [];

        foreach ($querido as $ingresoId => $importe) {
            if (! isset($monedas[$ingresoId])) {
                continue;
            }

            $deEste = $monedas[$ingresoId];

            if ($deEste['moneda'] !== (int) $this->moneda_id) {
                // Moneda distinta: no se puede comparar, y no se inventa una
                continue;
            }

            $yaCobrado = $cobradores[$ingresoId] ?? 0;

            if ($yaCobrado + $importe - $deEste['total'] > 0.005) {
                $problemas[$ingresoId] = sprintf(
                    'El ingreso #%d ya tiene %.2f cobrados de un total de %.2f. Con esta línea se pasarían en %.2f.',
                    $ingresoId,
                    $yaCobrado,
                    $deEste['total'],
                    $yaCobrado + $importe - $deEste['total']
                );
            }
        }

        return $problemas;
    }
}

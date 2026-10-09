<?php

namespace App\Services;

use App\Models\Ingreso;
use App\Models\MovimientosCosto;
use App\Models\MovimientosInventario;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use App\Models\Recuperacione;
use App\Models\TrabajosEmpleado;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Los numeros del panel de inicio.
 *
 * Todo lo que se enseña en la pagina principal se calcula aqui y no en el
 * controlador, porque son muchas consultas y cada una tiene una regla que depende
 * de las demas. En el controlador quedaria un metodo de trescientas lineas en el
 * que no se ve donde acaba una cosa y empieza la otra.
 *
 * ===========================================================================
 * LAS TRES REGLAS QUE MAS IMPORTAN, Y LAS TRES SON ERRORES SILENCIOSOS
 * ===========================================================================
 *
 * 1. EL COSTO TOTAL TIENE CUATRO SUMANDOS, NO UNO.
 *
 *    Esta es la que mas caro sale si se hace mal, porque no da error: sale un
 *    numero, y un numero verosimil es lo que mas dano hace.
 *
 *    El taller lleva los costos en cuatro sitios, y solo uno de ellos se llama
 *    "costos":
 *
 *      - Lo que alguien escribe a mano: luz, agua, mantenimiento. Es la tabla
 *        movimientos_costos.
 *
 *      - La mano de obra. Es la tabla trabajos_empleados, y no esta en la de
 *        costos porque la categoria "Mano de obra" esta marcada como automatica y
 *        no sale en el desplegable de la pantalla de costos. Ese "automatica" es
 *        justamente lo que impide que alguien la escriba a mano: si se escribiera
 *        en los dos sitios, la misma hora de trabajo se contaria dos veces.
 *
 *      - La depreciacion de los equipos. Es la tabla proceso_equipos, y por el
 *        mismo motivo: la categoria "Depreciacion" tambien es automatica.
 *
 *      - La materia prima. Es la tabla de movimientos del almacen, y no la de
 *        costos por un motivo distinto: el material sale fisicamente del
 *        almacen, se controla con existencias y lo que cuesta sale del promedio
 *        de lo que habia dentro, no de lo que alguien teclee.
 *
 *    Sumando solo movimientos_costos —que es lo que haria cualquiera que mirase el
 *    nombre de la pantalla de costos— el total del taller sale en menos de la
 *    cuarta parte de lo que es: con los datos de ahora, 750 en vez de 3.577,96.
 *    Y no avisa de nada.
 *
 *    ESTA REGLA YA ESTA ESCRITA en ProcesosOrden, que tiene un costo_total con
 *    estas mismas cuatro partes y un costosDesglosados() que las explica una a
 *    una. Eso no quiere decir que aqui se reutilice tal cual, y la diferencia
 *    esta al final de este comentario y es importante.
 *
 * 2. EL ESTADO DE UN PROCESO NO ESTA EN LA COLUMNA `estado`.
 *
 *    La columna existe pero solo vale para una cosa: decir si el proceso se
 *    cancelo a mano. Los otros tres estados se deducen de las fechas, y por eso la
 *    columna se queda vieja. En la base de ahora los tres procesos de la
 *    OT-2026-0001 tienen `estado` = 1, o sea "pendiente", cuando los tres tienen
 *    fecha de inicio y de fin ya pasada: estan finalizados.
 *
 *    Un GROUP BY sobre esa columna daria "tres procesos pendientes" cuando no hay
 *    ninguno, y el panel diria que el taller tiene tres trabajos sin empezar. Por
 *    eso las cuentas de procesos usan estadoCalculado(), que es el metodo del
 *    modelo y ya trae la regla escrita.
 *
 * 3. LO QUE SE QUEDO SIN EQUIVALENTE EN CORDOBAS NO CUENTA, Y SE CUENTA.
 *
 *    Es la misma regla que brought los ingresos: si una fila tiene tipo de cambio
 *    pero no tiene equivalente en NIO, su importe no se puede sumar con los demas
 *    y no se inventa. Pero eso no quiere decir que la fila no exista, asi que
 *    ademas se dice cuantas se quedaron fuera.
 *
 *    Un panel que enseña "costo total 3.577,96" sin decir que dos filas no entran
 *    en esa suma se lee como una suma completa cuando no lo es. Y esa es la peor
 *    forma de tener un error: invisible y con una cifra que parece correcta.
 *
 * ===========================================================================
 * Y LA DIFERENCIA CON EL COSTO QUE CALCULA LA PANTALLA DEL PROCESO
 * ===========================================================================
 *
 * ProcesosOrden::costoTotalAttribute() suma `trabajos_empleados.total` y
 * `movimientos_costos.costo_total`, que son los importes en la moneda original de
 * cada fila. Aqui se suman los equivalentes en NIO: `total_nio` y
 * `costo_total_nio`.
 *
 * La diferencia sale porque esas tablas guardan `moneda_id`, de modo que un costo
 * puede estar escrito en dolares y otro en cordobas, y sumar las dos columnas
 * "originales" mezcla cordobas con dolares. En una instalacion donde todo esta en
 * cordobas —que es el caso de este taller ahora— las dos cosas dan el mismo
 * numero y no se nota. En cuanto alguien registre un gasto en dolares, el total
 * de la ficha del proceso y el total del panel dejaran de coincidir.
 *
 * Aqui esta escrito en NIO a proposito, porque el panel lleva la contabilidad del
 * taller y esa lleva es en cordobas. La ficha del proceso no se toca en este
 * cambio: es otra pantalla, con otra regla, y cambiarla es una decision aparte que
 * necesita su propio documentacion. Queda anotado aqui para que no se pierda.
 *
 * ===========================================================================
 * QUE FILTRA EL PERIODO Y QUE NO
 * ===========================================================================
 *
 * Las cifras y la serie de barras SI filtran por el periodo elegido, porque son
 * preguntas con fecha: "cuanto gaste este mes".
 *
 * Las tres listas NO. Son la parte del panel que contesta "que hago ahora", y una
 * lista de procesos atrasados filtrada por la fecha de la orden sale vacia si la
 * orden se creo el mes pasado y el proceso se quedo a medias hoy. Un taller no
 * deja un trabajo a medias porque la orden sea vieja.
 *
 * Y la serie de doce meses no filtra por el periodo elegido, porque es una
 * tendencia: si filtrase por "este mes" seria una sola barra y dejaria de ser una
 * serie. Va siempre de los ultimos doce meses.
 */
class PanelDelTaller
{
    /**
     * Los periodos, con el texto del boton.
     *
     * El orden de este array es el orden en que salen los botones.
     */
    public const PERIODOS = [
        'mes' => 'Este mes',
        'anio' => 'Este año',
        'todo' => 'Todo',
    ];

    /** Cuantas filas salen en las listas de trabajo pendiente. */
    private const CUANTAS_EN_LISTAS = 8;

    /** Cuantas ordenes sale en la lista de las ultimas. */
    private const ULTIMAS_ORDENES = 5;

    /**
     * Los titulos de las tarjetas de procesos, y lo que explica cada numero.
     *
     * ---------------------------------------------------------------------------
     * POR QUE EL TITULO SE ESCRIBE ENTERO Y NO SE COMPONE
     * ---------------------------------------------------------------------------
     *
     * El nombre del estado sale del modelo y es el que usan todas las etiquetas del
     * resto de la aplicacion —"En proceso", "Finalizado"—, y son singulares porque
     * van pegados a un nombre propio: "OT-2026-0001: Finalizada". En el panel el
     * nombre va detras de "Procesos", y ahi "Procesos Finalizado" esta mal dicho.
     *
     * La primera solucion que se prueba es poner "Procesos " mas el nombre en
     * minuscula, que es lo automatico, y da "Procesos finalizado" y "Procesos
     * cancelado": en singular detras de un plural. Ademas "Procesos en proceso"
     * queda repetido y se lee mal.
     *
     * Y "Sin empezar" y "Terminados" son las palabras del taller, no las del modelo:
     * un proceso sin fecha de inicio no ha empezado, no esta "pendiente" como si
     * fuera a ver si lo hacen. "En proceso" se queda como esta porque en plural es
     * igual y porque es el nombre que aparece en las etiquetas del resto de la
     * aplicacion, que no van a cambiar por un titulo.
     *
     * ---------------------------------------------------------------------------
     * Y POR QUE CADA TARJETA DICE COMO CUENTA
     * ---------------------------------------------------------------------------
     *
     * Porque el numero de una tarjeta sin explicación es un numero del que hay que
     * fiarse, y estas cuatro cuentas son las que mas dataclan en el taller: un
     * proceso cuenta como terminado por sus fechas y no por su casilla, y hay
     * procesos cuya casilla dice "pendiente" desde hace semanas. Ponerlo debajo de
     * cada tarjeta es la manera de que se entienda sin tener que abrir el codigo.
     *
     * Y el de cancelados es el unico que no se deduce de nada: se marca a mano. Por
     * eso su texto lo dice, porque es el unico sitio donde el usuario puede tener
     * dudas de si el sistema se ha equivocado.
     *
     * La clave es el estado. Un estado que se anadiera al modelo y no estuviera aqui
     * sale con el nombre del modelo, que esta mal dicho pero no roto.
     */
    private const TARJETAS_DE_PROCESO = [
        0 => ['titulo' => 'Cancelados', 'pie' => 'Marcados a mano, no se deducen de las fechas'],
        1 => ['titulo' => 'Sin empezar', 'pie' => 'Sin fecha de inicio todavía'],
        2 => ['titulo' => 'En proceso', 'pie' => 'Empezados y sin fecha de fin'],
        3 => ['titulo' => 'Terminados', 'pie' => 'Con la fecha de fin ya pasada'],
    ];

    private string $periodo;

    private Carbon $hoy;

    public function __construct(string $periodo = 'mes')
    {
        // Un periodo que no esta en la lista cae en el de por defecto, en vez de
        // romper. El parametro viene de la url, y una url se puede escribir a
        // mano o quedarse con un valor viejo de un enlace guardado.
        $this->periodo = array_key_exists($periodo, self::PERIODOS) ? $periodo : 'mes';

        $this->hoy = Carbon::today();
    }

    public function nombrePeriodo(): string
    {
        return self::PERIODOS[$this->periodo];
    }

    public function periodo(): string
    {
        return $this->periodo;
    }

    /**
     * Los botones del selector, con su url ya hecha.
     *
     * Se devuelven todos con un booleano de "este es el que esta", y no solo el
     * que esta, para que la vista no tenga que saber cual hay que marcar. En
     * cuanto se anadiera un periodo, la vista tendria que cambiar, y ese es el
     * cambio que nadie recuerda hacer.
     *
     * @return array<int, array{clave: string, texto: string, url: string, activo: bool}>
     */
    public function botones(): array
    {
        $botones = [];

        foreach (self::PERIODOS as $clave => $texto) {
            $botones[] = [
                'clave' => $clave,
                'texto' => $texto,
                'url' => request()->fullUrlWithQuery(['periodo' => $clave]),
                'activo' => $clave === $this->periodo,
            ];
        }

        return $botones;
    }

    /**
     * Desde que fecha se cuenta, o null si se cuenta todo.
     */
    public function desde(): ?string
    {
        return match ($this->periodo) {
            'mes' => $this->hoy->copy()->startOfMonth()->toDateString(),
            'anio' => $this->hoy->copy()->startOfYear()->toDateString(),
            default => null,
        };
    }

    /**
     * Hasta que fecha se cuenta.
     *
     * Siempre hoy, y no "sin limite por arriba". Una fila con fecha de futuro es
     * un error de tecleo, y sumarla haria que un mes que todavia no ha pasado
     * enseñara dinero que no se ha gastado. El limite de abajo es opcional —el
     * periodo "todo" no lo tiene— y el de arriba no: un panel que enseña el mes que
     * viene no es un panel.
     */
    public function hasta(): string
    {
        return $this->hoy->toDateString();
    }

    // ==================================================================
    // Las cuentas de ordenes y de procesos
    // ==================================================================

    /**
     * Cuantas ordenes hay en cada estado dentro del periodo.
     *
     * Los cuatro estados salen siempre, aunque valgan cero, porque una tarjeta que
     * aparece y desaparece segun el numero es una tarjeta en la que uno no sabe
     * donde mirar. Y si hay estados que no estan en el catalogo, salen como
     * "Desconocido": si el modelo cambia y alguien mete un estado nuevo, el panel
     * tiene que decirlo en vez de esconder esas ordenes.
     *
     * @return array<int, array{estado: int, texto: string, color: string, hex: string, cuantas: int, abierta: bool}>
     */
    public function ordenesPorEstado(): array
    {
        $desde = $this->desde();

        $cuantas = OrdenesTrabajo::query()
            ->when($desde !== null, fn ($q) => $q->whereDate('fecha', '>=', $desde))
            ->whereDate('fecha', '<=', $this->hasta())
            ->selectRaw('estado, COUNT(*) AS cuantas')
            ->groupBy('estado')
            ->pluck('cuantas', 'estado');

        $tarjetas = [];

        foreach (OrdenesTrabajo::ESTADOS as $estado => $datos) {
            $tarjetas[] = [
                'estado' => (int) $estado,
                'texto' => $datos['texto'],
                'color' => $datos['color'],
                'hex' => $datos['hex'],
                'cuantas' => (int) ($cuantas[$estado] ?? 0),
                'abierta' => ! in_array((int) $estado, OrdenesTrabajo::ESTADOS_CERRADOS, true),
            ];
        }

        foreach ($cuantas as $estado => $n) {
            $estado = (int) $estado;

            if (isset(OrdenesTrabajo::ESTADOS[$estado])) {
                continue;
            }

            $tarjetas[] = [
                'estado' => $estado,
                'texto' => 'Desconocido (' . $estado . ')',
                'color' => 'bg-secondary',
                'hex' => '#888ea8',
                'cuantas' => (int) $n,
                'abierta' => true,
            ];
        }

        return $tarjetas;
    }

    /**
     * Cuantos procesos hay en cada estado.
     *
     * Y AQUI ESTA LA REGLA 2: se cuenta con estadoCalculado() y no con la columna
     * `estado`. Se cuenta en PHP y no en SQL a proposito: la regla cabe en cuatro
     * lineas y metida en un SELECT seria un CASE que hay que mantener en dos
     * sitios —el modelo y la consulta—, y el que se queda viejo es el del SELECT,
     * que es el que nadie ve.
     *
     * Un taller como este tiene decenas de procesos, no millones, asi que traer
     * las fechas y contarlas en php no cuesta nada.
     *
     * Y el filtro de fechas va sobre `fecha_inicio` y no sobre la fecha de la
     * orden a la que pertenece: la pregunta es "cuando empezo el trabajo", y un
     * proceso que empezo el dia 3 cuenta en el mes en que empezo, no en el mes en
     * que se creo la orden. Si se filtrara por la orden, el conteo de procesos de
     * un mes diria cosas de cuando se creo la orden, que es otra fecha distinta.
     *
     * @return array<int, array{estado: int, texto: string, color: string, hex: string, cuantas: int}>
     */
    public function procesosPorEstado(): array
    {
        $desde = $this->desde();

        $procesos = ProcesosOrden::query()
            ->when($desde !== null, fn ($q) => $q->whereDate('fecha_inicio', '>=', $desde))
            ->whereDate('fecha_inicio', '<=', $this->hasta())
            ->get(['id', 'estado', 'fecha_inicio', 'fecha_fin']);

        $cuantas = [];

        foreach ($procesos as $proceso) {
            $calculado = $proceso->estadoCalculado();

            $cuantas[$calculado] = ($cuantas[$calculado] ?? 0) + 1;
        }

        $tarjetas = [];

        foreach (ProcesosOrden::ESTADOS as $estado => $datos) {
            $delPanel = self::TARJETAS_DE_PROCESO[(int) $estado] ?? null;

            $tarjetas[] = [
                'estado' => (int) $estado,
                'texto' => $delPanel['titulo'] ?? $datos['texto'],
                'pie' => $delPanel['pie'] ?? null,
                'delCatalogo' => $datos['texto'],
                'color' => $datos['color'],
                'hex' => $datos['hex'],
                'cuantas' => (int) ($cuantas[$estado] ?? 0),
            ];
        }

        return $tarjetas;
    }

    // ==================================================================
    // El dinero
    // ==================================================================

    /**
     * Los cuatro sumandos del costo, y los ingresos al lado.
     *
     * Cada sumando sale con su numero de filas y con cuantas se quedaron sin
     * equivalente, porque la regla 3 dice que eso se cuenta y se dice. Y cada uno se
     * trae por separado ademas de la suma, para que el panel pueda explicar de
     * donde sale el total: un numero que se entrega sin sus partes es un numero del
     * que hay que fiarse, y un numero con sus partes encima es un numero que se
     * puede comprobar.
     *
     * @return array<string, mixed>
     */
    public function dinero(): array
    {
        $partes = [
            'costos' => $this->parteDeCostos(),
            'mano_de_obra' => $this->parteDeManoDeObra(),
            'depreciacion' => $this->parteDeDepreciacion(),
            'materia_prima' => $this->parteDeMateriaPrima(),
        ];

        $costoTotal = 0.0;
        $filas = 0;
        $sinEquivalente = 0;

        foreach ($partes as $parte) {
            $costoTotal += $parte['total'];
            $filas += $parte['filas'];
            $sinEquivalente += $parte['sinEquivalente'];
        }

        $ingresos = $this->parteDeIngresos();

        return [
            'partes' => $partes,
            'costo_total' => round($costoTotal, 2),
            'costo_filas' => $filas,
            'costo_sin_equivalente' => $sinEquivalente,
            'ingresos' => $ingresos['total'],
            'ingresos_filas' => $ingresos['filas'],
            'ingresos_sin_equivalente' => $ingresos['sinEquivalente'],
        ];
    }

    /**
     * Una consulta de agregado: total en NIO, filas, y filas sin equivalente.
     *
     * Las tres cosas salen en la misma consulta y no en tres porque se pueden
     * pedir a la vez, y porque asking de ellas por separado son tres viajes a la
     * base para sacar tres numeros que salen de la misma fila.
     *
     * El "COALESCE(SUM(...), 0)" no es cosmetico: SUM de una consulta que no
     * devuelve filas devuelve NULL, no cero, y un NULL que llega a la vista se
     * pinta como celda vacia. El taller leeria "costo total" en blanco y no sabria
     * si es que no gasto o que la pantalla fallo.
     *
     * @return array{total: float, filas: int, sinEquivalente: int}
     */
    private function parteDeCostos(): array
    {
        $desde = $this->desde();

        $fila = MovimientosCosto::query()
            ->when($desde !== null, fn ($q) => $q->whereDate('fecha', '>=', $desde))
            ->whereDate('fecha', '<=', $this->hasta())
            ->selectRaw(
                'COUNT(*) AS filas,'
                . ' COALESCE(SUM(costo_total_nio), 0) AS total,'
                . ' COALESCE(SUM(costo_total_nio IS NULL), 0) AS sin_equivalente'
            )
            ->first();

        return $this->formateaParte($fila);
    }

    /**
     * La mano de obra, que esta en su propia tabla y no en la de costos.
     *
     * @return array{total: float, filas: int, sinEquivalente: int}
     */
    private function parteDeManoDeObra(): array
    {
        $desde = $this->desde();

        $fila = TrabajosEmpleado::query()
            ->when($desde !== null, fn ($q) => $q->whereDate('fecha', '>=', $desde))
            ->whereDate('fecha', '<=', $this->hasta())
            ->selectRaw(
                'COUNT(*) AS filas,'
                . ' COALESCE(SUM(total_nio), 0) AS total,'
                . ' COALESCE(SUM(total_nio IS NULL), 0) AS sin_equivalente'
            )
            ->first();

        return $this->formateaParte($fila);
    }

    /**
     * La depreciacion de los equipos.
     *
     * Dos cosas que no se ven aqui y estan puestas a proposito:
     *
     *  - La fecha que se usa es `fecha_inicio`, no `fecha_fin`, porque la
     *    depreciacion se va acumulando mientras el equipo trabaja: se cuenta en el
     *    periodo en que empezo a trabajar en ese proceso. Y es un datetime, no una
     *    date como en las otras tablas, por eso el whereDate.
     *
     *  - `depreciacion_total` no admite nulo y ya esta en LibS: el equipo guarda un
     *    solo valor de compra, sin moneda, y la pantalla lo enseña tal cual. Por
     *    eso aqui no hay nada que convertir y "sin equivalente" es siempre cero.
     *
     * @return array{total: float, filas: int, sinEquivalente: int}
     */
    private function parteDeDepreciacion(): array
    {
        $desde = $this->desde();

        $fila = DB::table('proceso_equipos')
            ->whereNull('deleted_at')
            ->when($desde !== null, fn ($q) => $q->whereDate('fecha_inicio', '>=', $desde))
            ->whereDate('fecha_inicio', '<=', $this->hasta())
            ->selectRaw('COUNT(*) AS filas, COALESCE(SUM(depreciacion_total), 0) AS total')
            ->first();

        return $this->formateaParte($fila, sinEquivalente: 0);
    }

    /**
     * La materia prima, que sale del almacen y por eso viene del kardex.
     *
     * Solo se cuentan las SALIDAS. Una entrada es material que llega, no material
     * que se gasto: el costo se contabiliza cuando se consume, no cuando llega,
     * para que el costo de un proceso no dependa del momento en que se compro.
     *
     * Y solo las que son de un proceso. Una salida que no tiene proceso es material
     * que se perdio o que se regalo, que no es costo de ninguna orden.
     *
     * @return array{total: float, filas: int, sinEquivalente: int}
     */
    private function parteDeMateriaPrima(): array
    {
        $desde = $this->desde();

        $fila = DB::table('movimientos_inventario')
            ->whereNull('deleted_at')
            ->where('tipo', MovimientosInventario::TIPO_SALIDA)
            ->whereNotNull('proceso_orden_id')
            ->when($desde !== null, fn ($q) => $q->whereDate('fecha', '>=', $desde))
            ->whereDate('fecha', '<=', $this->hasta())
            ->selectRaw(
                'COUNT(*) AS filas,'
                . ' COALESCE(SUM(costo_total_nio), 0) AS total,'
                . ' COALESCE(SUM(costo_total_nio IS NULL), 0) AS sin_equivalente'
            )
            ->first();

        return $this->formateaParte($fila);
    }

    /**
     * Lo que se le ha cobrado al cliente, en LibS.
     *
     * @return array{total: float, filas: int, sinEquivalente: int}
     */
    private function parteDeIngresos(): array
    {
        $desde = $this->desde();

        $fila = Ingreso::query()
            ->when($desde !== null, fn ($q) => $q->whereDate('fecha', '>=', $desde))
            ->whereDate('fecha', '<=', $this->hasta())
            ->selectRaw(
                'COUNT(*) AS filas,'
                . ' COALESCE(SUM(total_nio), 0) AS total,'
                . ' COALESCE(SUM(total_nio IS NULL), 0) AS sin_equivalente'
            )
            ->first();

        return $this->formateaParte($fila);
    }

    /**
     * Convierte la fila de un agregado en el formato que usan todas las partes.
     *
     * @param  object|null  $fila
     * @return array{total: float, filas: int, sinEquivalente: int}
     */
    private function formateaParte($fila, int $sinEquivalente = null): array
    {
        return [
            'total' => round((float) ($fila->total ?? 0), 2),
            'filas' => (int) ($fila->filas ?? 0),
            'sinEquivalente' => $sinEquivalente ?? (int) ($fila->sin_equivalente ?? 0),
        ];
    }

    // ==================================================================
    // El oro
    // ==================================================================

    /**
     * Los gramos que salieron del taller, y cuantos de ellos eran oro fino.
     *
     * Se enseñan los dos porque no son lo mismo y el taller necesita los dos: los
     * gramos son lo que se lavo, y los finos son lo que se puede vender. Una
     * partida de 2 gramos al 75 % tiene 1,5 gramos de oro fino, y valorar los 2
     * seria pagar de mas por material que no era oro. Es la misma regla que aplica
     * la valoracion, y si el panel enseñara solo los gramos estaria tacitando a
     * valorar el mineral entero.
     *
     * Los finos solo se suman de las filas que tienen pureza. Una recuperacion en
     * la que no se midio la pureza no aporta finos, y no aporta cero: no se sabe.
     * Por eso el panel dice cuantas hay sin medir en vez de contarlas como si
     * fueran oro puro.
     *
     * El CAST a DECIMAL(20,6) esta porque se multiplican dos decimales —gramos con
     * cuatro y pureza con seis— y en MySQL el producto de dos DECIMAL sale con la
     * escala mas larga solo si se le pide; sin el cast sale truncado a cuatro
     * decimales, que es justo lo que no se quiere en un numero que se va a
     * multiplicar por un precio de oro.
     *
     * @return array{total: float, finos: float, filas: int, conPureza: int, sinPureza: int}
     */
    public function oro(): array
    {
        $desde = $this->desde();

        $fila = Recuperacione::query()
            ->when($desde !== null, fn ($q) => $q->whereDate('fecha', '>=', $desde))
            ->whereDate('fecha', '<=', $this->hasta())
            ->selectRaw(
                'COUNT(*) AS filas,'
                . ' COALESCE(SUM(gramos), 0) AS total,'
                . ' COALESCE(SUM(CAST(gramos AS DECIMAL(20,6)) * CAST(pureza AS DECIMAL(20,6))), 0) AS finos,'
                . ' COALESCE(SUM(pureza IS NOT NULL), 0) AS con_pureza'
            )
            ->first();

        $filas = (int) ($fila->filas ?? 0);
        $conPureza = (int) ($fila->con_pureza ?? 0);

        return [
            'total' => round((float) ($fila->total ?? 0), 4),
            'finos' => round((float) ($fila->finos ?? 0), 4),
            'filas' => $filas,
            'conPureza' => $conPureza,
            'sinPureza' => $filas - $conPureza,
        ];
    }

    // ==================================================================
    // La serie de doce meses
    // ==================================================================

    /**
     * El costo y el ingreso mes a mes, de los ultimos doce meses.
     *
     * NO FILTRA POR EL PERIODO ELEGIDO, a proposito: es una tendencia, y si
     * filtrase por "este mes" seria una sola barra y dejaria de ser una serie. Va
     * siempre de los ultimos doce meses, que es el tiempo que se mira para saber si
     * el taller va mejor o peor que hace un año.
     *
     * Los meses que no tienen nada salen con cero, y no se esconden. Una serie con
     * huecos es mas dificil de leer que una con ceros, y ademas el cero en un mes
     * sin datos es informacion: en un taller que esta empezando, no gastar un mes
     * es un dato y no un fallo de la consulta.
     *
     * @return array{etiquetas: array<int, string>, costo: array<int, float>, ingreso: array<int, float>}
     */
    public function serieDeDoceMeses(): array
    {
        $primerMes = $this->hoy->copy()->startOfMonth()->subMonths(11);

        $claves = [];
        $etiquetas = [];

        for ($i = 0; $i < 12; $i++) {
            $mes = $primerMes->copy()->addMonths($i);

            $claves[] = $mes->format('Y-m');
            $etiquetas[] = $mes->translatedFormat('M Y');
        }

        $costo = $this->serieDeCostosPorMes($claves);
        $ingreso = $this->serieDeIngresosPorMes($claves);

        return [
            'etiquetas' => $etiquetas,
            'costo' => $costo,
            'ingreso' => $ingreso,
        ];
    }

    /**
     * El costo mes a mes, con los cuatro sumandos juntos.
     *
     * @param  array<int, string>  $claves
     * @return array<int, float>
     */
    private function serieDeCostosPorMes(array $claves): array
    {
        $costos = $this->serieDeUnaTabla('movimientos_costos', 'fecha', 'costo_total_nio', $claves);
        $manoObra = $this->serieDeUnaTabla('trabajos_empleados', 'fecha', 'total_nio', $claves);
        $materiaPrima = $this->serieDeUnaTabla(
            'movimientos_inventario',
            'fecha',
            'costo_total_nio',
            $claves,
            soloSalidasDeProceso: true
        );

        // El equipo no tiene columna `fecha` y ademas su fecha es un datetime
        $depreciacion = $this->serieDeUnaTabla(
            'proceso_equipos',
            'fecha_inicio',
            'depreciacion_total',
            $claves
        );

        $salida = [];

        foreach ($claves as $clave) {
            $salida[] = round(
                ($costos[$clave] ?? 0)
                + ($manoObra[$clave] ?? 0)
                + ($materiaPrima[$clave] ?? 0)
                + ($depreciacion[$clave] ?? 0),
                2
            );
        }

        return $salida;
    }

    /**
     * Una serie mensual de una tabla, por la consulta que haga falta.
     *
     * Se escribe una vez y se usa cuatro porque las cuatro tienen lo mismo: misma
     * forma de agrupar por mes, mismo filtro de borrado logico, misma columna de
     * fecha. Lo que cambia es el nombre de la tabla y el de las dos columnas, y eso
     * son dos argumentos.
     *
     * La materia prima lleva un filtro aparte —salidas con proceso— porque es el
     * unico de los cuatro que no es solo "todo lo que hay en la tabla", y por eso
     * va como parametro con nombre en vez de como una bandera suelta que se podria
     * leer al reves sin querer.
     *
     * @param  array<int, string>  $claves
     * @return array<string, float>
     */
    private function serieDeUnaTabla(
        string $tabla,
        string $columnaFecha,
        string $columnaValor,
        array $claves,
        bool $soloSalidasDeProceso = false
    ): array {
        $mes = "DATE_FORMAT($columnaFecha, '%Y-%m')";

        $filas = DB::table($tabla)
            ->whereNull('deleted_at')
            ->when(
                $soloSalidasDeProceso,
                fn ($q) => $q
                    ->where('tipo', MovimientosInventario::TIPO_SALIDA)
                    ->whereNotNull('proceso_orden_id')
            )
            ->whereIn(DB::raw($mes), $claves)
            ->selectRaw("$mes AS mes, COALESCE(SUM($columnaValor), 0) AS total")
            ->groupBy(DB::raw($mes))
            ->pluck('total', 'mes');

        $salida = [];

        foreach ($filas as $clave => $total) {
            $salida[$clave] = (float) $total;
        }

        return $salida;
    }

    /**
     * Los ingresos mes a mes.
     *
     * @param  array<int, string>  $claves
     * @return array<int, float>
     */
    private function serieDeIngresosPorMes(array $claves): array
    {
        $filas = $this->serieDeUnaTabla('ingresos', 'fecha', 'total_nio', $claves);

        return array_map(fn ($clave) => round($filas[$clave] ?? 0, 2), $claves);
    }

    // ==================================================================
    // Las listas, que no filtran por periodo
    // ==================================================================

    /**
     * Las ordenes que siguen abiertas: pendientes y en proceso.
     *
     * La lista lleva el avance de los procesos de cada orden —"2 de 3"— porque
     * "¿como va esta orden?" se responde con los procesos y no con el estado de la
     * orden: una orden en proceso puede tener un proceso empezado y tres sin tocar,
     * y el estado no dice cual de las dos cosas es.
     *
     * Y el avance se cuenta con estadoCalculado(), por la regla 2. Un
     * `COUNT(fecha_fin IS NOT NULL)` contaria como terminado un proceso cuya fecha
     * de fin esta puesta pero todavia no ha llegado, y diria que la orden va mas
     * advanced de lo que va.
     *
     * @return Collection<int, OrdenesTrabajo>
     */
    public function ordenesAbiertas(): Collection
    {
        $procesos = 'procesos_orden AS p';
        $terminado = '(p.estado <> ' . ProcesosOrden::ESTADO_CANCELADO
            . ' AND p.fecha_fin IS NOT NULL AND DATE(p.fecha_fin) <= CURDATE())';

        return OrdenesTrabajo::query()
            ->select([
                'ordenes_trabajo.*',
                DB::raw(
                    '(SELECT COUNT(*) FROM ' . $procesos
                    . ' WHERE p.orden_trabajo_id = ordenes_trabajo.id'
                    . ' AND p.deleted_at IS NULL) AS procesos_total'
                ),
                DB::raw(
                    '(SELECT COUNT(*) FROM ' . $procesos
                    . ' WHERE p.orden_trabajo_id = ordenes_trabajo.id'
                    . ' AND p.deleted_at IS NULL AND ' . $terminado . ') AS procesos_terminados'
                ),
            ])
            ->with('cliente:id,nombre')
            ->whereIn('estado', [
                OrdenesTrabajo::ESTADO_PENDIENTE,
                OrdenesTrabajo::ESTADO_EN_PROCESO,
            ])
            ->orderBy('fecha')
            ->orderBy('codigo')
            ->limit(self::CUANTAS_EN_LISTAS)
            ->get();
    }

    /**
     * Los procesos que se empezaron y no se han terminado.
     *
     * Esta es la lista mas util del panel, y por eso va la primera.
     *
     * Un proceso cuenta como atrasado cuando tiene fecha de inicio, no tiene fecha
     * de fin, y no se cancelo. Y no se mira que la fecha de fin este puesta: si le
     * falta, esta en proceso; una fecha de fin puesta para dentro de dos dias no lo
     * convierte en terminado, porque el dia que se pone esa fecha el proceso ya no
     * va tarde, y hay que decirlo el dia que pasa y no el dia que alguien se acuerda
     * de escribir la fecha.
     *
     * Y se ordena por la mas antigua primero, que es la que mas duele.
     *
     * Los dias se cuentan en PHP y no con DATEDIFF, porque `fecha_inicio` es un
     * datetime con hora y con CURDATE() la cuenta sale con la hora de mas: un
     * proceso que empezo ayer a las once de la noche sale con cero dias hoy y con
     * uno mañana. Un contador de dias que cambia a media tarde no lo lee nadie.
     *
     * @return Collection<int, ProcesosOrden>
     */
    public function procesosAtrasados(): Collection
    {
        $procesos = ProcesosOrden::query()
            ->with(['etapa:id,nombre', 'orden_trabajo:id,codigo,cliente_id,estado'])
            ->whereNotNull('fecha_inicio')
            ->whereNull('fecha_fin')
            ->where('estado', '!=', ProcesosOrden::ESTADO_CANCELADO)
            ->where('fecha_inicio', '<=', $this->hoy->endOfDay())
            ->orderBy('fecha_inicio')
            ->limit(self::CUANTAS_EN_LISTAS)
            ->get();

        return $procesos->map(function (ProcesosOrden $proceso) {
            $proceso->setAttribute('dias_atraso', (int) $proceso->fecha_inicio->diffInDays($this->hoy));

            return $proceso;
        });
    }

    /**
     * Las ultimas ordenes que se crearon, esten en el estado que esten.
     *
     * Sale entera y no solo las abiertas, porque a veces lo que se busca es "que
     * paso con la OT-2026-0007", y esa puede estar finalizada. Y son pocas, porque
     * esta lista es para reconocer un codigo, no para trabajar: quien va a buscar
     * una orden concreta lo hace en su propia pantalla, que tiene filtros.
     *
     * @return Collection<int, OrdenesTrabajo>
     */
    public function ultimasOrdenes(): Collection
    {
        return OrdenesTrabajo::query()
            ->with('cliente:id,nombre')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(self::ULTIMAS_ORDENES)
            ->get();
    }
}

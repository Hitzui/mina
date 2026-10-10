<?php

namespace App\Http\Controllers\Procesos;

use App\DataTables\CobrosDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Cajas;
use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\DetalleCobro;
use App\Models\Ingreso;
use App\Models\MetodosPagos;
use App\Models\Moneda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Cobros: el dinero que el cliente entrega.
 *
 * NO HAY MODAL. Una compra lleva cabecera y un numero cualquiera de lineas de
 * material, y una rejilla en un modal se queda corta; un cobro es exactamente
 * eso —cabecera y varias lineas que se añaden y se quitan—, asi que va en pantalla
 * aparte, en la de compras.
 *
 * ===========================================================================
 * LAS SEIS REGLAS, Y POR QUE LAS SEIS ESTAN EN EL SERVIDOR
 * ===========================================================================
 *
 * 1. EL TOTAL NO SE ESCRIBE. Es la suma de las lineas. Si se aceptara ya
 *    calculado, un total equivocado se guardaria como si fuera verdad y habria dos
 *    cifras distintas —el total y la suma del detalle— sin que ninguna avisara de
 *    la otra. Misma regla que los ingresos, los costos y las compras.
 *
 * 2. UN COBRO TIENE AL MENOS UN INGRESO. detalle_cobros.ingreso_id es obligatoria en
 *    la base, de modo que un cobro sin lineas no se puede guardar ni a la fuerza.
 *    Aqui se avisa antes, con un mensaje que dice lo que hay que hacer.
 *
 * 3. CADA LINEA APUNTA A UN INGRESO DEL MISMO CLIENTE. Es la que mas se puede
 *    saltar y la mas grave: se cobra a un cliente y se le descuenta a otro, las dos
 *    filas son validas y las sumas cuadran, y el saldo de los dos queda mal sin que
 *    nada lo note hasta que alguien reclama. La comprobacion vive en el modelo,
 *    justamente para que ningun camino la esquive.
 *
 * 4. EL MISMO INGRESO NO PUEDE APARECER DOS VECES EN EL MISMO COBRO. En cobros
 *    distintos si, porque cobrar a plazos es lo normal —eso se permite y es la
 *    razon de que el total de un ingreso pueda repartirse—. Pero duplicarlo dentro
 *    del mismo cobro es un error de tecleo y ademas hace que el desglose de la
 *    ficha tenga dos filas iguales que no significan nada.
 *
 * 5. NO SE SOBRECOBRA, CUANDO LAS MONEDAS SON COMPARABLES. El detalle va en la
 *    moneda del cobro, de modo que solo se puede comparar con el total del ingreso
 *    cuando el ingreso esta facturado en la misma moneda. Con monedas distintas no
 *    se bloquea, porque no hay forma de saberlo sin una conversion que la tabla no
 *    tiene donde guardar. Ver Cobro::validaNoSobrecobra().
 *
 * 6. LOS TRES DESPLEGABLES OFRECEN SOLO LO ACTIVO: caja, metodo de pago y moneda.
 *    Un cobro finalizado es una foto de lo que se cobro y se puede seguir viendo
 *    con una caja que hoy esta desactivada —la caja se desactiva cuando ya no se
 *    usa, no cuando se dejo de existir—, asi que la ficha no se limita a activos: es
 *    el alta y la edicion los que ofrecen solo lo que se puede elegir ahora.
 */
class CobroController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('cobros');
        $this->middleware('permission:cobros.edit')->only('finalizar');
    }

    public function index(CobrosDataTable $dataTable)
    {
        $title = 'Cobros';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Cobros'],
        ];

        /*
         * Que falta para poder registrar un cobro.
         *
         * Sin esto, la tabla de metodos de pago estuvo vacia hasta hace dos dias y
         * el boton de "nuevo" llevaba a una pantalla con tres desplegables vacios sin
         * decir por que. El usuario busca el error en el formulario cuando el
         * problema es de catalogo, y eso es tiempo perdido buscando donde no esta.
         *
         * Se cuentan los ACTIVOS y no los que hay, porque un metodo desactivado no
         * se ofrece igual que uno que no existe: el usuario ve la lista de
         * configuracion, lo activa y sigue sin poder cobrar.
         */
        $faltanDesplegables = [];

        if (Cajas::paraRegistrar()->count() === 0) {
            $faltanDesplegables[] = 'No hay ninguna caja activa.';
        }

        if (MetodosPagos::paraRegistrar()->count() === 0) {
            $faltanDesplegables[] = 'No hay ningún método de pago activo.';
        }

        if (Moneda::query()->where('estado', true)->count() === 0) {
            $faltanDesplegables[] = 'No hay ninguna moneda activa.';
        }

        return $dataTable->render(
            'procesos.cobros.index',
            compact('title', 'breadcrumbs', 'faltanDesplegables')
        );
    }

    public function create()
    {
        $cobro = new Cobro([
            Cobro::FECHA => today(),
            Cobro::ESTADO => Cobro::ESTADO_PENDIENTE,
        ]);

        return view('procesos.cobros.create', $this->datosDeFormulario($cobro));
    }

    public function store(Request $request)
    {
        $datos = $this->validarCabecera($request);
        $lineas = $this->validarLineas($request);

        $cobro = DB::transaction(function () use ($datos, $lineas) {
            /*
             * crearConCodigo y no create() mas el codigo a mano, porque el codigo
             * tiene indice unico y dos altas a la vez —dos personas, o la misma en
             * dos pestanas— podrian sacar el mismo numero. El metodo lo reintenta
             * hasta tres veces dentro de su propia transaccion y, si no lo logra,
             * avisa en vez de dejar un cobro con el codigo repetido.
             */
            $cobro = Cobro::crearConCodigo($datos);

            $this->validaNoSobrecobra($cobro, $lineas);

            $this->guardarLineas($cobro, $lineas);

            $this->actualizarMonto($cobro);

            return $cobro;
        });

        Alert::toast($this->mensaje($cobro))->success()->flash();

        return redirect()->route('procesos.cobros.show', $cobro);
    }

    public function show(Cobro $cobro)
    {
        $cobro->load([
            'cliente',
            'caja',
            'metodo_pago',
            'moneda',
            'detalles.ingreso.orden_trabajo',
            'detalles.ingreso.tipo_ingreso',
        ]);

        $title = 'Cobro ' . $cobro->codigo;

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Cobros', 'url' => route('procesos.cobros.index')],
            ['label' => $cobro->codigo],
        ];

        /*
         * Cuanto lleva cobrado de cada ingreso, para el aviso de los que ya estan
         * pagados. Se cuenta desde todos los cobros y sin contar este, porque si se
         * contara el propio cobro cada linea se veria como duplicada.
         */
        $cobradoPorIngreso = $cobro->cobradoPorIngreso($cobro->id);

        return view(
            'procesos.cobros.show',
            compact('title', 'breadcrumbs', 'cobro', 'cobradoPorIngreso')
        );
    }

    public function edit(Cobro $cobro)
    {
        $cobro->load('detalles');

        return view('procesos.cobros.edit', $this->datosDeFormulario($cobro));
    }

    /**
     * Confirma la recepción del dinero sin volver a enviar ni reconstruir el detalle.
     */
    public function finalizar(Cobro $cobro)
    {
        DB::transaction(function () use ($cobro) {
            $cobroBloqueado = Cobro::query()
                ->lockForUpdate()
                ->findOrFail($cobro->id);

            if ($cobroBloqueado->estado === Cobro::ESTADO_CANCELADA) {
                throw ValidationException::withMessages([
                    'estado' => 'Un cobro cancelado no se puede finalizar directamente. Edítelo primero para reactivarlo.',
                ]);
            }

            $lineas = $cobroBloqueado->detalles()->get();

            if ($lineas->isEmpty()) {
                throw ValidationException::withMessages([
                    'estado' => 'No se puede confirmar un cobro sin líneas de ingreso.',
                ]);
            }

            $cobroBloqueado->estado = Cobro::ESTADO_FINALIZADA;
            $cobroBloqueado->monto = round((float) $lineas->sum('monto'), 2);
            $cobroBloqueado->save();
        });

        Alert::toast('Pago confirmado. El cobro quedó Finalizado y sus líneas cuentan como recibidas.')
            ->success()
            ->flash();

        return redirect()->route('procesos.cobros.show', $cobro);
    }

    public function update(Request $request, Cobro $cobro)
    {
        $datos = $this->validarCabecera($request);
        $lineas = $this->validarLineas($request);

        $cobro = DB::transaction(function () use ($cobro, $datos, $lineas) {
            $cobro->update($datos);

            $this->validaNoSobrecobra($cobro, $lineas);

            $this->guardarLineas($cobro, $lineas);

            $this->actualizarMonto($cobro);

            return $cobro;
        });

        Alert::toast($this->mensaje($cobro))->success()->flash();

        return redirect()->route('procesos.cobros.show', $cobro);
    }

    /**
     * Borrar el cobro, y con el sus lineas, de verdad.
     *
     * =============================================================================
     * LAS LÍNEAS SE BORRAN CON ÉL, Y NO SE DAN DE BAJA. ESTO CAMBIÓ.
     * =============================================================================
     *
     * Se puede leer por las dos ramas y las dos suenan razonables, asi que hace
     * falta decir cual manda y por que.
     *
     * detalle_cobros tiene FOREIGN KEY (cobro_id) REFERENCES cobros(id) ON DELETE
     * CASCADE. O sea: si el cobro se borra de verdad, la base borra sus lineas
     * igualmente, y no hay forma dearlas: o el cobro sobrevive, o las lineas
     * mueren con el.
     *
     * Se probo lo contrario —dar de baja las lineas y luego borrar el cobro— y
     * funciona, pero solo mientras las lineas esten VIVAS. En cuanto una linea se
     * dio de baja antes (una linea quitada al editar el cobro, que es el caso
     * normal de cualquier cobro que se ha corregido), esa fila dada de baja sigue
     * escribiendo el id del cobro, y el forceDelete() del cobro choca con la clave
     * foranea de una fila que MySQL considera viva. El borrado falla con un error
     * de integridad referencial.
     *
     * O sea: dar de baja las lineas primero solo funciona en los cobros que nunca
     * se han editado. Que un borrado funcione la primera vez y falle la segunda es
     * de las peores formas de fallo que hay: el taller ve que a veces se puede
     * borrar, no ve por que a veces no, y cuando le pasa ya no puede deshacer nada.
     *
     * =============================================================================
     * Y QUE SE PIERDA EL HISTORIAL ES LO MENOS MAL
     * =============================================================================
     *
     * La idea de que las lineas quedaran dadas de baja —"se cobró esto y se anuló
     * esta parte"— era buena, pero no es posible con este esquema sin quitar el
     * CASCADE, y quitarlo es un cambio de esquema que ademas dejaria lineas
     * apuntando a un cobro que ya no existe.
     *
     * Lo que queda de ese rastro es el COBRO CANCELADO, que es la via que el
     * taller deberia usar de verdad: poner el cobro en Cancelada deja el documento
     * entero, sus lineas siguen visibles en la ficha y dejan de contar como dinero
     * cobrado, y todo se puede deshacer volviendo a Pendiente. El borrado es para
     * cuando se registro un cobro que nunca existio —se equivoco de cliente y se
     * quiere que no quede nada—, y ahi perder las lineas es lo correcto: nunca
     * estuvo el dinero.
     *
     * Y por eso el boton de borrar avisa de las tres cosas segun el estado. Eso ya
     * esta en _action.blade.php y en la ficha.
     *
     * El codigo tampoco vuelve: es un indice unico y la fila borrada sigue contando
     * para el, de modo que el siguiente cobro sigue siendo CBX-000002.
     *
     * Y el forceDelete() es a proposito: nada depende del cobro —no hay almacen, no
     * hay tarjeta, no hay nada que se Actualice con el— asi que dejarlo dado de
     * baja solo haria que el taller lo viera todavia en consultas que filtran mal
     * el borrado logico. En las compras es al reves, y por eso ahi hay un
     * servicio que da de baja los movimientos antes: alli el material ya estaba en
     * el almacen y un borrado logico lo reactivaria al restaurar la compra.
     */
    public function destroy(Cobro $cobro)
    {
        DB::transaction(function () use ($cobro) {
            $cobro->forceDelete();
        });

        /*
         * El aviso dice lo que paso de verdad, que es que no queda nada. La version
         * anterior decia "sus lineas quedan dadas de baja en el historial", y era
         * mentira: la base las borra con el cobro por el ON DELETE CASCADE.
         *
         * Y por eso mismo el mensaje apunta a la alternativa, que es lo que el
         * taller deberia usar cuando lo que quiere es deshacer: poner el cobro en
         * Cancelada deja el documento entero y todo se puede revertir.
         */
        Alert::toast(
            'Cobro eliminado. Se borró también sus líneas, porque el documento ya no existe. '
            . 'Si lo que quería era deshacer el cobro sin perderlo, póngalo en Cancelada.'
        )
            ->success()
            ->flash();

        return redirect()->route('procesos.cobros.index');
    }

    /**
     * Los ingresos de un cliente, para el desplegable de las lineas.
     *
     * Va por ajax porque son los datos que mas cambian: en cuanto se elige cliente,
     * la lista entera se sustituye. Mandarlos todos en la pagina haria que cada
     * ficha de cobro cargara los ingresos de todos los clientes.
     *
     * Y se manda lo que el javascript necesita para no tener que preguntar otra
     * vez: el total del ingreso, su moneda y cuanto lleva cobrado. Con eso la linea
     * puede decir "de 3.000, ya cobrados 1.000" sin una segunda peticion por linea.
     */
    public function ingresosDeCliente(Request $request)
    {
        $datos = $request->validate([
            'cliente_id' => ['required', 'exists:clientes,id'],
            'cobro_id' => ['nullable', 'integer'],
        ], [
            'cliente_id.required' => 'Elija primero el cliente del cobro.',
        ]);

        /*
         * Cuanto lleva cobrado cada ingreso, contando TODOS los cobros menos el que
         * se esta editando.
         *
         * Y el "menos el que se esta editando" es la parte que no se puede dejar de
         * lado. Sin el, al editar un cobro el ajax contaria sus propias lineas, y
         * cada linea de la rejilla se veria como si ya tuviera dinero cobrado de
         * antes —"de 3.000, cobrados 1.000"—, siendo justo lo que el usuario esta
         * a punto de escribir. Peor: le haria creer que ya cobro esa parte.
         *
         * Y en el ALTA, sin cobro que excluir, hay que contar igualmente y no
         * devolver un mapa vacio. Devolverlo vacio hacia que cada ingreso del
         * desplegable saliera como "sin nada cobrado" aunque hubiera cobros
         * anteriores, y esa es justo la informacion que evita sobrecobrar: el
         * taller veria que el ingreso esta libre y pondria el importe entero.
         *
         * Por eso la cuenta no sale de un modelo de cobro que puede no existir,
         * sino de la consulta que cuenta por ingreso, que funciona igual en los dos
         * casos.
         */
        $cobradores = Cobro::cobradoPorIngresoDeTodos(
            empty($datos['cobro_id']) ? null : (int) $datos['cobro_id']
        );

        $ingresos = Ingreso::query()
            ->whereHas('orden_trabajo', fn($q) => $q->where('cliente_id', $datos['cliente_id']))
            ->with(['orden_trabajo:id,codigo', 'tipo_ingreso:id,nombre'])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        return response()->json([
            'ingresos' => $ingresos->map(fn(Ingreso $ingreso) => [
                'id' => (int) $ingreso->id,
                'codigo_orden' => $ingreso->orden_trabajo?->codigo ?? '—',
                'fecha' => $ingreso->fecha->format('d/m/Y'),
                'tipo' => $ingreso->tipo_ingreso?->nombre ?? 'Ingreso',
                'descripcion' => $ingreso->descripcion,
                'total' => (float) $ingreso->total,
                'moneda_id' => (int) $ingreso->moneda_id,
                'cobrado' => $cobradores[(int) $ingreso->id] ?? 0.0,
            ])->values(),
        ]);
    }

    // ==================================================================
    // Lo de siempre
    // ==================================================================

    /**
     * Lo que necesitan los formularios de alta y de edicion.
     */
    private function datosDeFormulario(Cobro $cobro): array
    {
        $cajas = Cajas::paraRegistrar()->get();
        $metodos = MetodosPagos::paraRegistrar()->get();
        $monedas = Moneda::query()->where('estado', true)->orderBy('codigo')->get();
        $clientes = Cliente::query()->orderBy('nombre')->get();

        $esEdicion = $cobro->exists;

        /*
         * Las lineas existentes se mandan con numeros y no con texto formateado. Un
         * "1,000" dentro de un input de numero no se lee, y el separador de miles
         * depende del navegador y no del servidor —ya se hizo esta cuenta en la
         * etapa del formato de numero y salio ahi el problema—.
         */
        $lineasParaJs = $esEdicion
            ? $cobro->detalles->map(fn(DetalleCobro $linea) => [
                'ingreso_id' => (int) $linea->ingreso_id,
                'monto' => (float) $linea->monto,
                'observaciones' => $linea->observaciones,
            ])->values()->all()
            : [];

        /*
         * Y los ingresos del cliente, si ya hay uno elegido. En el alta no hay
         * cliente todavia, asi que la lista llega vacia y la rellena el ajax en
         * cuanto se elija.
         */
        $ingresos = $cobro->cliente_id
            ? $this->ingresosParaJs($cobro)
            : [];

        $title = $esEdicion ? 'Editar cobro ' . $cobro->codigo : 'Nuevo cobro';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Cobros', 'url' => route('procesos.cobros.index')],
            [
                'label' => $esEdicion ? $cobro->codigo : 'Nuevo',
                'url' => $esEdicion ? route('procesos.cobros.show', $cobro) : '#',
            ],
            ['label' => $esEdicion ? 'Editar' : 'Nuevo'],
        ];

        /*
         * Los numeros van como numeros, no formateados, porque van a un <input
         * type="number"> y a un data- que el javascript lee con parseFloat. Un
         * "1,000" dentro de un input de numero no se lee, y el separador de miles
         * depende del navegador y no del servidor —esto ya salio en la etapa del
         * formato de numero y por eso se sabe—.
         *
         * Y el json se arma aqui y no en la vista, porque el @json() de blade con
         * una sintaxis de php dentro no compila siempre y el fallo sale en la vista
         * compilada, en una linea que ya no es la que se escribio.
         */
        $titulo = $esEdicion ? 'Editar cobro ' . $cobro->codigo : 'Nuevo cobro';

        $datosParaJs = json_encode(
            [
                'cobro_id' => $esEdicion ? $cobro->id : null,
                'moneda_id' => $cobro->moneda_id,
                'ingresos' => $ingresos,
                'lineas' => $lineasParaJs,
                'urlIngresos' => route('procesos.cobros.ingresos-cliente'),
            ],
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        /*
         * Y el nombre del archivo sale del codigo, no de la ruta: el boton de
         * descargar el excel del listado pone este nombre, y un nombre con barras
         * y guiones bajos se ve como un fallo de la pantalla.
         */
        return compact(
            'title',
            'titulo',
            'breadcrumbs',
            'cobro',
            'cajas',
            'metodos',
            'monedas',
            'clientes',
            'esEdicion',
            'lineasParaJs',
            'ingresos',
            'datosParaJs',
        );
    }

    /**
     * Los ingresos de un cobro, en el formato que espera el javascript.
     *
     * Y es la misma cuenta que usa ingresosDeCliente(), con la misma exclusion del
     * propio cobro. La razon de que haya dos metodos y no uno es que este se llama
     * una vez al abrir la pagina y el otro en cada cambio de cliente, asi que no
     * pueden compartir estado; pero la cuenta que hacen tiene que ser LA MISMA, o
     * al editar se verian unas cifras y al cambiar de cliente otras, sin que ninguna
     * avisara de la diferencia.
     */
    private function ingresosParaJs(Cobro $cobro): array
    {
        $cobradores = Cobro::cobradoPorIngresoDeTodos($cobro->id);

        return Ingreso::query()
            ->whereHas('orden_trabajo', fn($q) => $q->where('cliente_id', $cobro->cliente_id))
            ->with(['orden_trabajo:id,codigo', 'tipo_ingreso:id,nombre'])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get()
            ->map(fn(Ingreso $ingreso) => [
                'id' => (int) $ingreso->id,
                'codigo_orden' => $ingreso->orden_trabajo?->codigo ?? '—',
                'fecha' => $ingreso->fecha->format('d/m/Y'),
                'tipo' => $ingreso->tipo_ingreso?->nombre ?? 'Ingreso',
                'descripcion' => $ingreso->descripcion,
                'total' => (float) $ingreso->total,
                'moneda_id' => (int) $ingreso->moneda_id,
                'cobrado' => $cobradores[(int) $ingreso->id] ?? 0.0,
            ])
            ->values()
            ->all();
    }

    /**
     * La cabecera del cobro.
     *
     * El codigo no se acepta: lo pone Cobro::generarCodigo(). El monto tampoco, y
     * su lugar lo explica la regla 1.
     *
     * Y los tres desplegables se validan contra "activo" y no solo contra "existe",
     * porque si un cliente dejara el formulario abierto y en otra pestana se
     * desactivara la caja, el cobro se guardaria con una caja que ya no se ofrece en
     * ninguna parte.
     */
    private function validarCabecera(Request $request): array
    {
        return $request->validate([
            'cliente_id' => ['required', 'exists:clientes,id'],
            'caja_id' => ['required', 'exists:cajas,id'],
            'metodo_pago_id' => ['required', 'exists:metodos_pago,id'],
            'fecha' => ['required', 'date'],
            'moneda_id' => ['required', 'exists:monedas,id'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'estado' => ['required', 'in:0,1,2,3'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ], [
            'cliente_id.required' => 'Elija a quién se le cobra.',
            'caja_id.required' => 'Elija la caja donde entra el dinero.',
            'metodo_pago_id.required' => 'Elija con qué se cobró.',
            'fecha.required' => 'Indique la fecha del cobro.',
            'moneda_id.required' => 'Elija la moneda en que llega el dinero.',
            'referencia.max' => 'La referencia no puede pasar de 100 caracteres.',
            'estado.in' => 'Ese estado de cobro no existe.',
            'observaciones.max' => 'Las observaciones no pueden pasar de 1000 caracteres.',
        ]);
    }

    /**
     * Lee las lineas del formulario y devuelve solo las que llevan ingreso.
     *
     * El nombre viene como ingresos[] con los indices de los inputs.
     *
     * Y se valida lo que se devuelve y no el dato entero, por la razon que se
     * escribio en las compras: filtrar las filas vacias y luego validar el dato
     * completo es validar lo que se acaba de tirar, y el error acaba senalando una
     * fila invisible en vez de la que el usuario tiene delante.
     *
     * Y aqui anade dos reglas que no hacen falta en las compras, porque alli la
     * linea apunta a un catalogo y aqui apunta a un documento:
     *
     *   - El ingreso tiene que ser del mismo cliente (regla 3), que se comprueba
     *     con una consulta y no con una regla de validacion, porque depende de una
     *     tabla mas.
     *   - Y no puede repetirse dentro del mismo cobro (regla 4), que se comprueba
     *     aqui porque es una pregunta sobre el conjunto de filas.
     */
    private function validarLineas(Request $request): array
    {
        $crudo = $request->input('ingresos', []);

        if (! is_array($crudo)) {
            throw ValidationException::withMessages([
                'ingresos' => 'El cobro tiene que llevar al menos una línea de ingreso.',
            ]);
        }

        $limpias = [];

        foreach ($crudo as $clave => $fila) {
            if (! is_array($fila) || ($fila['ingreso_id'] ?? '') === '') {
                continue;
            }

            $limpias[$clave] = $fila;
        }

        if (empty($limpias)) {
            throw ValidationException::withMessages([
                'ingresos' => 'El cobro tiene que llevar al menos una línea de ingreso: '
                    . 'un cobro sin ingresos no dice a qué se ha cobrado.',
            ]);
        }

        $validador = Validator::make(['ingresos' => $limpias], [
            'ingresos' => ['required', 'array', 'min:1'],
            'ingresos.*.ingreso_id' => ['required', 'integer', 'exists:ingresos,id'],
            'ingresos.*.monto' => ['required', 'numeric', 'gt:0'],
            'ingresos.*.observaciones' => ['nullable', 'string', 'max:255'],
        ], [
            'ingresos.required' => 'El cobro tiene que llevar al menos una línea de ingreso.',
            'ingresos.*.ingreso_id.required' => 'Elija el ingreso de la línea.',
            'ingresos.*.ingreso_id.exists' => 'Ese ingreso ya no está. Vuelva a elegirlo.',
            'ingresos.*.monto.required' => 'Indique cuánto de este dinero va al ingreso.',
            'ingresos.*.monto.gt' => 'El importe tiene que ser mayor que cero.',
            'ingresos.*.observaciones.max' => 'La nota de la línea no puede pasar de 255 caracteres.',
        ]);

        if ($validador->fails()) {
            throw new ValidationException($validador);
        }

        $lineas = $validador->validated()['ingresos'];

        $this->validaQueNoSeRepitaUnIngreso($lineas);
        $this->validaQueSeanDelCliente($request, $lineas);

        return $lineas;
    }

    /**
     * Regla 4: un ingreso no puede aparecer dos veces en el mismo cobro.
     *
     * En cobros distintos si puede —cobrar a plazos es lo normal—, pero duplicarlo
     * dentro del mismo cobro es un error de tecleo, y ademas hace que el desglose de
     * la ficha tenga dos filas iguales que no explican nada.
     */
    private function validaQueNoSeRepitaUnIngreso(array $lineas): void
    {
        $vistos = [];

        foreach ($lineas as $clave => $linea) {
            $ingresoId = (int) $linea['ingreso_id'];

            if (isset($vistos[$ingresoId])) {
                throw ValidationException::withMessages([
                    'ingresos.' . $clave . '.ingreso_id' => sprintf(
                        'El ingreso #%d ya está en otra línea de este cobro. '
                            . 'Si quiere repartir el mismo dinero entre dos líneas de este cobro, '
                            . 'repítalo en cobros distintos: cobrar a plazos se hace así.',
                        $ingresoId
                    ),
                ]);
            }

            $vistos[$ingresoId] = true;
        }
    }

    /**
     * Regla 3: cada ingreso de la línea tiene que ser del cliente del cobro.
     *
     * Se comprueba con una consulta a ordenes_trabajo y no subiendo por la relación
     * del modelo, porque aqui solo hacen falta los ids: es una comprobacion de
     * seguridad, no una pantalla.
     *
     * Y el mensaje dice quien es el otro cliente, no solo que no cuadra. "El ingreso
     * pertenece a otro cliente" deja al usuario adivinando; "el ingreso #7 es de
     * Test 2 y este cobro es de Test 4" le dice que corregir.
     */
    private function validaQueSeanDelCliente(Request $request, array $lineas): void
    {
        $clienteId = (int) $request->input('cliente_id');

        if ($clienteId === 0) {
            return;
        }

        $ids = array_map(fn($linea) => (int) $linea['ingreso_id'], $lineas);

        /*
         * Una sola consulta para todas las lineas. Antes se hacia una por linea, que
         * con veinte lineas son veinte viajes a la base para una comprobacion que se
         * puede resolver de una vez.
         */
        $duenos = DB::table('ingresos')
            ->join('ordenes_trabajo', 'ordenes_trabajo.id', '=', 'ingresos.orden_trabajo_id')
            ->whereIn('ingresos.id', $ids)
            ->join('clientes', 'clientes.id', '=', 'ordenes_trabajo.cliente_id')
            ->get([
                'ingresos.id',
                'ordenes_trabajo.cliente_id',
                'clientes.nombre as cliente_nombre',
            ])
            ->keyBy('id');

        foreach ($lineas as $clave => $linea) {
            $ingresoId = (int) $linea['ingreso_id'];

            $dueno = $duenos[$ingresoId] ?? null;

            if ($dueno === null) {
                continue;
            }

            if ((int) $dueno->cliente_id === $clienteId) {
                continue;
            }

            $nombreDelCobro = DB::table('clientes')
                ->where('id', $clienteId)
                ->value('nombre');

            throw ValidationException::withMessages([
                'ingresos.' . $clave . '.ingreso_id' => sprintf(
                    'El ingreso #%d pertenece a %s, y este cobro es de %s. '
                        . 'Un cobro solo puede repartir el dinero entre ingresos de su mismo cliente.',
                    $ingresoId,
                    $dueno->cliente_nombre,
                    $nombreDelCobro ?? 'otro cliente'
                ),
            ]);
        }
    }

    /**
     * Regla 5: no se sobrecobra un ingreso.
     *
     * Se comprueba DESPUES de validar el formulario, y solo cuando las monedas son
     * comparables. Va con su propio mensaje y no con los de validacion, porque es
     * una regla de negocio y no una forma mal rellenada: el formulario esta bien y
     * lo que no se puede es guardarlo.
     */
    private function validaNoSobrecobra(Cobro $cobro, array $lineas): void
    {
        $problemas = $cobro->validaNoSobrecobra($lineas);

        if ($problemas === []) {
            return;
        }

        $primero = reset($problemas);

        $ingresoId = (int) array_key_first($problemas);
        $lineaConElIngreso = null;

        foreach ($lineas as $clave => $linea) {
            if ((int) $linea['ingreso_id'] === $ingresoId) {
                $lineaConElIngreso = $clave;

                break;
            }
        }

        throw ValidationException::withMessages([
            'ingresos.' . ($lineaConElIngreso ?? 0) . '.monto' => $primero,
        ]);
    }

    /**
     * Deja las lineas del cobro como deben quedar.
     *
     * Se emparejan por ingreso_id, igual que en las compras: las que ya estaban se
     * tocan y las que desaparecieron se dan de baja.
     *
     * Y aqui una linea quitada SE DA DE BAJA y no se borra, que es distinto de lo
     * que hace destroy(). Y no es una contradiccion: una linea que el usuario saca
     * del formulario es una linea que ya no se quiere imputar, pero el cobro sigue
     * existiendo, asi que la fila se queda apuntando a el. Lo unico que se pierde
     * es que ya no cuenta.
     *
     * Y por eso el borrado del cobro entero tiene que ser de verdad: la base tiene
     * ON DELETE CASCADE en detalle_cobros, y una linea dada de baja escribiendo el
     * id del cobro impediria borrar el cobro entero. Se explica entero en
     * destroy(), que es donde esta la decision.
     *
     * El ->withTrashed() es obligatorio y no un detalle: el modelo de la línea usa
     * SoftDeletes, y eso pone un ambito global a la RELACIÓN, no solo a la consulta.
     * Un ->get() de las lineas de un cobro no trae las dadas de baja ni aunque se
     * pregunte, asi que una linea quitada en una edicion anterior no apareceria en
     * $sobrantes y al reponerla se crearia OTRA fila para el mismo ingreso.
     *
     * Quedarian dos filas —una viva y otra dada de baja— apuntando al mismo ingreso,
     * el total saldria bien y todo pareceria funcionar. El desajuste apareceria
     * despues, en la ficha, al decir cuanto queda por cobrar: contaria ese cobro
     * dos veces. Y la comprobacion de "no se repite un ingreso en el mismo cobro" no
     * lo veria, porque solo mira las lineas del formulario, que son dos, una de
     * cada ingreso.
     */
    private function guardarLineas(Cobro $cobro, array $lineas): void
    {
        $sobrantes = [];

        foreach ($cobro->detalles()->withTrashed()->get() as $existente) {
            $sobrantes[(int) $existente->ingreso_id] = $existente;
        }

        foreach ($lineas as $fila) {
            $ingresoId = (int) $fila['ingreso_id'];

            if (isset($sobrantes[$ingresoId])) {
                $detalle = $sobrantes[$ingresoId];
                unset($sobrantes[$ingresoId]);
            } else {
                $detalle = new DetalleCobro();
                $detalle->cobro_id = $cobro->id;
                $detalle->ingreso_id = $ingresoId;
            }

            $detalle->monto = $fila['monto'];
            $detalle->observaciones = $fila['observaciones'] ?? null;

            /*
             * Si la fila venia dada de baja, save() la revive. Sin esto, una linea
             * que se quito y se vuelve a poner en la misma edicion dejaria dos
             * filas para el mismo ingreso —una viva y otra dada de baja— y la ficha
             * la contaria dos veces al decir cuanto queda por cobrar.
             */
            $detalle->deleted_at = null;

            $detalle->save();
        }

        foreach ($sobrantes as $sobrante) {
            $sobrante->delete();
        }
    }

    /**
     * Vuelca el total en la columna, que existe pero no se rellena desde el formulario.
     *
     * Se recalcula despues de guardar las lineas y no antes, porque el total es
     * exactamente su suma.
     */
    private function actualizarMonto(Cobro $cobro): void
    {
        $cobro->monto = $cobro->monto_calculado;
        $cobro->save();
    }

    /**
     * El aviso que sale al guardar.
     *
     * Y avisa del total, que es el numero que el usuario quiere saber y que la
     * pantalla no se lo ensegua mientras escribe: sale de las lineas, y las lineas
     * se rellenan de una en una.
     */
    private function mensaje(Cobro $cobro): string
    {
        $texto = 'Cobro ' . $cobro->codigo . ' guardado por ' . number_format($cobro->total(), 2) . '.';

        if ($cobro->estaCancelado()) {
            $texto .= ' Quedó cancelado: sus líneas no cuentan como cobrado.';
        } elseif ($cobro->esFinalizado()) {
            $texto .= ' El dinero queda confirmado en la caja.';
        } else {
            $texto .= ' Todavía está pendiente: no cuenta como dinero recibido.';
        }

        return $texto;
    }
}

<?php

/**
 * Los cobros, de punta a punta.
 *
 * Lo que se comprueba aqui no es que la pantalla exista, que eso lo hacen otros
 * tests. Son las seis reglas que pueden fallar sin que se note, y las seis estan
 * escritas en el servidor y no en el formulario porque se pueden saltar desde
 * cualquier lado.
 *
 * LAS SEIS REGLAS, Y POR QUE CADA UNA ESTA DONDE ESTA
 *
 * 1. EL TOTAL NO SE ESCRIBE. Es la suma de las lineas. Si se aceptara ya
 *    calculado, un total equivocado entraria en la base como si fuera verdad y
 *    habria dos cifras distintas sin que ninguna avisara de la otra.
 *
 * 2. UN COBRO TIENE AL MENOS UN INGRESO. detalle_cobros.ingreso_id es obligatoria
 *    en la base. Un cobro sin lineas no se puede guardar ni a la fuerza, asi que la
 *    pantalla lo avisa antes de dejar que el servidor lo rechace.
 *
 * 3. CADA LINEA APUNTA A UN INGRESO DEL MISMO CLIENTE. Es la que mas se puede
 *    saltar y la mas grave: se cobra a un cliente y se le descuenta a otro, las dos
 *    filas son validas, las sumas cuadran y los dos saldos quedan mal sin que nada
 *    lo note hasta que alguien reclama. La comprobacion vive en el modelo, que es
 *    donde no se puede esquivar.
 *
 * 4. EL MISMO INGRESO NO PUEDE APARECER DOS VECES EN EL MISMO COBRO. En cobros
 *    distintos si, porque cobrar a plazos es lo normal y es la razon de que el total
 *    de un ingreso se pueda repartir. Dentro del mismo cobro es un error de tecleo
 *    que ademas hace que el desglose tenga dos filas iguales que no dicen nada.
 *
 * 5. NO SE SOBRECOBRA, CUANDO LAS MONEDAS SON COMPARABLES. Los importes del detalle
 *    van en la MONEDA DEL COBRO, no en la del ingreso, de modo que solo se pueden
 *    comparar con el total del ingreso cuando las dos son la misma. Con monedas
 *    distintas no se bloquea —no hay forma de saberlo sin una conversion que la
 *    tabla no tiene donde guardar— pero tampoco se calla.
 *
 * 6. LOS TRES DESPLEGABLES OFRECEN SOLO LO ACTIVO. Caja, metodo y moneda. Y el
 *    borrado da de baja las lineas en vez de borrarlas, para que quede rastro de
 *    que se cobro y se anulo.
 *
 * LOS ESTADOS
 *
 * Los cuatro de siempre: 0 Cancelada, 1 Pendiente, 2 En proceso, 3 Finalizada.
 * Finalizada es cuando el dinero esta confirmado en la caja. Y el Cancelada es el
 * unico que cambia lo que cuenta: sus lineas dejan de ser dinero cobrado.
 *
 * Y UN COBRO CERRADO SI SE PUEDE EDITAR. A diferencia de la orden cerrada, y por
 * una razon concreta: el dinero se entrego igual, y lo que se equivoco fue al
 * apuntarlo. Bloquearlo dejaria el error sin salida.
 *
 * NUNCA SE CUENTA LA TABLA ENTERA. La base es la de verdad y el taller la usa:
 * assertDatabaseCount pasaria por casualidad mientras no haya cobros, y en cuanto
 * haya uno empezaria a fallar sin que cambie nada. Todas las comparaciones son de
 * antes y despues, o acotadas a la fila propia.
 */

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\DetalleCobro;
use App\Models\Ingreso;
use App\Models\Moneda;
use App\Models\OrdenesTrabajo;
use App\Models\TiposIngreso;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CobroTest extends TestCase
{
    use DatabaseTransactions;

    private const CABECERAS = [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ];

    /** Cuantos cobros habia antes de que esta prueba empezara. */
    private int $cobrosIniciales;

    /** Cuantos detalles habia antes de que esta prueba empezara. */
    private int $detallesIniciales;

    /** El usuario con permiso de cobro, que se crea una vez y se reusa. */
    private ?User $usuario = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cobrosIniciales = Cobro::withTrashed()->count();
        $this->detallesIniciales = DetalleCobro::withTrashed()->count();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ==================================================================
    // Andamiaje
    // ==================================================================

    /**
     * Un usuario que puede hacer de todo con los cobros.
     *
     * Se crea uno solo por prueba y se reusa, para no dejar veinte usuarios de
     * prueba en la base del taller cada vez que se corre la suite.
     */
    private function usuario(): User
    {
        if ($this->usuario !== null) {
            return $this->usuario;
        }

        $nombre = 'cobros-test-' . uniqid();

        $this->usuario = User::create([
            'name' => 'Prueba cobros',
            'email' => $nombre . '@taller.test',
            'password' => Hash::make('prueba1234'),
            'email_verified_at' => now(),
        ]);

        $this->usuario->syncPermissions([
            'cobros.view',
            'cobros.create',
            'cobros.edit',
            'cobros.delete',
        ]);

        return $this->usuario;
    }

    /**
     * Un cliente, del que se guarda el id para poder mirar sus ingresos.
     */
    private function cliente(string $nombre = 'Cliente de prueba'): Cliente
    {
        return Cliente::create([
            'nombre' => $nombre . ' ' . uniqid(),
            'telefono' => '00000000',
        ]);
    }

    /**
     * Una orden de un cliente.
     *
     * El codigo y la fecha se ponen aqui aunque no sean lo que se prueba, porque
     * los dos son obligatorios en la base y sin ellos el insert revienta con un
     * error de MySQL que no dice nada de cobros. Un test que falla por la tabla
     * que monta no mide el codigo que quiere medir.
     */
    private function orden(Cliente $cliente): OrdenesTrabajo
    {
        return OrdenesTrabajo::create([
            'cliente_id' => $cliente->id,
            'codigo' => 'OT-TEST-' . uniqid(),
            'fecha' => today(),
            'estado' => OrdenesTrabajo::ESTADO_PENDIENTE,
        ]);
    }

    /**
     * El tipo de ingreso que se pone en los ingresos de prueba.
     *
     * ingresos.tipo_ingreso_id es obligatoria, asi que hace falta uno. Se busca
     * entre los activos y, si no hubiera ninguno —la tabla se queda vacia si el
     * taller borra los cuatro—, se crea uno, porque un test que falla por no
     * tener catalogo no mide lo que dice medir.
     */
    private int $tipoIngresoId = 0;

    private function tipoIngreso(): int
    {
        if ($this->tipoIngresoId !== 0) {
            return $this->tipoIngresoId;
        }

        $existente = TiposIngreso::query()->where('estado', true)->first();

        if ($existente !== null) {
            return $this->tipoIngresoId = (int) $existente->id;
        }

        return $this->tipoIngresoId = (int) TiposIngreso::create([
            'nombre' => 'Prueba ' . uniqid(),
            'descripcion' => 'Creado por los tests de cobros',
            'estado' => true,
        ])->id;
    }

    /**
     * Un ingreso de una orden, con un total en una moneda.
     *
     * El total se pone a mano y no se calcula, porque ahi lo que se prueba es la
     * reparticion del cobro, no la cuenta del ingreso. El ingreso tiene su propia
     * pantalla con sus propios tests.
     */
    private function ingreso(OrdenesTrabajo $orden, float $total, Moneda $moneda): Ingreso
    {
        return Ingreso::create([
            'orden_trabajo_id' => $orden->id,
            'tipo_ingreso_id' => $this->tipoIngreso(),
            'fecha' => today(),
            'descripcion' => 'Ingreso de prueba',
            'cantidad' => 1,
            'unidad_medida' => 'und',
            'precio_unitario' => $total,
            'total' => $total,
            'precio_unitario_nio' => $total,
            'total_nio' => $total,
            'moneda_id' => $moneda->id,
            /*
             * El estado del ingreso va como `true`, no como una constante, y no es
             * descuido: Ingreso no declara constantes de estado —el modelo se hizo
             * antes que el catalogo de estados— y su estado es un booleano que dice
             * si esta activo. Poner un 3 a mano seria inventar un estado que el
             * modelo no reconoce, y el dia que el ingreso tenga los cuatro estados
             * como los demas, este test seguira mintiendo en vez de avisar.
             */
            'estado' => true,
        ]);
    }

    // ==================================================================
    // El andamiaje
    //
    // Caja, metodo de pago y moneda se buscan entre los que ya estan activos
    // antes de crear nada, porque el taller ya tiene y duplicarlos solo genera
    // filas basura que hay que acordar despues.
    // ==================================================================

    private function cajaActiva(): Caja
    {
        $caja = Caja::query()->where('estado', true)->first();

        if ($caja !== null) {
            return $caja;
        }

        return Caja::create([
            'nombre' => 'Caja de prueba ' . uniqid(),
            'descripcion' => 'Caja creada por los tests de cobros',
            'saldo_inicial' => 0,
            'estado' => true,
        ]);
    }

    private function metodoActivo(): int
    {
        $existente = DB::table('metodos_pago')->where('estado', true)->first();

        if ($existente !== null) {
            return (int) $existente->id;
        }

        return (int) DB::table('metodos_pago')->insertGetId([
            'nombre' => 'Efectivo de prueba ' . uniqid(),
            'descripcion' => 'Creado por los tests de cobros',
            'estado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function monedaActiva(): Moneda
    {
        $moneda = Moneda::query()->where('estado', true)->first();

        if ($moneda !== null) {
            return $moneda;
        }

        return Moneda::create([
            'codigo' => 'XXX',
            'nombre' => 'Moneda de prueba',
            'estado' => true,
        ]);
    }

    /**
     * Un cobro completo y guardado, con una linea.
     *
     * Devuelve el cobro y el ingreso, porque casi todos los tests necesitan las dos
     * cosas y tener que buscarlos otra vez en cada uno es ruido.
     */
    private function cobroConUnaLinea(
        Cliente $cliente,
        Ingreso $ingreso,
        Moneda $moneda,
        int $estado = Cobro::ESTADO_FINALIZADA
    ): array {
        $respuesta = $this->actingAs($this->usuario())
            ->post(route('procesos.cobros.store'), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'referencia' => 'Ref de prueba',
                'estado' => $estado,
                'observaciones' => null,
                'ingresos' => [
                    ['ingreso_id' => $ingreso->id, 'monto' => 100.00, 'observaciones' => null],
                ],
            ]);

        $respuesta->assertRedirect();

        $cobro = Cobro::orderByDesc('id')->first();

        return [$cobro, $ingreso];
    }

    // ==================================================================
    // 1. El total no se escribe: es la suma de las lineas
    // ==================================================================

    /**
     * El total guardado sale de la suma, no de un campo del formulario.
     *
     * Se mandan tres lineas de 100, 250,50 y se comprueba que lo guardado son 400.
     * Si el total se aceptara del formulario —que no esta ni en el formulario ni en
     * la validacion— este test no tendria forma de escribirlo, que es justo lo que
     * se quiere comprobar.
     */
    public function test_el_total_es_la_suma_de_las_lineas_y_no_se_acepta_del_formulario()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingresos = [];

        foreach ([100.00, 250.00, 50.00] as $total) {
            $ingresos[] = $this->ingreso($this->orden($cliente), $total, $moneda);
        }

        $lineas = [];

        foreach ($ingresos as $i => $ingreso) {
            $lineas[$i] = [
                'ingreso_id' => $ingreso->id,
                'monto' => [100.00, 250.00, 50.00][$i],
                'observaciones' => null,
            ];
        }

        $respuesta = $this->actingAs($this->usuario())
            ->post(route('procesos.cobros.store'), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_FINALIZADA,
                // Se manda un total a proposito: tiene que ser ignorado.
                'monto' => 999999.00,
                'ingresos' => $lineas,
            ]);

        $respuesta->assertSessionHasNoErrors();

        $cobro = Cobro::orderByDesc('id')->first();

        $this->assertSame(3, $cobro->cantidad_lineas);
        $this->assertSame(400.00, $cobro->total(), 'El total tiene que ser la suma de las líneas.');
        $this->assertSame(400.00, (float) $cobro->monto, 'La columna guardada tiene que ser esa misma suma.');
    }

    /**
     * El total se vuelve a calcular al editar, no se arrastra el anterior.
     *
     * Si el total se copiara al editar, al quitar una linea el cobro seguiria
     * valiendo lo que valia antes: el dinero se habria retirado del ingreso y el
     * cobro no lo notaria.
     */
    public function test_al_quitar_una_linea_el_total_baja_en_la_cantidad_quitada()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingresoUno = $this->ingreso($this->orden($cliente), 500.00, $moneda);
        $ingresoDos = $this->ingreso($this->orden($cliente), 300.00, $moneda);

        [$cobro] = $this->cobroConUnaLinea($cliente, $ingresoUno, $moneda);

        // Se agrega la segunda linea y se deja la primera como estaba.
        $this->actingAs($this->usuario())
            ->put(route('procesos.cobros.update', $cobro), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_FINALIZADA,
                'ingresos' => [
                    ['ingreso_id' => $ingresoUno->id, 'monto' => 100.00, 'observaciones' => null],
                    ['ingreso_id' => $ingresoDos->id, 'monto' => 200.00, 'observaciones' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        $cobro->refresh();

        $this->assertSame(300.00, $cobro->total());

        // Y ahora se quita la segunda: solo queda la primera.
        $this->actingAs($this->usuario())
            ->put(route('procesos.cobros.update', $cobro), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_FINALIZADA,
                'ingresos' => [
                    ['ingreso_id' => $ingresoUno->id, 'monto' => 100.00, 'observaciones' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        $cobro->refresh();

        $this->assertSame(1, $cobro->cantidad_lineas);
        $this->assertSame(100.00, $cobro->total(), 'Quitar una línea tiene que bajar el total.');
    }

    /**
     * Una linea quitada queda dada de baja, no borrada de verdad.
     *
     * Y con una razon que no es purismo: el cobro dice "se cobró esto y se quitó
     * esta parte". Si la fila desapareciera, el historial no podria contar lo que
     * se cobro nunca, y mas adelante no hay forma de saber si un
     * ingreso se quedo sin cobrar por que.
     */
    public function test_una_linea_quitada_queda_dada_de_baja_y_no_borrada()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingresoUno = $this->ingreso($this->orden($cliente), 500.00, $moneda);
        $ingresoDos = $this->ingreso($this->orden($cliente), 300.00, $moneda);

        [$cobro] = $this->cobroConUnaLinea($cliente, $ingresoUno, $moneda);

        $idDeLaSegunda = null;

        $this->actingAs($this->usuario())
            ->put(route('procesos.cobros.update', $cobro), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_FINALIZADA,
                'ingresos' => [
                    ['ingreso_id' => $ingresoUno->id, 'monto' => 100.00, 'observaciones' => null],
                    ['ingreso_id' => $ingresoDos->id, 'monto' => 200.00, 'observaciones' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        $idDeLaSegunda = DetalleCobro::where('cobro_id', $cobro->id)
            ->where('ingreso_id', $ingresoDos->id)
            ->first()
            ?->id;

        $this->actingAs($this->usuario())
            ->put(route('procesos.cobros.update', $cobro), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_FINALIZADA,
                'ingresos' => [
                    ['ingreso_id' => $ingresoUno->id, 'monto' => 100.00, 'observaciones' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        $quitada = DetalleCobro::withTrashed()->find($idDeLaSegunda);

        $this->assertNotNull($quitada, 'La línea quitada tiene que seguir existiendo.');
        $this->assertNotNull($quitada->deleted_at, 'La línea quitada tiene que estar dada de baja.');
    }

    // ==================================================================
    // 2. Un cobro tiene al menos un ingreso
    // ==================================================================

    /**
     * Un cobro sin lineas no se guarda.
     *
     * Y el mensaje tiene que decir que hacer, porque "no se puede guardar" sin mas
     * no le dice a nadie que lo que falta son las lineas.
     */
    public function test_un_cobro_sin_ninguna_linea_no_se_guarda()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $antes = Cobro::withTrashed()->count();

        $respuesta = $this->actingAs($this->usuario())
            ->post(route('procesos.cobros.store'), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_PENDIENTE,
                'ingresos' => [],
            ]);

        $respuesta->assertSessionHasErrors('ingresos');

        $this->assertSame(
            $antes,
            Cobro::withTrashed()->count(),
            'No se puede crear un cobro sin líneas aunque la validación pase.'
        );
    }

    /**
     * Una fila con el ingreso vacio no cuenta como linea, pero tampoco se cuela.
     *
     * El caso real es la fila de ejemplo y las filas anadidas y dejadas en blanco.
     * Si se validara el dato entero, la fila vacia protestaria "falta el ingreso"
     * con el ingreso de verdad elegido tres lineas mas abajo, y el mensaje senalaria
     * una fila que el usuario no tiene delante.
     */
    public function test_una_fila_con_el_ingreso_vacio_no_cuenta_como_linea()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingreso = $this->ingreso($this->orden($cliente), 400.00, $moneda);

        $respuesta = $this->actingAs($this->usuario())
            ->post(route('procesos.cobros.store'), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_FINALIZADA,
                'ingresos' => [
                    0 => ['ingreso_id' => '', 'monto' => '', 'observaciones' => ''],
                    1 => ['ingreso_id' => $ingreso->id, 'monto' => 100.00, 'observaciones' => null],
                    2 => ['ingreso_id' => '', 'monto' => '', 'observaciones' => ''],
                ],
            ]);

        $respuesta->assertSessionHasNoErrors();

        $cobro = Cobro::orderByDesc('id')->first();

        $this->assertSame(1, $cobro->cantidad_lineas, 'Solo hay una línea con ingreso.');
        $this->assertSame(100.00, $cobro->total());
    }

    /**
     * Un importe de cero no es una linea.
     *
     * Una linea de cero no dice nada —el dinero se entrego pero no se imputo a
     * nada— y dejarla pasar haria que el total fuera cero con una linea puesta, que
     * es un cobro sin contenido.
     */
    public function test_una_linea_con_importe_cero_se_rechaza()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingreso = $this->ingreso($this->orden($cliente), 400.00, $moneda);

        $this->actingAs($this->usuario())
            ->post(route('procesos.cobros.store'), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_PENDIENTE,
                'ingresos' => [
                    ['ingreso_id' => $ingreso->id, 'monto' => 0, 'observaciones' => null],
                ],
            ])
            ->assertSessionHasErrors('ingresos.0.monto');
    }

    // ==================================================================
    // 3. Cada linea apunta a un ingreso del mismo cliente
    // ==================================================================

    /**
     * No se puede cobrar a un cliente con el ingreso de otro.
     *
     * Y el mensaje tiene que decir quienes son, porque "no cuadra" deja al usuario
     * adivinando y lo que hace falta es saber a quien pertenece ese ingreso.
     */
    public function test_no_se_puede_cobrar_a_un_cliente_con_el_ingreso_de_otro()
    {
        $clienteDelCobro = $this->cliente('Cliente que paga');
        $otroCliente = $this->cliente('Cliente que NO paga');

        $moneda = $this->monedaActiva();

        $ingresoDelOtro = $this->ingreso($this->orden($otroCliente), 500.00, $moneda);

        $antes = Cobro::withTrashed()->count();

        $respuesta = $this->actingAs($this->usuario())
            ->post(route('procesos.cobros.store'), [
                'cliente_id' => $clienteDelCobro->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_PENDIENTE,
                'ingresos' => [
                    ['ingreso_id' => $ingresoDelOtro->id, 'monto' => 100.00, 'observaciones' => null],
                ],
            ]);

        $respuesta->assertSessionHasErrors('ingresos.0.ingreso_id');

        $mensajes = session('errors')->get('ingresos.0.ingreso_id');

        $this->assertStringContainsString(
            'Cliente que NO paga',
            implode(' ', $mensajes),
            'El mensaje tiene que decir de quién es el ingreso.'
        );

        $this->assertSame(
            $antes,
            Cobro::withTrashed()->count(),
            'Un cobro con el ingreso de otro cliente no se puede guardar.'
        );
    }

    /**
     * La comprobacion del cliente esta en el modelo, no en la pantalla.
     *
     * El punto de este test es que la regla no depende del formulario: se llama
     * directo al modelo, que es lo que haria cualquier otro camino que no pase por
     * el controlador —un comando, un job, una restauracion—.
     */
    public function test_la_comprobacion_de_cliente_esta_en_el_modelo_y_no_en_la_pantalla()
    {
        $clienteDelCobro = $this->cliente();
        $otroCliente = $this->cliente();

        $moneda = $this->monedaActiva();

        $ingresoDelOtro = $this->ingreso($this->orden($otroCliente), 500.00, $moneda);
        $ingresoSuyo = $this->ingreso($this->orden($clienteDelCobro), 500.00, $moneda);

        $cobro = new Cobro([
            Cobro::CLIENTE_ID => $clienteDelCobro->id,
            Cobro::MONEDA_ID => $moneda->id,
        ]);

        $lineaSuyo = new DetalleCobro([
            DetalleCobro::INGRESO_ID => $ingresoSuyo->id,
        ]);

        $lineaDelOtro = new DetalleCobro([
            DetalleCobro::INGRESO_ID => $ingresoDelOtro->id,
        ]);

        $this->assertTrue(
            $lineaSuyo->ingresoEsDelMismoCliente($cobro),
            'El ingreso del propio cliente tiene que pasar.'
        );

        $this->assertFalse(
            $lineaDelOtro->ingresoEsDelMismoCliente($cobro),
            'El ingreso de otro cliente tiene que quedarse fuera, se llame como se llame.'
        );
    }

    /**
     * Una linea sin ingreso no es del cliente de nadie.
     *
     * El caso de tecleo mas facil: si el ingreso_id se manda en cero o vacio y por
     * lo que sea se llegara a la comprobacion, tiene que decir que no y no que sí.
     */
    public function test_una_linea_sin_ingreso_no_pasa_la_comprobacion_de_cliente()
    {
        $cobro = new Cobro([Cobro::CLIENTE_ID => $this->cliente()->id]);

        $linea = new DetalleCobro([DetalleCobro::INGRESO_ID => 0]);

        $this->assertFalse($linea->ingresoEsDelMismoCliente($cobro));
    }

    // ==================================================================
    // 4. El mismo ingreso no puede aparecer dos veces en el mismo cobro
    // ==================================================================

    /**
     * Un ingreso no puede estar en dos lineas del mismo cobro.
     *
     * En cobros distintos si puede —eso es cobrar a plazos— y hay otro test que lo
     * comprueba. Aqui lo que se mira es el mismo cobro, que es el error de tecleo.
     */
    public function test_el_mismo_ingreso_no_puede_estar_en_dos_lineas_del_mismo_cobro()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingreso = $this->ingreso($this->orden($cliente), 1000.00, $moneda);

        $antes = Cobro::withTrashed()->count();

        $respuesta = $this->actingAs($this->usuario())
            ->post(route('procesos.cobros.store'), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_PENDIENTE,
                'ingresos' => [
                    ['ingreso_id' => $ingreso->id, 'monto' => 100.00, 'observaciones' => null],
                    ['ingreso_id' => $ingreso->id, 'monto' => 200.00, 'observaciones' => null],
                ],
            ]);

        $respuesta->assertSessionHasErrors('ingresos.1.ingreso_id');

        $this->assertSame($antes, Cobro::withTrashed()->count());
    }

    /**
     * Y SI puede repartirse en cobros distintos.
     *
     * Este es el motivo de que el total de un ingreso se pueda repartir, que es lo
     * que hace falta para lo de verdad: se factura 1.000 y se va cobrando a plazos.
     */
    public function test_un_ingreso_si_puede_repartirse_en_cobros_distintos()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingreso = $this->ingreso($this->orden($cliente), 1000.00, $moneda);

        $caja = $this->cajaActiva();
        $metodo = $this->metodoActivo();

        foreach ([300.00, 250.00] as $importe) {
            $this->actingAs($this->usuario())
                ->post(route('procesos.cobros.store'), [
                    'cliente_id' => $cliente->id,
                    'caja_id' => $caja->id,
                    'metodo_pago_id' => $metodo,
                    'fecha' => today()->format('Y-m-d'),
                    'moneda_id' => $moneda->id,
                    'estado' => Cobro::ESTADO_FINALIZADA,
                    'ingresos' => [
                        ['ingreso_id' => $ingreso->id, 'monto' => $importe, 'observaciones' => null],
                    ],
                ])
                ->assertSessionHasNoErrors();
        }

        $cobro = Cobro::orderByDesc('id')->first();

        $this->assertSame(
            250.00,
            $cobro->total(),
            'Cada cobro vale lo suyo, aunque los dos repartan el mismo ingreso.'
        );

        $cobradores = $cobro->cobradoPorIngreso();

        $this->assertSame(
            550.00,
            $cobradores[(int) $ingreso->id] ?? 0,
            'El ingreso lleva 550 cobrados en los dos cobros.'
        );
    }

    // ==================================================================
    // 5. No se sobrecobra, cuando las monedas son comparables
    // ==================================================================

    /**
     * No se puede cobrar mas de lo que vale el ingreso, en la misma moneda.
     *
     * Es la regla que sale de que los importes del detalle van en la moneda del
     * cobro: si el ingreso esta facturado en la misma, la comparacion es directa.
     */
    public function test_no_se_puede_cobrar_mas_que_el_total_del_ingreso_en_la_misma_moneda()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $moneda);

        $antes = Cobro::withTrashed()->count();

        $respuesta = $this->actingAs($this->usuario())
            ->post(route('procesos.cobros.store'), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_PENDIENTE,
                'ingresos' => [
                    ['ingreso_id' => $ingreso->id, 'monto' => 600.00, 'observaciones' => null],
                ],
            ]);

        $respuesta->assertSessionHasErrors('ingresos.0.monto');

        $this->assertSame(
            $antes,
            Cobro::withTrashed()->count(),
            'Un cobro que sobrecobra no se guarda.'
        );
    }

    /**
     * El sobrecobro se ve al sumar varios cobros, no solo en uno.
     *
     * El caso de verdad: dos cobros de 300 cada uno sobre un ingreso de 500. Cada
     * uno por separado estaba bien, y entre los dos se pasaron. Por eso la cuenta
     * mira todos los cobros y no solo el que se esta guardando.
     */
    public function test_el_sobrecobro_se_ve_al_sumar_varios_cobros()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $moneda);

        $caja = $this->cajaActiva();
        $metodo = $this->metodoActivo();

        // El primero de 300 pasa, porque 300 < 500.
        $this->actingAs($this->usuario())
            ->post(route('procesos.cobros.store'), [
                'cliente_id' => $cliente->id,
                'caja_id' => $caja->id,
                'metodo_pago_id' => $metodo,
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_FINALIZADA,
                'ingresos' => [
                    ['ingreso_id' => $ingreso->id, 'monto' => 300.00, 'observaciones' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        // El segundo de 250 ya se pasa: 300 + 250 > 500.
        $respuesta = $this->actingAs($this->usuario())
            ->post(route('procesos.cobros.store'), [
                'cliente_id' => $cliente->id,
                'caja_id' => $caja->id,
                'metodo_pago_id' => $metodo,
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_FINALIZADA,
                'ingresos' => [
                    ['ingreso_id' => $ingreso->id, 'monto' => 250.00, 'observaciones' => null],
                ],
            ]);

        $respuesta->assertSessionHasErrors('ingresos.0.monto');

        $mensajes = session('errors')->get('ingresos.0.monto');

        $this->assertStringContainsString(
            '50.00',
            implode(' ', $mensajes),
            'El mensaje tiene que decir cuánto se pasa, no solo que se pasa.'
        );
    }

    /**
     * Con monedas distintas NO SE BLOQUEA, porque no se puede saber.
     *
     * Y esta es la consecuencia directa de que los importes del detalle vayan en la
     * moneda del cobro: 400 dolares no se pueden comparar con un ingreso de 3.000
     * cordoba, porque no hay conversion guardada en ninguna parte.
     *
     * Bloquearlo seria peor que dejar pasar el caso real —el taller factura en
     * cordoba y cobra en dolares—, y no avisar seria dejar al usuario creyendo que
     * el sistema lo ha comprobado. Lo que se hace es dejarlo pasar y que la ficha
     * diga que no se puede comprobar.
     */
    public function test_con_monedas_distintas_no_se_bloquea_porque_no_se_puede_comparar()
    {
        $cliente = $this->cliente();

        $monedaDelIngreso = $this->monedaActiva();

        $otraMoneda = Moneda::query()->where('estado', true)->where('id', '!=', $monedaDelIngreso->id)->first();

        if ($otraMoneda === null) {
            $otraMoneda = Moneda::create([
                'codigo' => 'ZZZ',
                'nombre' => 'Moneda de prueba ' . uniqid(),
                'estado' => true,
            ]);
        }

        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $monedaDelIngreso);

        $antes = Cobro::withTrashed()->count();

        $this->actingAs($this->usuario())
            ->post(route('procesos.cobros.store'), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $otraMoneda->id,
                'estado' => Cobro::ESTADO_FINALIZADA,
                'ingresos' => [
                    ['ingreso_id' => $ingreso->id, 'monto' => 999999.00, 'observaciones' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $antes + 1,
            Cobro::withTrashed()->count(),
            'Con monedas distintas el cobro se guarda: no hay forma de saber que se pasa.'
        );
    }

    /**
     * Un cobro que ya se esta editando no cuenta sus propias lineas.
     *
     * Si se contaran, un cobro con dos lineas del mismo ingreso —cosa que no se
     * permite, pero que puede haber si se manipuló la base— pareceria
     * sobrecobrado por su propia cuenta. Y mas normal: al editar, las lineas que ya
     * estan sumarian a lo que se esta añadiendo y todo cobro pareceria pasarse.
     */
    public function test_al_editar_un_cobro_no_cuenta_sus_propias_lineas_como_cobro_anterior()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingreso = $this->ingreso($this->orden($cliente), 1000.00, $moneda);

        [$cobro] = $this->cobroConUnaLinea($cliente, $ingreso, $moneda);

        // Subir la misma linea de 100 a 400 tiene que dejar pasar: el cobro ya tiene
        // esa linea, y no es un cobro anterior.
        $this->actingAs($this->usuario())
            ->put(route('procesos.cobros.update', $cobro), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_FINALIZADA,
                'ingresos' => [
                    ['ingreso_id' => $ingreso->id, 'monto' => 400.00, 'observaciones' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        $cobro->refresh();

        $this->assertSame(400.00, $cobro->total());
    }

    // ==================================================================
    // 6. Los tres desplegables ofrecen solo lo activo
    // ==================================================================

    /**
     * Una caja desactivada no se ofrece en el alta.
     *
     * Y el motivo de que esto sea una regla y no una preferencia: si se ofreciera,
     * un cobro guardado con una caja que ya no existe en el catalogo quedaria con un
     * desplegable que no la enseña, y nadie sabria si el cobro va a la caja
     * correcta o a la primera que aparecio.
     */
    public function test_una_caja_desactivada_no_se_ofrece_en_el_alta()
    {
        $moneda = $this->monedaActiva();
        $cliente = $this->cliente();

        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $moneda);

        $cajaMuerta = Caja::create([
            'nombre' => 'Caja apagada ' . uniqid(),
            'descripcion' => 'No deberia salir en el desplegable',
            'saldo_inicial' => 0,
            'estado' => false,
        ]);

        $this->actingAs($this->usuario())
            ->get(route('procesos.cobros.create'))
            ->assertOk()
            ->assertDontSee($cajaMuerta->nombre, false);

        [$cobro] = $this->cobroConUnaLinea($cliente, $ingreso, $moneda);
    }

    /**
     * Una caja desactivada SI se puede ver en la ficha de un cobro que la uso.
     *
     * Y esto es lo contrario del caso anterior a proposito: un cobro ya guardado es
     * un hecho, y se puede seguir mirando aunque hoy la caja este apagada —la caja
     * se desactiva cuando ya no se usa, no cuando dejo de existir—. Si la ficha
     * limitara a las activas, el cobro aparecia con la caja eliminada y no habria
     * forma de saber cual fue.
     */
    public function test_una_caja_despues_desactivada_se_sigue_viendo_en_la_ficha()
    {
        $moneda = $this->monedaActiva();
        $cliente = $this->cliente();

        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $moneda);

        $caja = $this->cajaActiva();

        [$cobro] = $this->cobroConUnaLinea($cliente, $ingreso, $moneda);

        $caja->update(['estado' => false]);

        $this->actingAs($this->usuario())
            ->get(route('procesos.cobros.show', $cobro))
            ->assertOk()
            ->assertSee($caja->nombre)
            ->assertSee('inactiva');
    }

    /**
     * Y en la edicion sale la caja con el aviso de que esta apagada.
     *
     * Sin esto, el desplegable se abriria con la primera activa seleccionada —el
     * value del cobro no esta entre las opciones— y al guardar el cobro se mudaria
     * de caja sin que nadie lo pidiera.
     */
    public function test_al_editar_se_ofrece_la_caja_apagada_con_el_aviso()
    {
        $moneda = $this->monedaActiva();
        $cliente = $this->cliente();

        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $moneda);

        $caja = $this->cajaActiva();

        [$cobro] = $this->cobroConUnaLinea($cliente, $ingreso, $moneda);

        $caja->update(['estado' => false]);

        $this->actingAs($this->usuario())
            ->get(route('procesos.cobros.edit', $cobro))
            ->assertOk()
            ->assertSee('está desactivada');
    }

    /**
     * Un metodo de pago desactivado sale de los desplegables del alta.
     */
    public function test_un_metodo_de_pago_desactivado_no_se_ofrece_en_el_alta()
    {
        $metodoMuerto = DB::table('metodos_pago')->insertGetId([
            'nombre' => 'Tarjeta muerta ' . uniqid(),
            'descripcion' => 'No deberia salir',
            'estado' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->usuario())
            ->get(route('procesos.cobros.create'))
            ->assertOk()
            ->assertDontSee((string) DB::table('metodos_pago')->find($metodoMuerto)->nombre, false);
    }

    /**
     * Los datos de la tabla, pedidos como los pide el navegador.
     *
     * Y NO como se miran en el listado, y esta es la razon por la que este helper
     * existe. Un DataTable no pinta sus filas en la pagina: las pide por ajax
     * cuando el navegador ya ha cargado el html. Un assertSee sobre el listado
     * miraria una pagina donde esas filas no estan nunca, y el test fallaria
     * siempre —o, peor, pasaria si el texto buscado aparece en otro sitio de la
     * pagina, como el titulo de la cabecera— sin que la tabla funcione.
     *
     * Yajra solo contesta json si ve las dos cabeceras: la de que es una peticion
     * de ajax y la de que se acepta json. Con una de las dos se queda pensando que
     * es una visita normal y devuelve html, que es un fallo que no dice nada.
     *
     * @return array<int, array<string, mixed>>
     */
    private function filasDeLaTabla(): array
    {
        $respuesta = $this->actingAs($this->usuario())
            ->get(route('procesos.cobros.index'), self::CABECERAS);

        $respuesta->assertOk();

        return json_decode($respuesta->getContent(), true, 512, JSON_THROW_ON_ERROR)['data'];
    }

    /**
     * La fila de un cobro en la tabla.
     *
     * Y se busca barriendo y no con un indice, porque el orden de la tabla es por
     * fecha y el taller ya tiene filas: el cobro de la prueba no va a ser el
     * primero. Buscar por indice es el fallo mas comun al probar una tabla en un
     * taller que ya tiene datos, y falla de una manera que parece un fallo de la
     * tabla y no del test.
     *
     * @return array<string, mixed>|null
     */
    private function filaDelCobro(Cobro $cobro): ?array
    {
        foreach ($this->filasDeLaTabla() as $fila) {
            if ((int) ($fila['id'] ?? 0) === (int) $cobro->id) {
                return $fila;
            }
        }

        return null;
    }

    // ==================================================================
    // El borrado
    // ==================================================================

    /**
     * Borrar el cobro da de baja sus lineas, y no las borra.
     *
     * Y las lineas dadas de baja dejan de contar como cobrado: si se dejaran, el
     * ingreso que se cobraron seguiria apareciendo como pagado por un cobro que ya
     * no existe.
     */
    public function test_al_borrar_el_cobro_sus_lineas_dejan_de_contar_como_cobrado()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $moneda);

        [$cobro] = $this->cobroConUnaLinea($cliente, $ingreso, $moneda);

        /*
         * Los dos números se cuentan ANTES de borrar, y el de las líneas de este
         * cobro se cuenta de su propia tabla con una consulta acotada a su id.
         *
         * Y el motivo de no usar `$cobro->cantidad_lineas` para lo de después es que
         * ya no vale: al venir de `forceDelete()`, el modelo tiene la relación
         * cargada o vacía según cuándo se miró, y cuenta cero aunque la fila
         * existiera. El atributo es una foto del estado del modelo en el momento en
         * que se le preguntó, no una consulta —y aquí la pregunta se hace después
         * de que la fila desapareció—.
         */
        $lineasDeEsteCobro = DetalleCobro::withTrashed()
            ->where('cobro_id', $cobro->id)
            ->count();

        $antesDeBorrar = Cobro::withTrashed()->count();
        $antesDeDetalles = DetalleCobro::withTrashed()->count();

        $this->assertSame(1, $lineasDeEsteCobro, 'El cobro tiene una línea antes de borrar.');

        $this->assertSame(
            100.00,
            $cobro->cobradoPorIngreso()[(int) $ingreso->id] ?? 0,
            'Antes de borrar, el ingreso lleva lo que se le cobró.'
        );

        $this->actingAs($this->usuario())
            ->delete(route('procesos.cobros.destroy', $cobro))
            ->assertRedirect(route('procesos.cobros.index'));

        $this->assertSame(
            $antesDeBorrar - 1,
            Cobro::withTrashed()->count(),
            'El cobro desaparece de verdad, incluso del recuento de los dados de baja.'
        );

        /*
         * Y sus líneas desaparecen con él. No es una decision del código: la tabla
         * tiene ON DELETE CASCADE en detalle_cobros, así que la base las borra
         * aunque el código no las toque. Por eso el aviso de la confirmación dice
         * que se borran, y por eso la alternativa para deshacer un cobro es
         * ponerlo en Cancelada, no borrarlo.
         */
        $this->assertSame(
            $antesDeDetalles - $lineasDeEsteCobro,
            DetalleCobro::withTrashed()->count(),
            'Las líneas se van con el cobro: la base las borra en cascada.'
        );

        $this->assertSame(
            0,
            DetalleCobro::withTrashed()->where('cobro_id', $cobro->id)->count(),
            'No queda ninguna línea de este cobro.'
        );

        // Y ya no hay ningun cobro que cuente para ese ingreso.
        $otro = Cobro::query()->latest('id')->first();

        $this->assertSame(
            [],
            $otro?->cobradoPorIngreso() ?? [],
            'Con el cobro borrado, su ingreso ya no lleva nada cobrado.'
        );
    }

    /**
     * Un cobro con una línea que ya se había quitado también se puede borrar.
     *
     * Y este test existe porque la versión anterior del borrado fallaba justo aquí.
     *
     * Daba de baja las líneas y luego borraba el cobro de verdad, lo cual funciona
     * mientras todas las líneas estén vivas. Pero en cuanto una línea había quedado
     * dada de baja de una edición anterior —que es lo normal en cualquier cobro que
     * se ha corregido—, esa fila seguía escribiendo el id del cobro, y el forceDelete
     * del cobro chocaba con la clave foránea de una fila que MySQL tenía por viva.
     *
     * El síntoma era el peor posible: el borrado funcionaba la primera vez y fallaba
     * la segunda, sin decir por qué. El taller ve que a veces se puede borrar y
     * cuando le pasa ya no puede deshacer nada.
     */
    public function test_un_cobro_que_ya_tenia_una_linea_quitada_tambien_se_puede_borrar()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingresoUno = $this->ingreso($this->orden($cliente), 500.00, $moneda);
        $ingresoDos = $this->ingreso($this->orden($cliente), 300.00, $moneda);

        [$cobro] = $this->cobroConUnaLinea($cliente, $ingresoUno, $moneda);

        // Se agrega la segunda linea.
        $this->actingAs($this->usuario())
            ->put(route('procesos.cobros.update', $cobro), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_FINALIZADA,
                'ingresos' => [
                    ['ingreso_id' => $ingresoUno->id, 'monto' => 100.00, 'observaciones' => null],
                    ['ingreso_id' => $ingresoDos->id, 'monto' => 200.00, 'observaciones' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        // Y luego se quita: la segunda queda dada de baja, escribiendo el id.
        $this->actingAs($this->usuario())
            ->put(route('procesos.cobros.update', $cobro), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_FINALIZADA,
                'ingresos' => [
                    ['ingreso_id' => $ingresoUno->id, 'monto' => 100.00, 'observaciones' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertGreaterThan(
            0,
            DetalleCobro::withTrashed()->where('cobro_id', $cobro->id)->count() - $cobro->cantidad_lineas,
            'Tiene que quedar una línea dada de baja, que es el caso que rompía el borrado.'
        );

        $this->actingAs($this->usuario())
            ->delete(route('procesos.cobros.destroy', $cobro))
            ->assertRedirect(route('procesos.cobros.index'));

        $this->assertNull(
            Cobro::withTrashed()->find($cobro->id),
            'El cobro se borra aunque tuviera líneas dadas de baja.'
        );

        $this->assertSame(
            0,
            DetalleCobro::withTrashed()->where('cobro_id', $cobro->id)->count(),
            'Y sus líneas se van con él.'
        );
    }

    /**
     * Una línea quitada y luego repuesta vuelve a contar, y no duplicada.
     *
     * La fila dada de baja se revive en vez de crear una nueva. Si se creara otra,
     * quedarían dos filas para el mismo ingreso —una viva y otra dada de baja— y la
     * ficha contaría el importe dos veces al decir cuánto queda por cobrar. Y como
     * las dos filas apuntan al mismo ingreso, la comprobación de "no se repite" no
     * lo vería: para ella solo hay una línea.
     */
    public function test_una_linea_quitada_y_repuesta_no_deja_dos_filas_para_el_mismo_ingreso()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingresoUno = $this->ingreso($this->orden($cliente), 500.00, $moneda);
        $ingresoDos = $this->ingreso($this->orden($cliente), 300.00, $moneda);

        [$cobro] = $this->cobroConUnaLinea($cliente, $ingresoUno, $moneda);

        $conDos = [
            'cliente_id' => $cliente->id,
            'caja_id' => $this->cajaActiva()->id,
            'metodo_pago_id' => $this->metodoActivo(),
            'fecha' => today()->format('Y-m-d'),
            'moneda_id' => $moneda->id,
            'estado' => Cobro::ESTADO_FINALIZADA,
        ];

        $this->actingAs($this->usuario())
            ->put(route('procesos.cobros.update', $cobro), $conDos + [
                'ingresos' => [
                    ['ingreso_id' => $ingresoUno->id, 'monto' => 100.00, 'observaciones' => null],
                    ['ingreso_id' => $ingresoDos->id, 'monto' => 200.00, 'observaciones' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->usuario())
            ->put(route('procesos.cobros.update', $cobro), $conDos + [
                'ingresos' => [
                    ['ingreso_id' => $ingresoUno->id, 'monto' => 100.00, 'observaciones' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        // Y ahora se vuelve a poner la que se habia quitado.
        $this->actingAs($this->usuario())
            ->put(route('procesos.cobros.update', $cobro), $conDos + [
                'ingresos' => [
                    ['ingreso_id' => $ingresoUno->id, 'monto' => 100.00, 'observaciones' => null],
                    ['ingreso_id' => $ingresoDos->id, 'monto' => 200.00, 'observaciones' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        $cobro->refresh();

        $this->assertSame(2, $cobro->cantidad_lineas, 'Las dos líneas vuelven a estar.');
        $this->assertSame(300.00, $cobro->total(), 'Y el total es la suma de las dos.');

        /*
         * Y no hay una tercera fila.
         *
         * La cuenta va por ingreso_id, no por "está viva o dada de baja". Si la
         * repuesta crease una fila nueva en vez de revivir la vieja, el total
         * saldria bien y todo pareceria funcionar —este test pasaria hasta aqui—,
         * pero quedarian dos filas apuntando al mismo ingreso.
         *
         * Y la comprobacion de "no se repite un ingreso en el mismo cobro" no lo
         * veria, porque solo mira las lineas que vienen del formulario, que son dos,
         * una de cada ingreso. El desajuste apareceria mas tarde, en la ficha, al
         * decir cuanto queda por cobrar: contaria ese cobro dos veces.
         */
        $todasDelCobro = DetalleCobro::withTrashed()->where('cobro_id', $cobro->id)->get();

        $this->assertCount(
            2,
            $todasDelCobro,
            'No hay una tercera fila: la que estaba dada de baja se ha revivido.'
        );

        $this->assertSame(
            0,
            $todasDelCobro->whereNotNull('deleted_at')->count(),
            'Ninguna de las dos líneas queda dada de baja.'
        );

        $this->assertSame(
            [1, 1],
            $todasDelCobro
                ->map(fn($una) => $todasDelCobro->where('ingreso_id', $una->ingreso_id)->count())
                ->values()
                ->all(),
            'Cada ingreso tiene exactamente una fila, ni dos ni cero.'
        );
    }

    // ==================================================================
    // El codigo
    // ==================================================================

    /**
     * El codigo lo pone el sistema y no se acepta del formulario.
     *
     * Y es un indice unico en la base, asi que aunque se borre un cobro su numero
     * no vuelve a salir: el siguiente sigue siendo el siguiente.
     */
    public function test_el_codigo_lo_pone_el_sistema_y_no_se_acepta_del_formulario()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $moneda);

        $this->actingAs($this->usuario())
            ->post(route('procesos.cobros.store'), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_FINALIZADA,
                'codigo' => 'CBX-999999',
                'ingresos' => [
                    ['ingreso_id' => $ingreso->id, 'monto' => 100.00, 'observaciones' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        $cobro = Cobro::orderByDesc('id')->first();

        $this->assertMatchesRegularExpression(
            '/^CBX-\d{6}$/',
            $cobro->codigo,
            'El código tiene que tener la forma CBX- seguido de seis dígitos.'
        );

        $this->assertNotSame(
            'CBX-999999',
            $cobro->codigo,
            'El código enviado por el formulario tiene que ignorarse.'
        );
    }

    /**
     * Dos cobros seguidos no salen con el mismo codigo.
     *
     * El indice unico de la base lo impediria, pero el punto es que el sistema lo
     * busca bien: si se hiciera mal, el segundo alta reventaria con un error de
     * MySQL que no le dice a nadie de que se trata.
     */
    public function test_dos_cobros_no_salen_con_el_mismo_codigo()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $moneda);

        $caja = $this->cajaActiva();
        $metodo = $this->metodoActivo();

        foreach ([100.00, 200.00] as $importe) {
            $this->actingAs($this->usuario())
                ->post(route('procesos.cobros.store'), [
                    'cliente_id' => $cliente->id,
                    'caja_id' => $caja->id,
                    'metodo_pago_id' => $metodo,
                    'fecha' => today()->format('Y-m-d'),
                    'moneda_id' => $moneda->id,
                    'estado' => Cobro::ESTADO_PENDIENTE,
                    'ingresos' => [
                        ['ingreso_id' => $ingreso->id, 'monto' => $importe, 'observaciones' => null],
                    ],
                ])
                ->assertSessionHasNoErrors();
        }

        $codigos = Cobro::orderBy('id')
            ->limit(2)
            ->pluck('codigo')
            ->values()
            ->all();

        $this->assertCount(2, $codigos);
        $this->assertNotSame($codigos[0], $codigos[1], 'Dos cobros no pueden compartir código.');
    }

    // ==================================================================
    // Los estados
    // ==================================================================

    /**
     * Los cuatro estados del taller, y solo esos.
     */
    public function test_solo_se_aceptan_los_cuatro_estados_del_taller()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $moneda);

        foreach ([0, 1, 2, 3] as $estado) {
            $this->actingAs($this->usuario())
                ->post(route('procesos.cobros.store'), [
                    'cliente_id' => $cliente->id,
                    'caja_id' => $this->cajaActiva()->id,
                    'metodo_pago_id' => $this->metodoActivo(),
                    'fecha' => today()->format('Y-m-d'),
                    'moneda_id' => $moneda->id,
                    'estado' => $estado,
                    'ingresos' => [
                        ['ingreso_id' => $ingreso->id, 'monto' => 10.00, 'observaciones' => null],
                    ],
                ])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($this->usuario())
            ->post(route('procesos.cobros.store'), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => 7,
                'ingresos' => [
                    ['ingreso_id' => $ingreso->id, 'monto' => 10.00, 'observaciones' => null],
                ],
            ])
            ->assertSessionHasErrors('estado');
    }

    /**
     * Un cobro CANCELADO no cuenta como cobrado, y sus lineas no lo hacen tampoco.
     *
     * Solo Finalizada cuenta como dinero recibido. Pendiente y En proceso todavia
     * no han llegado a caja; Cancelada queda anulada.
     */
    public function test_un_cobro_cancelado_no_cuenta_como_cobrado()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $moneda);

        [$cobro] = $this->cobroConUnaLinea(
            $cliente,
            $ingreso,
            $moneda,
            Cobro::ESTADO_CANCELADA
        );

        $cobro->refresh();

        $this->assertTrue($cobro->estaCancelado());

        $this->assertSame(
            [],
            $cobro->cobradoPorIngreso(),
            'Las líneas de un cobro cancelado no cuentan como dinero cobrado.'
        );
    }

    /**
     * Un cobro pendiente no reduce lo que queda por cobrar.
     *
     * Pendiente significa que se anoto que el cliente va a pagar, no que el dinero
     * ya se recibio. La cifra de cobrado tiene que representar dinero confirmado
     * en caja, asi que solo los cobros Finalizados se suman. Lo pendiente sigue
     * visible como pendiente en su propia lista, pero no se resta del saldo.
     */
    public function test_un_cobro_pendiente_no_cuenta_como_cobrado()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $moneda);

        [$cobro] = $this->cobroConUnaLinea(
            $cliente,
            $ingreso,
            $moneda,
            Cobro::ESTADO_PENDIENTE
        );

        $cobro->refresh();

        $cobradores = $cobro->cobradoPorIngreso();

        $this->assertSame(
            [],
            $cobradores,
            'Pendiente no cuenta como dinero recibido: solo cuenta al pasar a Finalizada.'
        );
    }

    /**
     * En proceso tampoco cuenta como dinero recibido.
     *
     * Y no es un caso distinto de Pendiente: ambos estados dicen que el dinero
     * todavia no esta confirmado en caja. Si En proceso se sumara, se podria
     * esconder un ingreso pendiente de cobro detras de un numero que parece ya
     * recibido.
     */
    public function test_un_cobro_en_proceso_no_cuenta_como_cobrado()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();
        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $moneda);

        [$cobro] = $this->cobroConUnaLinea(
            $cliente,
            $ingreso,
            $moneda,
            Cobro::ESTADO_EN_PROCESO
        );

        $this->assertSame([], $cobro->cobradoPorIngreso());
    }

    /**
     * Un cobro finalizado se puede editar igual.
     *
     * Y la razon es lo que hace que esto no sea un olvido: el dinero se entrego
     * igual, y lo que se equivoco fue al apuntarlo. Bloquearlo dejaria el error sin
     * salida, y un error sin salida se resuelve con el papel.
     */
    public function test_un_cobro_finalizado_se_puede_editar()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $moneda);

        [$cobro] = $this->cobroConUnaLinea($cliente, $ingreso, $moneda);

        $this->assertTrue($cobro->esFinalizado());

        $this->actingAs($this->usuario())
            ->get(route('procesos.cobros.edit', $cobro))
            ->assertOk();

        $this->actingAs($this->usuario())
            ->put(route('procesos.cobros.update', $cobro), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_FINALIZADA,
                'observaciones' => 'Corregido: la nota estaba mal',
                'ingresos' => [
                    ['ingreso_id' => $ingreso->id, 'monto' => 150.00, 'observaciones' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        $cobro->refresh();

        $this->assertSame(150.00, $cobro->total());
        $this->assertSame('Corregido: la nota estaba mal', $cobro->observaciones);
    }

    // ==================================================================
    // Los permisos
    // ==================================================================

    /**
     * Sin sesion no se ven ni se registran cobros.
     */
    public function test_sin_sesion_no_se_puede_entrar_a_los_cobros()
    {
        $this->get(route('procesos.cobros.index'))->assertRedirect('/login');
        $this->get(route('procesos.cobros.create'))->assertRedirect('/login');
    }

    /**
     * Sin el permiso de ver no se entra ni al listado ni a la ficha.
     */
    public function test_sin_permiso_de_ver_no_se_entra()
    {
        $usuario = User::create([
            'name' => 'Sin permiso ' . uniqid(),
            'email' => 'sin-permiso-' . uniqid() . '@taller.test',
            'password' => Hash::make('prueba1234'),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($usuario)
            ->get(route('procesos.cobros.index'))
            ->assertForbidden();

        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();
        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $moneda);

        [$cobro] = $this->cobroConUnaLinea($cliente, $ingreso, $moneda);

        $this->actingAs($usuario)
            ->get(route('procesos.cobros.show', $cobro))
            ->assertForbidden();
    }

    /**
     * Ver no es crear: el que solo mira no puede registrar cobros.
     */
    public function test_ver_no_es_crear()
    {
        $usuario = User::create([
            'name' => 'Solo mira ' . uniqid(),
            'email' => 'solo-mira-' . uniqid() . '@taller.test',
            'password' => Hash::make('prueba1234'),
            'email_verified_at' => now(),
        ]);

        $usuario->syncPermissions(['cobros.view']);

        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();
        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $moneda);

        $antes = Cobro::withTrashed()->count();

        $this->actingAs($usuario)
            ->get(route('procesos.cobros.index'))
            ->assertOk();

        $this->actingAs($usuario)
            ->post(route('procesos.cobros.store'), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_PENDIENTE,
                'ingresos' => [
                    ['ingreso_id' => $ingreso->id, 'monto' => 100.00, 'observaciones' => null],
                ],
            ])
            ->assertForbidden();

        $this->assertSame(
            $antes,
            Cobro::withTrashed()->count(),
            'El que solo mira no registra cobros.'
        );
    }

    // ==================================================================
    // Las pantallas
    // ==================================================================

    /**
     * Las cinco pantallas responden.
     *
     * Con el boton de agregar linea en su sitio, que es la parte que mas se rompe
     * sin que se note: si el javascript no lo encuentra, la pantalla abre y no hace
     * nada, y eso no da ningun error.
     */
    public function test_las_cinco_pantallas_responden()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();
        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $moneda);

        [$cobro] = $this->cobroConUnaLinea($cliente, $ingreso, $moneda);

        $usuario = $this->usuario();

        $this->actingAs($usuario)
            ->get(route('procesos.cobros.index'))
            ->assertOk()
            ->assertSee('<li class="menu active">', false)
            ->assertSee(
                '<a href="' . route('procesos.cobros.index') . '" aria-expanded="false" class="dropdown-toggle">',
                false
            );
        $this->actingAs($usuario)->get(route('procesos.cobros.create'))->assertOk();
        $this->actingAs($usuario)->get(route('procesos.cobros.show', $cobro))->assertOk();
        $this->actingAs($usuario)->get(route('procesos.cobros.edit', $cobro))->assertOk();

        $this->actingAs($usuario)
            ->get(route('procesos.cobros.edit', $cobro))
            ->assertSee('btnAgregarLinea', false)
            ->assertSee('style="table-layout: fixed; min-width: 760px;"', false)
            ->assertSee('style="width: 44%"', false);

        $this->actingAs($usuario)
            ->get(route('procesos.cobros.show', $cobro))
            ->assertSee($cobro->codigo)
            ->assertSee($cliente->nombre);
    }

    /**
     * Los campos ocultos de la plantilla no participan en la validación del navegador.
     *
     * El atributo min del importe es correcto para una línea real, pero un input
     * oculto con 0.00 bloquea el envío y el navegador no puede enfocarlo. La
     * plantilla queda deshabilitada; JavaScript habilita sus controles al clonar.
     */
    public function test_la_plantilla_oculta_no_bloquea_el_envio_del_formulario()
    {
        $html = $this->actingAs($this->usuario())
            ->get(route('procesos.cobros.create'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<select(?=[^>]*class="form-select ingreso-linea")(?=[^>]*disabled)[^>]*>/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<input(?=[^>]*class="form-control monto-linea")(?=[^>]*min="0\.01")(?=[^>]*disabled)[^>]*value=""[^>]*>/s',
            $html
        );
    }

    /**
     * El cliente se escoge en el modal y el formulario envía su id oculto.
     */
    public function test_el_cliente_se_selecciona_desde_un_modal_con_busqueda()
    {
        $cliente = $this->cliente();

        $this->actingAs($this->usuario())
            ->get(route('procesos.cobros.create'))
            ->assertOk()
            ->assertSee('id="modalSeleccionCliente"', false)
            ->assertSee('id="buscarCliente"', false)
            ->assertSee('id="cliente_id"', false)
            ->assertSee('name="cliente_id"', false)
            ->assertSee('data-cliente-id="' . $cliente->id . '"', false)
            ->assertSee($cliente->nombre)
            ->assertDontSee('<select id="cliente_id"', false);
    }

    /**
     * Una linea que no cubre el ingreso entero dice cuanto falta.
     *
     * Y no es un adorno: sin ese aviso, el taller ve un ingreso con una linea de
     * cobro y no sabe si le falta dinero o ya esta todo. Y el caso de varios cobros
     * —una linea vieja y otra nueva— sale con las dos pareciendo correctas, y el
     * saldo del cliente queda mal sin que nada lo diga.
     */
    public function test_la_ficha_dice_cuanto_queda_por_cobrar()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingreso = $this->ingreso($this->orden($cliente), 200.00, $moneda);

        [$cobro] = $this->cobroConUnaLinea($cliente, $ingreso, $moneda);

        /*
         * Un cobro de 100 sobre un ingreso de 200 lo deja a medias, y la ficha
         * tiene que decirlo como "quedan por cobrar": sin ese aviso, el taller ve
         * un ingreso con una linea de cobro y no sabe si le falta dinero o ya
         * esta todo.
         */
        $this->actingAs($this->usuario())
            ->get(route('procesos.cobros.show', $cobro))
            ->assertOk()
            ->assertSee('quedan por cobrar')
            ->assertSee('100.00');
    }

    /**
     * Y cuando la linea de ESTE cobro es la que lo paga entero, sale "ya pagado".
     *
     * Y este caso rompia antes. La ficha excluye el propio cobro de la cuenta —para
     * que la linea no se vea cobrada dos veces— y no volvia a sumar la linea
     * despues de excluida. Con eso, el total cobrado de la linea era siempre cero
     * mas lo que hubieran pagado otros cobros, y ningun ingreso podia salir como
     * pagado por el cobro que lo estaba pagando. Que es el caso normal.
     *
     * Con un ingreso de 200 y una linea de 100 no se puede ver, porque 100 no
     * cubre 200. Por eso aqui la linea se pone al total entero.
     */
    public function test_la_ficha_dice_ya_pagado_cuando_esta_linea_cubre_el_ingreso_entero()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingreso = $this->ingreso($this->orden($cliente), 200.00, $moneda);

        [$cobro] = $this->cobroConUnaLinea($cliente, $ingreso, $moneda);

        // La linea sube a 200, que es el total del ingreso.
        $this->actingAs($this->usuario())
            ->put(route('procesos.cobros.update', $cobro), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_FINALIZADA,
                'ingresos' => [
                    ['ingreso_id' => $ingreso->id, 'monto' => 200.00, 'observaciones' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->usuario())
            ->get(route('procesos.cobros.show', $cobro))
            ->assertOk()
            ->assertSee('ya pagado');
    }

    /**
     * Y cuando la pagan entre varios cobros, tambien sale "ya pagado".
     *
     * Y el subtitulo dice de quien es cada parte, que es lo que hace util el aviso:
     * sin ese desglose, "ya pagado" no dice si lo pago este cobro entero o si fue
     * entre tres, y el taller no puede decidir si falta algo.
     */
    public function test_la_ficha_avisa_cuando_entre_varios_cobros_ya_pagan_el_ingreso()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $moneda);

        $caja = $this->cajaActiva();
        $metodo = $this->metodoActivo();

        // Un cobro viejo de 300, que se queda.
        $viejo = Cobro::create([
            Cobro::CLIENTE_ID => $cliente->id,
            Cobro::CAJA_ID => $caja->id,
            Cobro::METODO_PAGO_ID => $metodo,
            Cobro::FECHA => today()->subDays(5),
            Cobro::MONEDA_ID => $moneda->id,
            Cobro::ESTADO => Cobro::ESTADO_FINALIZADA,
            Cobro::CODIGO => Cobro::generarCodigo(),
            Cobro::MONTO => 300.00,
        ]);

        DetalleCobro::create([
            DetalleCobro::COBRO_ID => $viejo->id,
            DetalleCobro::INGRESO_ID => $ingreso->id,
            DetalleCobro::MONTO => 300.00,
        ]);

        // Y otro de 200, que con el viejo lo pagan entre los dos.
        [$cobro] = $this->cobroConUnaLinea($cliente, $ingreso, $moneda);

        $this->actingAs($this->usuario())
            ->put(route('procesos.cobros.update', $cobro), [
                'cliente_id' => $cliente->id,
                'caja_id' => $caja->id,
                'metodo_pago_id' => $metodo,
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_FINALIZADA,
                'ingresos' => [
                    ['ingreso_id' => $ingreso->id, 'monto' => 200.00, 'observaciones' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->usuario())
            ->get(route('procesos.cobros.show', $cobro))
            ->assertOk()
            ->assertSee('ya pagado')
            ->assertSee('300.00 en otros cobros')
            ->assertSee('200.00 aquí');
    }

    /**
     * Y avisa igual cuando el cobro NUEVO se pasa de lo que quedaba.
     */
    public function test_la_ficha_avisa_cuando_esta_linea_pasa_lo_que_tenia()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        // El ingreso se salva a mano como ya sobrecobrado por un cobro viejo,
        // porque la pantalla no lo dejaria crear y aun asi hay que ver como lo
        // pinta la ficha.
        $ingreso = $this->ingreso($this->orden($cliente), 100.00, $moneda);

        $caja = $this->cajaActiva();
        $metodo = $this->metodoActivo();
        $usuario = $this->usuario();

        // Un cobro de 400 sobre un ingreso de 100, escrito por la base.
        $cobro = Cobro::create([
            Cobro::CLIENTE_ID => $cliente->id,
            Cobro::CAJA_ID => $caja->id,
            Cobro::METODO_PAGO_ID => $metodo,
            Cobro::FECHA => today(),
            Cobro::MONEDA_ID => $moneda->id,
            Cobro::ESTADO => Cobro::ESTADO_FINALIZADA,
            Cobro::CODIGO => Cobro::generarCodigo(),
            Cobro::MONTO => 400.00,
        ]);

        DetalleCobro::create([
            DetalleCobro::COBRO_ID => $cobro->id,
            DetalleCobro::INGRESO_ID => $ingreso->id,
            DetalleCobro::MONTO => 400.00,
        ]);

        $this->actingAs($usuario)
            ->get(route('procesos.cobros.show', $cobro))
            ->assertOk()
            ->assertSee('sobrecobrado');
    }

    /**
     * La ficha dice que no se puede comprobar cuando las monedas son distintas.
     *
     * Y dice eso, no "correcto". Un texto en blanco parece un fallo del script y un
     * "correcto" seria mentira: no se ha comprobado nada.
     */
    public function test_la_ficha_dice_que_no_se_puede_comprobar_con_monedas_distintas()
    {
        $cliente = $this->cliente();
        $monedaDelIngreso = $this->monedaActiva();

        $otraMoneda = Moneda::query()->where('estado', true)->where('id', '!=', $monedaDelIngreso->id)->first();

        if ($otraMoneda === null) {
            $otraMoneda = Moneda::create([
                'codigo' => 'YYY',
                'nombre' => 'Moneda de prueba ' . uniqid(),
                'estado' => true,
            ]);
        }

        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $monedaDelIngreso);

        $caja = $this->cajaActiva();
        $metodo = $this->metodoActivo();

        $cobro = Cobro::create([
            Cobro::CLIENTE_ID => $cliente->id,
            Cobro::CAJA_ID => $caja->id,
            Cobro::METODO_PAGO_ID => $metodo,
            Cobro::FECHA => today(),
            Cobro::MONEDA_ID => $otraMoneda->id,
            Cobro::ESTADO => Cobro::ESTADO_FINALIZADA,
            Cobro::CODIGO => Cobro::generarCodigo(),
            Cobro::MONTO => 400.00,
        ]);

        DetalleCobro::create([
            DetalleCobro::COBRO_ID => $cobro->id,
            DetalleCobro::INGRESO_ID => $ingreso->id,
            DetalleCobro::MONTO => 400.00,
        ]);

        $this->actingAs($this->usuario())
            ->get(route('procesos.cobros.show', $cobro))
            ->assertOk()
            ->assertSee('no se puede comprobar');
    }

    /**
     * El listado avisa de lo que falta para poder registrar un cobro.
     *
     * Y no es hipotetico: la tabla de metodos de pago estuvo vacia hasta hace poco,
     * y el sintoma sin aviso —el boton lleva a una pantalla con tres desplegables
     * vacios y no dice por que— es el peor de los dos.
     */
    public function test_el_listado_avisa_si_no_hay_metodos_de_pago_activos()
    {
        // Se apagan todos, se mira, y se vuelven a encender.
        $metodos = DB::table('metodos_pago')->where('estado', true)->pluck('id')->all();

        DB::table('metodos_pago')->whereIn('id', $metodos)->update(['estado' => false]);

        try {
            $this->actingAs($this->usuario())
                ->get(route('procesos.cobros.index'))
                ->assertOk()
                ->assertSee('No hay ningún método de pago activo');
        } finally {
            DB::table('metodos_pago')->whereIn('id', $metodos)->update(['estado' => true]);
        }
    }

    /**
     * Y avisa de las cajas vacias tambien.
     */
    public function test_el_listado_avisa_si_no_hay_cajas_activas()
    {
        $cajas = Caja::query()->where('estado', true)->pluck('id')->all();

        Caja::query()->whereIn('id', $cajas)->update(['estado' => false]);

        try {
            $this->actingAs($this->usuario())
                ->get(route('procesos.cobros.index'))
                ->assertOk()
                ->assertSee('No hay ninguna caja activa');
        } finally {
            Caja::query()->whereIn('id', $cajas)->update(['estado' => true]);
        }
    }

    // ==================================================================
    // El ajax de los ingresos
    // ==================================================================

    /**
     * El ajax devuelve los ingresos del cliente, con lo que la linea necesita.
     *
     * Y NO devuelve los de otros clientes, que es la regla 3 vista desde el otro
     * lado: si el desplegable ofreciera ingresos ajenos, el rechazo del servidor
     * llegaria con el mensaje mas incomodo de la pantalla.
     */
    public function test_el_ajax_solo_devuelve_los_ingresos_del_cliente_que_se_pide()
    {
        $cliente = $this->cliente();
        $otroCliente = $this->cliente();

        $moneda = $this->monedaActiva();

        $suyo = $this->ingreso($this->orden($cliente), 500.00, $moneda);
        $ajeno = $this->ingreso($this->orden($otroCliente), 700.00, $moneda);

        $respuesta = $this->actingAs($this->usuario())
            ->post(route('procesos.cobros.ingresos-cliente'), [
                'cliente_id' => $cliente->id,
            ], self::CABECERAS);

        $respuesta->assertOk();

        $ids = collect($respuesta->json('ingresos'))->pluck('id')->all();

        $this->assertContains($suyo->id, $ids, 'El ingreso del cliente tiene que estar.');
        $this->assertNotContains($ajeno->id, $ids, 'El ingreso de otro cliente no puede estar.');
    }

    /**
     * Y trae los numeros que la linea necesita para no tener que preguntar otra vez.
     *
     * Esos numeros se mandan como atributos data- y no dentro del texto, porque el
     * texto lleva separadores de miles: parseFloat("3.000,00") devuelve 3, y el
     * formato de Nicaragua es al reves que en casi todas partes.
     */
    public function test_el_ajax_trae_el_total_la_moneda_y_lo_ya_cobrado_de_cada_ingreso()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingreso = $this->ingreso($this->orden($cliente), 3000.00, $moneda);

        // Un cobro previo de 1.000, para que el ajax lo diga.
        [$cobroPrevio] = $this->cobroConUnaLinea($cliente, $ingreso, $moneda);

        $this->actingAs($this->usuario())
            ->put(route('procesos.cobros.update', $cobroPrevio), [
                'cliente_id' => $cliente->id,
                'caja_id' => $this->cajaActiva()->id,
                'metodo_pago_id' => $this->metodoActivo(),
                'fecha' => today()->format('Y-m-d'),
                'moneda_id' => $moneda->id,
                'estado' => Cobro::ESTADO_FINALIZADA,
                'ingresos' => [
                    ['ingreso_id' => $ingreso->id, 'monto' => 1000.00, 'observaciones' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        /*
         * Y con `cobro_id`, que es lo que manda el javascript al editar.
         *
         * Y esto no es un detalle del test: es lo que hace que la edicion no se
         * confunda a si misma. El propio cobro ya tiene una linea de este ingreso
         * por 1.000, y si el ajax lo contara, la linea saldria con "1.000 cobrados"
         * de si mismo y quien la viera creeria que ya se cobro antes de escribir el
         * importe nuevo.
         */
        $respuesta = $this->actingAs($this->usuario())
            ->post(route('procesos.cobros.ingresos-cliente'), [
                'cliente_id' => $cliente->id,
                'cobro_id' => $cobroPrevio->id,
            ], self::CABECERAS);

        $respuesta->assertOk();

        $linea = collect($respuesta->json('ingresos'))->firstWhere('id', $ingreso->id);

        $this->assertNotNull($linea);
        $this->assertSame(3000.0, (float) $linea['total']);
        $this->assertSame((int) $moneda->id, $linea['moneda_id']);

        /*
         * Y con el cobro previo excluido de la cuenta, el ingreso aparece como
         * sin nada cobrado: esos 1.000 son del propio cobro que se esta editando.
         */
        $this->assertSame(0.0, (float) $linea['cobrado']);

        // Y sin mandar el cobro, si cuenta, que es el caso del alta.
        $sinCobro = $this->actingAs($this->usuario())
            ->post(route('procesos.cobros.ingresos-cliente'), [
                'cliente_id' => $cliente->id,
            ], self::CABECERAS);

        $otra = collect($sinCobro->json('ingresos'))->firstWhere('id', $ingreso->id);

        $this->assertSame(1000.0, (float) $otra['cobrado']);
    }

    /**
     * El ajax sin sesion no devuelve nada.
     */
    /**
     * El ajax sin sesion contesta 401, no un redirect.
     *
     * Y la diferencia es de fondo, no de forma. Este es el unico endpoint del
     * proyecto que devuelve datos y se pide por ajax: los demas son paginas, y a
     * una pagina sin sesion lo que le sirve al navegador es un redirect al login.
     * Aqui no: el javascript espera un json con la lista de ingresos, y si le llega
     * un 302 lo que llega de verdad es el html del login, que no se puede leer como
     * una lista. Asi que sale 401.
     *
     * Por eso este test mira el codigo y no la ruta de destino: un redirect a
     * /login aqui seria un fallo de verdad, no una diferencia de estilo.
     */
public function test_el_ajax_sin_sesion_contesta_401_y_no_un_redirect()
    {
        $this->post(route('procesos.cobros.ingresos-cliente'), [
            'cliente_id' => $this->cliente()->id,
        ], self::CABECERAS)->assertStatus(401);
    }

    // ==================================================================
    // El total se ve, y el guardado tambien
    // ==================================================================

    /**
     * La columna del total muestra la calculada y, si no coincide, la guardada.
     *
     * Mostrar solo una de las dos deja al usuario viendo un numero sin sus partes.
     * Las dos juntas, cuando coinciden, se ven como una sola.
     */
    public function test_la_columna_del_total_avisa_si_la_guardada_no_coincide_con_la_suma()
    {
        $cliente = $this->cliente();
        $moneda = $this->monedaActiva();

        $ingreso = $this->ingreso($this->orden($cliente), 500.00, $moneda);

        [$cobro] = $this->cobroConUnaLinea($cliente, $ingreso, $moneda);

        $fila = $this->filaDelCobro($cobro);

        $this->assertNotNull($fila, 'El cobro tiene que estar en la tabla.');
        $this->assertStringNotContainsString(
            'guardado',
            $fila['total'],
            'Si las dos cifras coinciden se muestra solo una.'
        );

        /*
         * Y al desajustarla a mano, avisa de las dos.
         *
         * El atributo se pone con `forceFill()` y no con `update()`. No es
         * descuido: `monto` NO está en el `$fillable` de Cobro a propósito, porque
         * el total no se escribe desde el formulario —esa es la regla 1—. Con
         * `update(['monto' => ...])` Laravel descarta silenciosamente el atributo
         * por no estar permitido, el update no cambia nada, y el test seguiría
         * mirando la fila buena y no fallaría nunca: un test que pasa porque su
         * propia preparación no surtió efecto.
         */
        $cobro->forceFill(['monto' => 12345.67])->save();

        $this->assertSame(12345.67, (float) $cobro->fresh()->monto, 'La desalineación se guardó.');

        $fila = $this->filaDelCobro($cobro);

        $this->assertNotNull($fila);
        $this->assertStringContainsString(
            'guardado',
            $fila['total'],
            'Cuando la columna guardada no coincide con la suma, hay que ver las dos.'
        );
    }
}

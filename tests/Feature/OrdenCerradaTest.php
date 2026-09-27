<?php

namespace Tests\Feature;

use App\Models\CategoriasCosto;
use App\Models\Empleado;
use App\Models\Equipo;
use App\Models\Etapa;
use App\Models\Moneda;
use App\Models\MovimientosCosto;
use App\Models\OrdenesTrabajo;
use App\Models\Producto;
use App\Models\ProcesosOrden;
use App\Models\TiposPagoEmpleado;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Una orden cerrada no admite datos nuevos.
 *
 * Finalizada y cancelada son las dos cerradas. Cerrada no quiere decir
 * intocable: lo que ya esta escrito se sigue editando con normalidad, y por
 * eso estos tests miran tambien que el update siga pasando. Bloquear de mas
 * es tan malo como no bloquear: dejaria al usuario sin forma de corregir un
 * dato que se equivoco, que es justo cuando mas lo necesita.
 *
 * El bloqueo se comprueba por los seis caminos por los que se puede meter
 * algo en una orden. Por los seis, y no por uno: son seis pantallas escritas
 * por separado, y la regla esta en un trait, pero el trait no hace nada por
 * si solo, lo llama cada una. Si uno se olvida de llamar, ese camino queda
 * abierto y no se nota por ningun otro lado.
 *
 * Las cuentas no se comprueban con assertDatabaseCount sino restando antes y
 * despues: esta base tiene datos de verdad, y un conteo absoluto daria por
 * hecho que no hay ninguno. Un test que cuenta filas absolutas pasa porque
 * la tabla estaba vacia y empieza a fallar el dia que se mete el primer dato
 * real.
 */
class OrdenCerradaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'cerrada-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

    // ==================================================================
    // Los datos de siempre
    // ==================================================================

    private function orden(int $estado): OrdenesTrabajo
    {
        /*
         * El cliente es obligatorio y no tiene valor por defecto. Se toma el
         * de la primera orden que haya, que es lo que hace el test del
         * calendario: la prueba va de la regla de la orden cerrada, y del
         * cliente no.
         */
        $original = OrdenesTrabajo::firstOrFail();

        return OrdenesTrabajo::create([
            OrdenesTrabajo::CODIGO => 'OT-CERR-' . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
            OrdenesTrabajo::CLIENTE_ID => $original->cliente_id,
            OrdenesTrabajo::FECHA => '2026-09-20',
            OrdenesTrabajo::DESCRIPCION => 'Orden de la prueba de orden cerrada',
            OrdenesTrabajo::PESO_MINERAL => 10,
            OrdenesTrabajo::UNIDAD_PESO => 'toneladas',
            OrdenesTrabajo::ESTADO => $estado,
        ]);
    }

    private function proceso(OrdenesTrabajo $orden): ProcesosOrden
    {
        return ProcesosOrden::create([
            ProcesosOrden::CODIGO => 'P-CERR-' . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
            ProcesosOrden::ORDEN_TRABAJO_ID => $orden->id,
            ProcesosOrden::ETAPA_ID => Etapa::firstOrFail()->id,
        ]);
    }

    private function moneda(): Moneda
    {
        return Moneda::where('es_moneda_base', true)->firstOrFail();
    }

    /**
     * Una categoria de costo que se pueda registrar a mano.
     *
     * No sirve cualquiera: las de mano de obra y depreciacion las calcula el
     * sistema, y la validacion las rechaza a proposito, porque escribirlas
     * aqui las contaria dos veces. Por eso el catalogo trae el filtro y hay
     * que usar el.
     */
    private function categoriaRegistrable(): CategoriasCosto
    {
        return CategoriasCosto::paraRegistrar()->firstOrFail();
    }

    private function datosCosto(): array
    {
        return [
            'categoria_costo_id' => $this->categoriaRegistrable()->id,
            'fecha' => '2026-09-20',
            'moneda_id' => $this->moneda()->id,
            'cantidad' => 1,
            'costo_unitario' => 100,
            'descripcion' => 'Costo de la prueba',
        ];
    }

    private function datosTrabajo(): array
    {
        return [
            'empleado_id' => Empleado::firstOrFail()->id,
            'tipo_pago_id' => TiposPagoEmpleado::firstOrFail()->id,
            'fecha' => '2026-09-20',
            'moneda_id' => $this->moneda()->id,
            'descripcion' => 'Trabajo de la prueba',
            'cantidad' => 1,
        ];
    }

    /**
     * Cuantas filas hay ahora en una tabla.
     */
    private function cuantas(string $tabla): int
    {
        return \Illuminate\Support\Facades\DB::table($tabla)->count();
    }

    // ==================================================================
    // La regla
    // ==================================================================

    public function test_finalizada_y_cancelada_estan_cerradas_y_las_otras_no(): void
    {
        $this->assertTrue($this->orden(OrdenesTrabajo::ESTADO_FINALIZADA)->estaCerrada());
        $this->assertTrue($this->orden(OrdenesTrabajo::ESTADO_CANCELADA)->estaCerrada());

        $this->assertFalse($this->orden(OrdenesTrabajo::ESTADO_PENDIENTE)->estaCerrada());
        $this->assertFalse($this->orden(OrdenesTrabajo::ESTADO_EN_PROCESO)->estaCerrada());
    }

    public function test_una_orden_pendiente_sigue_admitiendo_datos(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_PENDIENTE);

        $antes = $this->cuantas('movimientos_costos');

        $this->post(
            '/procesos/ordenes-trabajo/' . $orden->id . '/costos',
            $this->datosCosto()
        )->assertRedirect(route('procesos.ordenes_trabajo.show', $orden));

        $this->assertSame($antes + 1, $this->cuantas('movimientos_costos'));
    }

    public function test_una_orden_en_proceso_sigue_admitiendo_datos(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_EN_PROCESO);

        $antes = $this->cuantas('movimientos_costos');

        $this->post(
            '/procesos/ordenes-trabajo/' . $orden->id . '/costos',
            $this->datosCosto()
        )->assertRedirect(route('procesos.ordenes_trabajo.show', $orden));

        $this->assertSame($antes + 1, $this->cuantas('movimientos_costos'));
    }

    // ==================================================================
    // Los seis caminos
    // ==================================================================

    public function test_no_se_registra_un_costo_en_una_orden_finalizada(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_FINALIZADA);

        $antes = $this->cuantas('movimientos_costos');

        $this->post(
            '/procesos/ordenes-trabajo/' . $orden->id . '/costos',
            $this->datosCosto()
        )->assertSessionHasNoErrors();

        $this->assertSame($antes, $this->cuantas('movimientos_costos'));
    }

    public function test_no_se_agrega_un_proceso_a_una_orden_finalizada(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_FINALIZADA);

        $antes = $this->cuantas('procesos_orden');

        $this->post('/procesos/ordenes-trabajo/' . $orden->id . '/procesos', [
            'etapa_id' => Etapa::firstOrFail()->id,
            'fecha_inicio' => '2026-09-20',
        ]);

        $this->assertSame($antes, $this->cuantas('procesos_orden'));
    }

    public function test_no_se_registra_un_costo_de_proceso_en_una_orden_finalizada(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_FINALIZADA);
        $proceso = $this->proceso($orden);

        $antes = $this->cuantas('movimientos_costos');

        $this->post(
            '/procesos/ordenes-trabajo/' . $orden->id . '/procesos/' . $proceso->id . '/costos',
            $this->datosCosto()
        );

        $this->assertSame($antes, $this->cuantas('movimientos_costos'));
    }

    public function test_no_se_registra_un_consumo_de_material_en_una_orden_finalizada(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_FINALIZADA);
        $proceso = $this->proceso($orden);

        $antes = $this->cuantas('movimientos_inventario');

        $this->post(
            '/procesos/ordenes-trabajo/' . $orden->id . '/procesos/' . $proceso->id . '/materiales',
            [
                'producto_id' => Producto::firstOrFail()->id,
                'fecha' => '2026-09-20',
                'cantidad' => 1,
            ]
        );

        $this->assertSame($antes, $this->cuantas('movimientos_inventario'));
    }

    public function test_no_se_registra_un_trabajo_de_empleado_en_una_orden_finalizada(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_FINALIZADA);
        $proceso = $this->proceso($orden);

        $antes = $this->cuantas('trabajos_empleados');

        $this->post(
            '/procesos/ordenes-trabajo/' . $orden->id . '/procesos/' . $proceso->id . '/trabajos-empleados',
            $this->datosTrabajo()
        );

        $this->assertSame($antes, $this->cuantas('trabajos_empleados'));
    }

    public function test_no_se_registra_el_uso_de_un_equipo_en_una_orden_finalizada(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_FINALIZADA);
        $proceso = $this->proceso($orden);

        $antes = $this->cuantas('proceso_equipos');

        $this->post(
            '/procesos/ordenes-trabajo/' . $orden->id . '/procesos/' . $proceso->id . '/proceso-equipos',
            [
                'equipo_id' => Equipo::firstOrFail()->id,
                'fecha_inicio' => '2026-09-20',
                'fecha_fin' => '2026-09-25',
            ]
        );

        $this->assertSame($antes, $this->cuantas('proceso_equipos'));
    }

    public function test_tampoco_se_puede_en_una_orden_cancelada(): void
    {
        /*
         * Cancelada y finalizada son las dos cerradas por lo mismo: la orden
         * se acabo, o se tiro la toalla, y en los dos casos ya no se le anade
         * nada. Si el bloqueo solo mirara la finalizada, una cancelada
         * seguiria admitiendo costos sin avisar.
         */
        $orden = $this->orden(OrdenesTrabajo::ESTADO_CANCELADA);
        $proceso = $this->proceso($orden);

        $costos = $this->cuantas('movimientos_costos');
        $trabajos = $this->cuantas('trabajos_empleados');

        $this->post(
            '/procesos/ordenes-trabajo/' . $orden->id . '/costos',
            $this->datosCosto()
        );

        $this->post(
            '/procesos/ordenes-trabajo/' . $orden->id . '/procesos/' . $proceso->id . '/trabajos-empleados',
            $this->datosTrabajo()
        );

        $this->assertSame($costos, $this->cuantas('movimientos_costos'));
        $this->assertSame($trabajos, $this->cuantas('trabajos_empleados'));
    }

    public function test_el_bloqueo_devuelve_a_la_orden_con_el_motivo(): void
    {
        /*
         * No basta con no guardar: si alguien manda el formulario a mano,
         * tiene que enterarse de por que no se guardo y donde mirar. Un
         * silencio deja pensar que si se guardo.
         */
        $orden = $this->orden(OrdenesTrabajo::ESTADO_FINALIZADA);

        $this->post(
            '/procesos/ordenes-trabajo/' . $orden->id . '/costos',
            $this->datosCosto()
        )->assertRedirect(route('procesos.ordenes_trabajo.show', $orden));

        /*
         * La clave es 'alert', que es donde el paquete de SweetAlert deja
         * el aviso, y no una que se parezca al nombre del paquete. El texto
         * va dentro como un arreglo, asi que se pasa por json_encode y se busca
         * una frase del mensaje y no la clave entera: asi el test sigue valiendo
         * si el paquete cambia la forma en que guarda las cosas.
         */
        $aviso = json_encode(session('alert'), JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString(
            'no admite datos nuevos',
            $aviso,
            'Deberia quedar un aviso explicando por que no se guardo'
        );

        $this->assertStringContainsString(
            $orden->codigo,
            $aviso,
            'El aviso deberia decir de que orden se trata'
        );
    }

    // ==================================================================
    // Cerrada no quiere decir intocable
    // ==================================================================

    public function test_un_costo_ya_registrado_sigue_pudiendo_editarse(): void
    {
        /*
         * El punto de todo esto. Si un costo se equivoco y la orden ya
         * estaba cerrada, tiene que haber forma de corregirlo: si no, el
         * error se queda para siempre y con el el costo de la orden.
         */
        $orden = $this->orden(OrdenesTrabajo::ESTADO_PENDIENTE);

        $this->post(
            '/procesos/ordenes-trabajo/' . $orden->id . '/costos',
            $this->datosCosto()
        )->assertRedirect(route('procesos.ordenes_trabajo.show', $orden));

        $costo = MovimientosCosto::where('orden_trabajo_id', $orden->id)->firstOrFail();

        // Se cierra la orden despues de haber apuntado el costo
        $orden->update([OrdenesTrabajo::ESTADO => OrdenesTrabajo::ESTADO_FINALIZADA]);

        $this->put(
            '/procesos/ordenes-trabajo/' . $orden->id . '/costos/' . $costo->id,
            array_merge($this->datosCosto(), [
                'costo_unitario' => 250,
                'descripcion' => 'Corregido despues de cerrar la orden',
            ])
        )->assertRedirect(route('procesos.ordenes_trabajo.show', $orden));

        $costo->refresh();

        $this->assertSame(250.0, round((float) $costo->costo_unitario, 2));
        $this->assertSame('Corregido despues de cerrar la orden', $costo->descripcion);
    }

    public function test_un_costo_de_una_orden_cancelada_tambien_se_puede_editar(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_PENDIENTE);

        $this->post(
            '/procesos/ordenes-trabajo/' . $orden->id . '/costos',
            $this->datosCosto()
        );

        $costo = MovimientosCosto::where('orden_trabajo_id', $orden->id)->firstOrFail();

        $orden->update([OrdenesTrabajo::ESTADO => OrdenesTrabajo::ESTADO_CANCELADA]);

        $this->put(
            '/procesos/ordenes-trabajo/' . $orden->id . '/costos/' . $costo->id,
            array_merge($this->datosCosto(), ['costo_unitario' => 300])
        );

        $this->assertSame(300.0, round((float) $costo->fresh()->costo_unitario, 2));
    }

    // ==================================================================
    // La pantalla avisa antes de que se pulse nada
    // ==================================================================

    public function test_la_ficha_de_la_orden_avisa_y_apaga_los_botones(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_FINALIZADA);

        $html = $this->get('/procesos/ordenes-trabajo/' . $orden->id)->assertOk()->getContent();

        $this->assertStringContainsString('no admite', $html);
        $this->assertStringContainsString('no se pueden agregar procesos', $html);
        $this->assertStringContainsString('disabled', $html);
    }

    public function test_la_ficha_del_proceso_avisa_y_apaga_los_cuatro_botones(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_FINALIZADA);
        $proceso = $this->proceso($orden);

        $html = $this->get(
            '/procesos/ordenes-trabajo/' . $orden->id . '/procesos/' . $proceso->id
        )->assertOk()->getContent();

        $this->assertStringContainsString('no admite', $html);

        foreach (['un trabajo', 'el uso de un equipo', 'un costo', 'un consumo'] as $que) {
            $this->assertStringContainsString(
                'no se puede registrar ' . $que,
                $html,
                "El boton de '$que' deberia decir por que esta apagado"
            );
        }
    }

    public function test_una_orden_abierta_no_muestra_el_aviso(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_EN_PROCESO);
        $proceso = $this->proceso($orden);

        $html = $this->get(
            '/procesos/ordenes-trabajo/' . $orden->id . '/procesos/' . $proceso->id
        )->assertOk()->getContent();

        $this->assertStringNotContainsString('no admite', $html);
    }

    // ==================================================================
    // Los botones se apagan de verdad
    // ==================================================================

    public function test_los_botones_se_apagan_con_el_atributo_disabled(): void
    {
        /*
         * Un boton con la clase de gris de bootstrap sigue siendo pulsable, y
         * el javascript que abre el modal seguiria cogiendo el clic. Lo que
         * lo apaga de verdad es el atributo disabled.
         *
         * Se busca el disabled despues del id y no al reves: blade escribe
         * los atributos en el orden en que estan en la vista, y el disabled
         * va detras del id. Buscandolo antes, el test decia que el boton no
         * estaba apagado cuando si lo estaba.
         */
        $orden = $this->orden(OrdenesTrabajo::ESTADO_FINALIZADA);
        $proceso = $this->proceso($orden);

        $html = $this->get(
            '/procesos/ordenes-trabajo/' . $orden->id . '/procesos/' . $proceso->id
        )->assertOk()->getContent();

        foreach (['btnNuevoTrabajoEmpleado', 'btnNuevoUsoEquipo', 'btnNuevoCosto', 'btnNuevoMaterial'] as $boton) {
            $this->assertMatchesRegularExpression(
                '/id="' . $boton . '"[^>]*disabled/s',
                $html,
                "El boton $boton deberia llevar el atributo disabled"
            );
        }
    }

    public function test_los_botones_no_se_apagan_en_una_orden_abierta(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_EN_PROCESO);
        $proceso = $this->proceso($orden);

        $html = $this->get(
            '/procesos/ordenes-trabajo/' . $orden->id . '/procesos/' . $proceso->id
        )->assertOk()->getContent();

        foreach (['btnNuevoTrabajoEmpleado', 'btnNuevoUsoEquipo', 'btnNuevoCosto', 'btnNuevoMaterial'] as $boton) {
            $this->assertDoesNotMatchRegularExpression(
                '/id="' . $boton . '"[^>]*disabled/s',
                $html,
                "El boton $boton no deberia estar apagado en una orden abierta"
            );
        }
    }

    // ==================================================================
    // La regla esta en un solo sitio
    // ==================================================================

    public function test_los_seis_controladores_llaman_al_bloqueo_del_trait(): void
    {
        $controladores = [
            'Procesos/CostosOrdenController.php',
            'Procesos/ProcesoOrdenController.php',
            'Procesos/OrdenesTrabajo/CostosProcesoController.php',
            'Procesos/OrdenesTrabajo/MaterialesProcesoController.php',
            'Procesos/OrdenesTrabajo/TrabajosEmpleadoController.php',
            'Procesos/OrdenesTrabajo/ProcesoEquipoController.php',
        ];

        foreach ($controladores as $controlador) {
            $ruta = app_path('Http/Controllers/' . $controlador);

            $this->assertFileExists($ruta, "No existe el controlador $controlador");

            $codigo = file_get_contents($ruta);

            $this->assertStringContainsString(
                'use OrdenCerrada;',
                $codigo,
                "$controlador no usa el trait: se le olvidaria el bloqueo"
            );

            $this->assertStringContainsString(
                'bloquearOrdenCerrada',
                $codigo,
                "$controlador declara el trait pero no llama al bloqueo: "
                . 'ese camino de entrada quedaria abierto'
            );
        }
    }

    public function test_el_bloqueo_va_antes_de_validar_nada(): void
    {
        /*
         * Si el bloqueo estuviera despues de validar, un formulario con un
         * campo vacio devolveria un error de campo vacio, y el usuario
         * veria un mensaje de que se le olvido algo en vez de que la orden
         * esta cerrada.
         */
        $ruta = app_path('Http/Controllers/Procesos/OrdenesTrabajo/TrabajosEmpleadoController.php');

        $codigo = file_get_contents($ruta);

        $bloqueo = strpos($codigo, 'bloquearOrdenCerrada');
        $validacion = strpos($codigo, 'validarTrabajo', $bloqueo);

        $this->assertIsInt($bloqueo);
        $this->assertIsInt($validacion);

        $this->assertLessThan(
            $validacion,
            $bloqueo,
            'El bloqueo deberia ir antes de validar el formulario'
        );
    }
}

<?php

namespace Tests\Feature;

use App\Models\CategoriasCosto;
use App\Models\Moneda;
use App\Models\MovimientosCosto;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use App\Models\TiposCambio;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Costos registrados a mano: los de un proceso y los de toda la orden.
 *
 * Aqui se comprueban las reglas que hacen que el costo de una OT sea
 * fiable:
 *   - el total y su equivalente en NIO los calcula el servidor, no el
 *     formulario;
 *   - la mano de obra y la depreciacion no se pueden escribir a mano, que
 *     es como se duplicarian;
 *   - el proceso de un costo tiene que ser de la misma orden;
 *   - un costo general y uno de proceso no se mezclan ni se cuentan dos
 *     veces.
 */
class CostosDeOrdenTest extends TestCase
{
    use DatabaseTransactions;

    private OrdenesTrabajo $orden;

    private ProcesosOrden $proceso;

    private ProcesosOrden $otroProceso;

    private Moneda $monedaBase;

    private Moneda $monedaExtranjera;

    private CategoriasCosto $categoria;

    private CategoriasCosto $categoriaAutomatica;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'cos-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);

        $this->orden = OrdenesTrabajo::firstOrFail();

        $this->proceso = $this->crearProceso();
        $this->otroProceso = $this->crearProceso();

        $this->monedaBase = Moneda::where('es_moneda_base', true)->firstOrFail();
        $this->monedaExtranjera = Moneda::where('es_moneda_base', false)
            ->where('estado', true)
            ->firstOrFail();

        // Una categoria que si se carga a mano y otra que no
        $this->categoria = CategoriasCosto::paraRegistrar()->firstOrFail();
        $this->categoriaAutomatica = CategoriasCosto::where('automatica', true)
            ->firstOrFail();
    }

    private function crearProceso(): ProcesosOrden
    {
        $etapa = DB::table('etapas')->first();

        $proceso = new ProcesosOrden();
        $proceso->orden_trabajo_id = $this->orden->id;
        $proceso->etapa_id = $etapa->id;
        $proceso->codigo = 'P-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $proceso->fecha_inicio = '2026-01-01 08:00:00';
        $proceso->fecha_fin = '2026-12-31 17:00:00';
        $proceso->peso_entrada = 1;
        $proceso->peso_salida = 1;
        $proceso->estado = 1;
        $proceso->save();

        return $proceso;
    }

    private function rutaProceso(string $accion = '', ?ProcesosOrden $proceso = null): string
    {
        $proceso ??= $this->proceso;

        return "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$proceso->id}/costos{$accion}";
    }

    private function rutaOrden(string $accion = ''): string
    {
        return "/procesos/ordenes-trabajo/{$this->orden->id}/costos{$accion}";
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'categoria_costo_id' => $this->categoria->id,
            'fecha' => '2026-03-10',
            'descripcion' => 'Consumo de energia del molino',
            'cantidad' => 4,
            'costo_unitario' => 250,
            'moneda_id' => $this->monedaBase->id,
        ], $extra);
    }

    /**
     * El html del combo de categorias, y no la pagina entera.
     *
     * Hace falta acotar al <select>: la pantalla del proceso nombra
     * "Mano de obra" y "Depreciación" en el desglose de arriba, asi que
     * buscarlas en el html completo daria positivo aunque no estuvieran
     * en el combo, que es lo que de verdad importa.
     */
    private function comboDeCategorias(string $html): string
    {
        if (preg_match('#<select[^>]*id="costo(Orden)?Categoria".*?</select>#s', $html, $m)) {
            return $m[0];
        }

        $this->fail('No se encontro el combo de categorias en la pagina');
    }

    // ==================================================================
    // El total lo calcula el servidor
    // ==================================================================

    public function test_el_total_se_calcula_solo(): void
    {
        $this->post($this->rutaProceso(), $this->payload([
            'cantidad' => 3,
            'costo_unitario' => 250,
        ]))->assertRedirect();

        $costo = MovimientosCosto::latest('id')->firstOrFail();

        $this->assertEqualsWithDelta(750.0, (float) $costo->costo_total, 0.01);
    }

    public function test_el_total_del_formulario_se_ignora(): void
    {
        $this->post($this->rutaProceso(), $this->payload([
            'cantidad' => 3,
            'costo_unitario' => 250,
            // Se manda un total inventado: no debe ganar
            'costo_total' => 999999,
        ]))->assertRedirect();

        $costo = MovimientosCosto::latest('id')->firstOrFail();

        $this->assertEqualsWithDelta(
            750.0,
            (float) $costo->costo_total,
            0.01,
            'El total del formulario no debe poder imponer un valor'
        );
    }

    public function test_en_moneda_base_el_equivalente_nio_es_el_mismo(): void
    {
        $this->post($this->rutaProceso(), $this->payload([
            'cantidad' => 2,
            'costo_unitario' => 500,
            'moneda_id' => $this->monedaBase->id,
        ]))->assertRedirect();

        $costo = MovimientosCosto::latest('id')->firstOrFail();

        // La moneda base vale 1 por definicion: no hace falta tipo de cambio
        $this->assertEqualsWithDelta(1000.0, (float) $costo->costo_total, 0.01);
        $this->assertEqualsWithDelta(1000.0, (float) $costo->costo_total_nio, 0.01);
        $this->assertEqualsWithDelta(500.0, (float) $costo->costo_unitario_nio, 0.01);
    }

    public function test_en_moneda_extranjera_usa_el_tipo_de_cambio_de_la_fecha(): void
    {
        TiposCambio::create([
            'moneda_id' => $this->monedaExtranjera->id,
            'fecha' => '2026-01-01',
            'valor' => 36.5,
            'fuente' => 'Prueba',
        ]);

        $this->post($this->rutaProceso(), $this->payload([
            'cantidad' => 2,
            'costo_unitario' => 10,
            'moneda_id' => $this->monedaExtranjera->id,
            'fecha' => '2026-03-10',
        ]))->assertRedirect();

        $costo = MovimientosCosto::latest('id')->firstOrFail();

        $this->assertEqualsWithDelta(20.0, (float) $costo->costo_total, 0.01);
        $this->assertEqualsWithDelta(730.0, (float) $costo->costo_total_nio, 0.01);
    }

    public function test_sin_tipo_de_cambio_el_equivalente_nio_queda_vacio(): void
    {
        $this->post($this->rutaProceso(), $this->payload([
            'cantidad' => 2,
            'costo_unitario' => 10,
            'moneda_id' => $this->monedaExtranjera->id,
        ]))->assertRedirect();

        $costo = MovimientosCosto::latest('id')->firstOrFail();

        // El costo se guarda igual, solo el equivalente queda vacio
        $this->assertEqualsWithDelta(20.0, (float) $costo->costo_total, 0.01);
        $this->assertNull($costo->costo_total_nio);
    }

    // ==================================================================
    // Las categorias que calcula el sistema no se escriben a mano
    // ==================================================================

    public function test_no_se_puede_registrar_una_categoria_automatica(): void
    {
        $this->assertTrue(
            $this->categoriaAutomatica->automatica,
            'La prueba necesita una categoria marcada como automatica'
        );

        $this->post(
            $this->rutaProceso(),
            $this->payload(['categoria_costo_id' => $this->categoriaAutomatica->id])
        )->assertSessionHasErrors('categoria_costo_id');

        $this->assertSame(0, MovimientosCosto::count());
    }

    public function test_las_categorias_automaticas_no_aparecen_en_el_combo(): void
    {
        // La pantalla del proceso, no la ruta del ajax del DataTable: esa
        // devuelve json y no tiene el formulario
        $html = $this->get(
            "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$this->proceso->id}"
        )->assertOk()->getContent();

        $combo = $this->comboDeCategorias($html);

        $this->assertStringContainsString($this->categoria->nombre, $combo);
        $this->assertStringNotContainsString(
            $this->categoriaAutomatica->nombre,
            $combo,
            'Una categoria automatica no debe ofrecerse para registrarla a mano'
        );
    }

    public function test_el_proceso_no_ofrece_las_categorias_automaticas_ni_en_la_orden(): void
    {
        $html = $this->get(
            "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$this->proceso->id}"
        )->assertOk()->getContent();

        $combo = $this->comboDeCategorias($html);

        $this->assertStringContainsString($this->categoria->nombre, $combo);
        $this->assertStringNotContainsString($this->categoriaAutomatica->nombre, $combo);

        $html = $this->get(
            "/procesos/ordenes-trabajo/{$this->orden->id}"
        )->assertOk()->getContent();

        $this->assertStringNotContainsString(
            $this->categoriaAutomatica->nombre,
            $this->comboDeCategorias($html)
        );
    }

    public function test_la_categoria_automatica_explica_donde_se_calcula(): void
    {
        $this->assertNotNull($this->categoriaAutomatica->origenDeCalculo());
        $this->assertNull($this->categoria->origenDeCalculo());
    }

    // ==================================================================
    // El proceso sale de la ruta y tiene que ser de esa orden
    // ==================================================================

    public function test_el_proceso_se_toma_de_la_ruta(): void
    {
        $this->post($this->rutaProceso('', $this->proceso), $this->payload([
            'proceso_orden_id' => $this->otroProceso->id,
        ]))->assertRedirect();

        $costo = MovimientosCosto::latest('id')->firstOrFail();

        $this->assertEquals(
            $this->proceso->id,
            $costo->proceso_orden_id,
            'El proceso debe tomarse de la ruta, no del formulario'
        );
    }

    public function test_no_se_puede_cargar_un_costo_en_un_proceso_de_otra_orden(): void
    {
        $otra = $this->orden->replicate();
        $otra->codigo = 'OT-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $otra->save();

        $etapa = DB::table('etapas')->first();

        $ajeno = new ProcesosOrden();
        $ajeno->orden_trabajo_id = $otra->id;
        $ajeno->etapa_id = $etapa->id;
        $ajeno->codigo = 'P-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $ajeno->fecha_inicio = '2026-01-01 08:00:00';
        $ajeno->peso_entrada = 1;
        $ajeno->peso_salida = 1;
        $ajeno->estado = 1;
        $ajeno->save();

        $this->post(
            "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$ajeno->id}/costos",
            $this->payload()
        )->assertNotFound();

        $this->assertSame(0, MovimientosCosto::count());
    }

    public function test_no_se_puede_tocar_un_costo_de_otro_proceso(): void
    {
        $this->post($this->rutaProceso('', $this->proceso), $this->payload())
            ->assertRedirect();

        $costo = MovimientosCosto::latest('id')->firstOrFail();

        $this->get($this->rutaProceso("/{$costo->id}", $this->otroProceso))->assertNotFound();
        $this->delete($this->rutaProceso("/{$costo->id}", $this->otroProceso))->assertNotFound();

        $this->assertNotSoftDeleted('movimientos_costos', ['id' => $costo->id]);
    }

    // ==================================================================
    // Un costo general y uno de proceso no se mezclan
    // ==================================================================

    public function test_un_costo_general_no_tiene_proceso(): void
    {
        $this->post($this->rutaOrden(), $this->payload([
            'descripcion' => 'Transporte de la muestra',
        ]))->assertRedirect();

        $costo = MovimientosCosto::latest('id')->firstOrFail();

        $this->assertNull($costo->proceso_orden_id);
        $this->assertEquals($this->orden->id, $costo->orden_trabajo_id);
    }

    public function test_desde_la_orden_no_se_puede_tocar_un_costo_de_proceso(): void
    {
        $this->post($this->rutaProceso(), $this->payload())->assertRedirect();

        $costo = MovimientosCosto::latest('id')->firstOrFail();

        // El costo es de un proceso: en la pantalla de la orden no existe
        $this->get($this->rutaOrden("/{$costo->id}"))->assertNotFound();
        $this->delete($this->rutaOrden("/{$costo->id}"))->assertNotFound();

        $this->assertNotSoftDeleted('movimientos_costos', ['id' => $costo->id]);
    }

    public function test_desde_el_proceso_no_se_puede_tocar_un_costo_general(): void
    {
        $this->post($this->rutaOrden(), $this->payload())->assertRedirect();

        $costo = MovimientosCosto::latest('id')->firstOrFail();

        $this->delete($this->rutaProceso("/{$costo->id}"))->assertNotFound();

        $this->assertNotSoftDeleted('movimientos_costos', ['id' => $costo->id]);
    }

    public function test_cada_tabla_muestra_solo_lo_suyo(): void
    {
        $this->post($this->rutaProceso(), $this->payload([
            'descripcion' => 'Costo del proceso',
        ]))->assertRedirect();

        $this->post($this->rutaOrden(), $this->payload([
            'descripcion' => 'Costo general',
        ]))->assertRedirect();

        $general = $ruta = null;

        foreach (MovimientosCosto::all() as $costo) {
            if ($costo->proceso_orden_id === null) {
                $general = $costo;
            } else {
                $ruta = $costo;
            }
        }

        $this->assertNotNull($general);
        $this->assertNotNull($ruta);

        $r = $this->getJson(
            "/procesos/ordenes-trabajo/{$this->orden->id}/costos?draw=1&start=0&length=50",
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertOk();

        $ids = array_column($r->json('data'), 'id');
        $this->assertContains($general->id, $ids);
        $this->assertNotContains($ruta->id, $ids, 'El costo de un proceso no va en la tabla de la orden');
    }

    // ==================================================================
    // El costo del proceso
    // ==================================================================

    public function test_los_cotros_costos_entran_al_costo_del_proceso(): void
    {
        $this->post($this->rutaProceso('', $this->proceso), $this->payload([
            'cantidad' => 2,
            'costo_unitario' => 500,
        ]))->assertRedirect();

        $this->post($this->rutaProceso('', $this->proceso), $this->payload([
            'descripcion' => 'Agua del turno',
            'cantidad' => 1,
            'costo_unitario' => 100,
        ]))->assertRedirect();

        $proceso = $this->proceso->fresh();

        $this->assertEqualsWithDelta(1100.0, $proceso->costo_otros, 0.01);
        $this->assertEqualsWithDelta(1100.0, $proceso->costo_total, 0.01);
    }

    public function test_los_costos_de_otro_proceso_no_arrastran(): void
    {
        $this->post($this->rutaProceso('', $this->otroProceso), $this->payload([
            'cantidad' => 1,
            'costo_unitario' => 1000,
        ]))->assertRedirect();

        $this->assertEquals(0.0, $this->proceso->fresh()->costo_otros);
        $this->assertEqualsWithDelta(1000.0, $this->otroProceso->fresh()->costo_otros, 0.01);
    }

    public function test_quitar_el_costo_baja_el_costo_del_proceso(): void
    {
        $this->post($this->rutaProceso(), $this->payload([
            'cantidad' => 1,
            'costo_unitario' => 700,
        ]))->assertRedirect();

        $costo = MovimientosCosto::latest('id')->firstOrFail();

        $this->assertEqualsWithDelta(700.0, $this->proceso->fresh()->costo_otros, 0.01);

        $this->delete($this->rutaProceso("/{$costo->id}"))->assertRedirect();

        $this->assertEquals(0.0, $this->proceso->fresh()->costo_otros);
    }

    // ==================================================================
    // El desglose
    // ==================================================================

    public function test_el_desglose_muestra_cada_categoria_una_sola_vez(): void
    {
        $otraCategoria = CategoriasCosto::paraRegistrar()
            ->where('id', '!=', $this->categoria->id)
            ->firstOrFail();

        $this->post($this->rutaProceso(), $this->payload([
            'cantidad' => 2,
            'costo_unitario' => 100,
        ]))->assertRedirect();

        $this->post($this->rutaProceso(), $this->payload([
            'categoria_costo_id' => $otraCategoria->id,
            'descripcion' => 'Otro concepto',
            'cantidad' => 1,
            'costo_unitario' => 50,
        ]))->assertRedirect();

        $desglose = $this->proceso->fresh()->costosDesglosados();

        $nombres = $desglose->pluck('nombre')->all();

        // Sin repetidos: es el fallo clasico al sumar automaticos y manuales
        $this->assertSame(
            count($nombres),
            count(array_unique($nombres)),
            'Hay conceptos repetidos en el desglose: ' . implode(' | ', $nombres)
        );

        // Los dos automaticos siempre estan
        $this->assertContains('Mano de obra', $nombres);
        $this->assertContains('Depreciación de equipos', $nombres);

        // Y las categorias que se cargan a mano, con o sin movimientos
        $this->assertContains($this->categoria->nombre, $nombres);
        $this->assertContains($otraCategoria->nombre, $nombres);
    }

    public function test_el_desglose_no_mezcla_las_categorias_automaticas(): void
    {
        $this->post($this->rutaProceso(), $this->payload([
            'cantidad' => 1,
            'costo_unitario' => 300,
        ]))->assertRedirect();

        $desglose = $this->proceso->fresh()->costosDesglosados();

        /*
         * "Mano de obra" si tiene que estar en el desglose, como lo que
         * calcula el sistema. Lo que no puede pasar es que aparezca dos
         * veces: una como concepto automatico y otra como si se pudiera
         * cargar a mano, que es la forma de que el mismo costo se cuente
         * en la fila equivocada.
         */
        $manuales = $desglose
            ->where('nombre', $this->categoriaAutomatica->nombre)
            ->where('automatico', false)
            ->count();

        $this->assertSame(
            0,
            $manuales,
            'Una categoria automatica no debe aparecer como concepto cargable a mano'
        );

        // Y si aparece, es una sola vez y marcada como automatica
        $apariciones = $desglose->where('nombre', $this->categoriaAutomatica->nombre)->count();

        if ($apariciones > 0) {
            $this->assertSame(1, $apariciones);
            $this->assertTrue($desglose->firstWhere('nombre', $this->categoriaAutomatica->nombre)['automatico']);
        }
    }

    public function test_el_desglose_suma_el_total_del_proceso(): void
    {
        $this->post($this->rutaProceso(), $this->payload([
            'cantidad' => 3,
            'costo_unitario' => 200,
        ]))->assertRedirect();

        $proceso = $this->proceso->fresh();

        $suma = $proceso->costosDesglosados()->sum('importe');

        $this->assertEqualsWithDelta(
            $proceso->costo_total,
            $suma,
            0.01,
            'La suma del desglose tiene que dar el total del proceso'
        );
    }

    public function test_el_proceso_muestra_el_desglose_y_los_totales(): void
    {
        $this->post($this->rutaProceso(), $this->payload([
            'cantidad' => 2,
            'costo_unitario' => 250,
        ]))->assertRedirect();

        $html = $this->get(
            "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$this->proceso->id}"
        )->assertOk()->getContent();

        $this->assertStringContainsString('Costos del proceso', $html);
        $this->assertStringContainsString('De dónde sale el costo', $html);
        $this->assertStringContainsString('Otros costos', $html);
        $this->assertStringContainsString('Costo total del proceso', $html);
        $this->assertStringContainsString('500.00', $html);
    }

    // ==================================================================
    // Editar
    // ==================================================================

    public function test_editar_recalcula_en_vez_de_duplicar(): void
    {
        $this->post($this->rutaProceso(), $this->payload([
            'cantidad' => 2,
            'costo_unitario' => 100,
        ]))->assertRedirect();

        $costo = MovimientosCosto::latest('id')->firstOrFail();
        $this->assertSame(1, MovimientosCosto::count());

        $this->put($this->rutaProceso("/{$costo->id}"), $this->payload([
            'cantidad' => 5,
            'costo_unitario' => 100,
        ]))->assertRedirect();

        $this->assertSame(
            1,
            MovimientosCosto::count(),
            'Editar no debe crear una fila nueva'
        );

        $costo = $costo->fresh();

        $this->assertEqualsWithDelta(500.0, (float) $costo->costo_total, 0.01);
        $this->assertEqualsWithDelta(500.0, $this->proceso->fresh()->costo_otros, 0.01);
    }

    public function test_editar_una_moneda_extranjera_rehace_el_equivalente(): void
    {
        $this->post($this->rutaProceso(), $this->payload([
            'cantidad' => 1,
            'costo_unitario' => 100,
        ]))->assertRedirect();

        $costo = MovimientosCosto::latest('id')->firstOrFail();

        TiposCambio::create([
            'moneda_id' => $this->monedaExtranjera->id,
            'fecha' => '2026-01-01',
            'valor' => 40,
            'fuente' => 'Prueba',
        ]);

        $this->put($this->rutaProceso("/{$costo->id}"), $this->payload([
            'cantidad' => 1,
            'costo_unitario' => 100,
            'moneda_id' => $this->monedaExtranjera->id,
        ]))->assertRedirect();

        $costo = $costo->fresh();

        $this->assertEqualsWithDelta(100.0, (float) $costo->costo_total, 0.01);
        $this->assertEqualsWithDelta(4000.0, (float) $costo->costo_total_nio, 0.01);
    }

    // ==================================================================
    // Validaciones
    // ==================================================================

    public function test_la_cantidad_no_puede_ser_cero(): void
    {
        $this->post($this->rutaProceso(), $this->payload(['cantidad' => 0]))
            ->assertSessionHasErrors('cantidad');

        $this->assertSame(0, MovimientosCosto::count());
    }

    public function test_la_descripcion_es_obligatoria(): void
    {
        $this->post($this->rutaProceso(), $this->payload(['descripcion' => '']))
            ->assertSessionHasErrors('descripcion');
    }

    public function test_no_se_acepta_una_categoria_inactiva(): void
    {
        $inactiva = CategoriasCosto::where('automatica', false)->first();
        $inactiva->update(['estado' => 0]);

        $this->post($this->rutaProceso(), $this->payload([
            'categoria_costo_id' => $inactiva->id,
        ]))->assertSessionHasErrors('categoria_costo_id');
    }

    // ==================================================================
    // Las pantallas
    // ==================================================================

    public function test_las_tablas_de_costo_responden(): void
    {
        $this->post($this->rutaProceso(), $this->payload())->assertRedirect();
        $this->post($this->rutaOrden(), $this->payload())->assertRedirect();

        $r = $this->getJson(
            $this->rutaProceso() . '?draw=1&start=0&length=50',
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertOk()->assertJsonStructure(['data', 'recordsTotal']);

        $this->assertCount(1, $r->json('data'));

        $r = $this->getJson(
            $this->rutaOrden() . '?draw=1&start=0&length=50',
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertOk();

        $this->assertCount(1, $r->json('data'));
    }

    public function test_la_orden_muestra_los_costos_generales(): void
    {
        $this->post($this->rutaOrden(), $this->payload([
            'descripcion' => 'Transporte de la muestra',
        ]))->assertRedirect();

        $html = $this->get(
            "/procesos/ordenes-trabajo/{$this->orden->id}"
        )->assertOk()->getContent();

        $this->assertStringContainsString('Costos generales de la orden', $html);
        $this->assertStringContainsString('costos-orden-table', $html);
        $this->assertStringContainsString('modalCostoOrden', $html);
    }

    public function test_el_modal_avisa_que_las_automaticas_no_se_escriben(): void
    {
        $html = $this->get(
            "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$this->proceso->id}"
        )->assertOk()->getContent();

        $this->assertStringContainsString('no se pueden escribir a mano', $html);
    }

    // ==================================================================
    // El tipo de cambio quedo centralizado
    // ==================================================================

    public function test_el_tipo_de_cambio_esta_centralizado_en_el_modelo(): void
    {
        TiposCambio::create([
            'moneda_id' => $this->monedaExtranjera->id,
            'fecha' => '2026-01-01',
            'valor' => 36.5,
            'fuente' => 'Prueba',
        ]);

        // Antes del 1 de enero todavia no hay ninguno
        $this->assertNull(
            TiposCambio::vigentePara($this->monedaExtranjera->id, '2025-12-31')
        );

        $this->assertEqualsWithDelta(
            36.5,
            TiposCambio::vigentePara($this->monedaExtranjera->id, '2026-03-10'),
            0.0001
        );

        // Si se corrige despues, el valor vigente cambia para las consultas
        // nuevas, pero los costos ya guardados no se mueven
        $this->assertNull(TiposCambio::vigentePara(999999, '2026-03-10'));
    }

    // ==================================================================
    // Permisos
    // ==================================================================

    public function test_hacen_falta_permisos_para_registrar_costos(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->usuarioConPermisos([
            'movimientos_costo.view',
            'procesos_orden.view',
        ]));

        $this->post($this->rutaProceso(), $this->payload())->assertForbidden();

        $this->assertSame(0, MovimientosCosto::count());
    }

    public function test_operador_puede_cargar_costos_pero_no_verlos_al_agregar(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->usuarioConPermisos([
            'movimientos_costo.view', 'movimientos_costo.create', 'movimientos_costo.edit',
            'procesos_orden.view', 'ordenes_trabajo.view',
        ]));

        $this->post($this->rutaProceso(), $this->payload())->assertRedirect();

        $this->assertSame(1, MovimientosCosto::count());

        // Y no puede borrar: el costo es parte de la trazabilidad
        $costo = MovimientosCosto::latest('id')->firstOrFail();

        $this->delete($this->rutaProceso("/{$costo->id}"))->assertForbidden();

        $this->assertNotSoftDeleted('movimientos_costos', ['id' => $costo->id]);
    }

    private function usuarioConPermisos(array $nombres): User
    {
        $nombre = 'rol-test-' . substr(md5(implode(',', $nombres)), 0, 8);

        $rol = Role::firstOrCreate([
            'name' => $nombre,
            'guard_name' => 'web',
        ]);

        $rol->syncPermissions($nombres);

        $usuario = User::create([
            'name' => 'Usuario de prueba',
            'email' => 'cosp-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);

        $usuario->assignRole($nombre);

        return $usuario;
    }
}

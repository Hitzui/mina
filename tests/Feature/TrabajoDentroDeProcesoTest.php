<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\EmpleadosPago;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use App\Models\TiposPagoEmpleado;
use App\Models\TrabajosEmpleado;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * El trabajo de un empleado cuelga de un proceso, no de la orden.
 *
 * Antes el proceso era opcional y existia el "trabajo general de la OT",
 * con proceso_orden_id en NULL. Ahora el proceso es obligatorio y la orden
 * se deduce de el, para que no haya dos datos que puedan desincronizarse.
 */
class TrabajoDentroDeProcesoTest extends TestCase
{
    use DatabaseTransactions;

    private OrdenesTrabajo $orden;

    private ProcesosOrden $proceso;

    private ProcesosOrden $otroProceso;

    private Empleado $empleado;

    private TiposPagoEmpleado $tipoPago;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'tp-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);

        $this->orden = OrdenesTrabajo::firstOrFail();
        $this->empleado = Empleado::where('estado', 1)->firstOrFail();

        // Dos procesos de la misma orden: el trabajo va a uno y hay que
        // comprobar que no se puede llegar al otro con el mismo trabajo
        $this->proceso = $this->crearProceso();
        $this->otroProceso = $this->crearProceso();

        $this->tipoPago = TiposPagoEmpleado::create([
            'nombre' => 'Test dentro de proceso',
            'codigo' => 'TDP' . substr(md5(uniqid('', true)), 0, 5),
            'metodo_calculo' => TiposPagoEmpleado::METODO_CANTIDAD_X_TARIFA,
            'estado' => 1,
        ]);

        EmpleadosPago::create([
            'empleado_id' => $this->empleado->id,
            'tipo_pago_id' => $this->tipoPago->id,
            'tarifa' => 100,
            'moneda_id' => 1,
            'fecha_inicio' => '2020-01-01',
            'fecha_fin' => null,
            'estado' => 1,
        ]);
    }

    private function crearProceso(): ProcesosOrden
    {
        $etapa = DB::table('etapas')->first();

        $proceso = new ProcesosOrden();
        $proceso->orden_trabajo_id = $this->orden->id;
        $proceso->etapa_id = $etapa->id;
        $proceso->codigo = 'P-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $proceso->fecha_inicio = now();
        $proceso->fecha_fin = null;
        $proceso->peso_entrada = 1;
        $proceso->peso_salida = 1;
        $proceso->estado = 1;
        $proceso->save();

        return $proceso;
    }

    private function ruta(string $accion, ?ProcesosOrden $proceso = null): string
    {
        $proceso ??= $this->proceso;

        return "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$proceso->id}/trabajos-empleados{$accion}";
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'empleado_id' => $this->empleado->id,
            'tipo_pago_id' => $this->tipoPago->id,
            'fecha' => '2026-03-10',
            'cantidad' => 4,
            'unidad' => 'hora',
            'descripcion' => 'Prueba automatica',
        ], $extra);
    }

    // ------------------------------------------------------------------
    // El proceso es obligatorio
    // ------------------------------------------------------------------

    public function test_la_base_no_admite_un_trabajo_sin_proceso(): void
    {
        $r = DB::selectOne(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                AND COLUMN_NAME = ?',
            ['trabajos_empleados', 'proceso_orden_id']
        );

        $this->assertNotNull($r, 'La columna proceso_orden_id no existe');
        $this->assertEquals(
            'NO',
            $r->IS_NULLABLE,
            'proceso_orden_id deberia ser NOT NULL: el trabajo cuelga del proceso'
        );
    }

    public function test_la_columna_de_la_orden_ya_no_existe(): void
    {
        $this->assertFalse(
            Schema::hasColumn('trabajos_empleados', 'orden_trabajo_id'),
            'orden_trabajo_id deberia haberse eliminado: la orden se saca del proceso'
        );
    }

    public function test_no_se_puede_registrar_un_trabajo_sin_proceso_por_el_formulario(): void
    {
        // El campo ya no existe en el formulario: mandarlo debe ignorarse
        // y el proceso sale de la url
        $this->post($this->ruta(''), $this->payload(['proceso_orden_id' => '']))
            ->assertRedirect();

        $trabajo = TrabajosEmpleado::latest('id')->first();

        $this->assertNotNull($trabajo);
        $this->assertEquals(
            $this->proceso->id,
            $trabajo->proceso_orden_id,
            'El proceso debe tomarse de la ruta, no del formulario'
        );
    }

    public function test_el_formulario_ya_no_ofrece_choices_de_proceso(): void
    {
        $html = $this->renderFormulario();

        $this->assertStringNotContainsString('name="proceso_orden_id"', $html);
        $this->assertStringNotContainsString('trabajoProceso', $html);
    }

    // ------------------------------------------------------------------
    // La orden se deduce del proceso
    // ------------------------------------------------------------------

    public function test_la_orden_del_trabajo_se_saca_del_proceso(): void
    {
        $this->post($this->ruta(''), $this->payload())->assertRedirect();

        $trabajo = TrabajosEmpleado::latest('id')->first();

        $this->assertEquals(
            $this->orden->id,
            $trabajo->orden_trabajo->id,
            'La orden del trabajo debe ser la del proceso'
        );
    }

    public function test_los_trabajos_de_una_orden_solo_son_los_de_sus_procesos(): void
    {
        $this->post($this->ruta(''), $this->payload())->assertRedirect();

        $trabajo = TrabajosEmpleado::latest('id')->first();

        $vistos = $this->getJson(
            "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$this->proceso->id}/trabajos-empleados"
        );

        $vistos->assertOk();

        $ids = collect($vistos->json('data'))->pluck('id')->all();

        $this->assertContains($trabajo->id, $ids);
        $this->assertCount(1, $ids, 'El otro proceso no debe ver este trabajo');
    }

    // ------------------------------------------------------------------
    // No se puede cruzar de proceso
    // ------------------------------------------------------------------

    public function test_no_se_puede_ver_un_trabajo_desde_otro_proceso(): void
    {
        $this->post($this->ruta(''), $this->payload())->assertRedirect();

        $trabajo = TrabajosEmpleado::latest('id')->first();

        $this->getJson(
            "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$this->otroProceso->id}"
            . "/trabajos-empleados/{$trabajo->id}"
        )->assertNotFound();

        $this->getJson(
            "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$this->otroProceso->id}"
            . "/trabajos-empleados/{$trabajo->id}/edit"
        )->assertNotFound();
    }

    public function test_no_se_puede_editar_ni_borrar_desde_otro_proceso(): void
    {
        $this->post($this->ruta(''), $this->payload())->assertRedirect();

        $trabajo = TrabajosEmpleado::latest('id')->first();

        $this->put(
            "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$this->otroProceso->id}"
            . "/trabajos-empleados/{$trabajo->id}",
            $this->payload()
        )->assertNotFound();

        $this->delete(
            "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$this->otroProceso->id}"
            . "/trabajos-empleados/{$trabajo->id}"
        )->assertNotFound();

        $this->assertNull(
            $trabajo->fresh()->deleted_at,
            'El trabajo no debe haberse borrado'
        );
    }

    public function test_no_se_puede_usar_un_proceso_de_otra_orden(): void
    {
        $otra = $this->crearOrden();

        $this->proceso->update(['orden_trabajo_id' => $otra->id]);

        $antes = TrabajosEmpleado::count();

        $this->post(
            "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$this->proceso->id}/trabajos-empleados",
            $this->payload()
        )->assertNotFound();

        $this->assertEquals(
            $antes,
            TrabajosEmpleado::count(),
            'No deberia haberse creado ningun trabajo'
        );
    }

    // ------------------------------------------------------------------
    // El costo del proceso
    // ------------------------------------------------------------------

    public function test_el_costo_del_proceso_suma_sus_trabajos(): void
    {
        $this->assertEquals(0.0, $this->proceso->costo_empleados);

        $this->post($this->ruta(''), $this->payload(['cantidad' => 4]))->assertRedirect();
        $this->post($this->ruta(''), $this->payload(['cantidad' => 6]))->assertRedirect();

        // 4 x 100 y 6 x 100
        $this->assertEquals(1000.0, $this->proceso->fresh()->costo_empleados);

        // Y el otro proceso sigue en cero
        $this->assertEquals(0.0, $this->otroProceso->fresh()->costo_empleados);
    }

    public function test_el_costo_refleja_un_trabajo_borrado(): void
    {
        $this->post($this->ruta(''), $this->payload(['cantidad' => 4]))->assertRedirect();

        $trabajo = TrabajosEmpleado::latest('id')->first();
        $this->assertEquals(400.0, $this->proceso->fresh()->costo_empleados);

        $trabajo->delete();

        // Se mira el historial, asi que el borrado logico no lo cambia
        $this->assertEquals(400.0, $this->proceso->fresh()->costo_empleados);
    }

    public function test_el_costo_usa_la_relacion_cargada_si_la_hay(): void
    {
        $this->post($this->ruta(''), $this->payload(['cantidad' => 5]))->assertRedirect();

        // Con la relacion precargada no debe hacer falta consultar
        $proceso = ProcesosOrden::with('trabajos_empleados')->findOrFail($this->proceso->id);

        $this->assertTrue($proceso->relationLoaded('trabajos_empleados'));
        $this->assertEquals(500.0, $proceso->costo_empleados);
    }

    // ------------------------------------------------------------------
    // Pantalla del proceso
    // ------------------------------------------------------------------

    public function test_la_pantalla_del_proceso_muestra_el_costo(): void
    {
        $this->post($this->ruta(''), $this->payload(['cantidad' => 7]))->assertRedirect();

        $r = $this->get("/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$this->proceso->id}");

        $r->assertOk();
        // El rotulo se acorto al repartirse la fila en tres componentes:
        // mano de obra, depreciacion y otros costos
        $r->assertSee('Costo de mano de obra');
        $r->assertSee(number_format(700, 2));
    }

    private function crearOrden(): OrdenesTrabajo
    {
        $original = OrdenesTrabajo::firstOrFail();

        return OrdenesTrabajo::create([
            'codigo' => 'OT-TDP-' . substr(md5(uniqid('', true)), 0, 6),
            'cliente_id' => $original->cliente_id,
            'fecha' => $original->fecha,
            'descripcion' => 'Orden creada por la prueba automatica',
            'peso_mineral' => $original->peso_mineral,
            'unidad_peso' => $original->unidad_peso,
            'estado' => 1,
        ]);
    }

    private function renderFormulario(): string
    {
        return (string) view(
            'procesos.ordenes_trabajo.trabajos_empleados._form',
            [
                'tiposPago' => TiposPagoEmpleado::all(),
            ]
        )->render();
    }
}

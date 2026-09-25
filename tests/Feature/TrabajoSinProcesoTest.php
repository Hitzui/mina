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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * El trabajo de un empleado puede pertenecer a un proceso de la orden o
 * ser general de la orden misma.
 *
 * "General de la OT" no es "sin especificar": es una decision valida que se
 * guarda con proceso_orden_id en NULL.
 */
class TrabajoSinProcesoTest extends TestCase
{
    use DatabaseTransactions;

    private OrdenesTrabajo $orden;

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
        $this->tipoPago = $this->crearTipoConTarifa();
    }

    private function crearTipoConTarifa(): TiposPagoEmpleado
    {
        $tipo = TiposPagoEmpleado::create([
            'nombre' => 'Test sin proceso',
            'codigo' => 'TST' . substr(md5(uniqid('', true)), 0, 5),
            'metodo_calculo' => TiposPagoEmpleado::METODO_CANTIDAD_X_TARIFA,
            'estado' => 1,
        ]);

        EmpleadosPago::create([
            'empleado_id' => $this->empleado->id,
            'tipo_pago_id' => $tipo->id,
            'tarifa' => 100,
            'moneda_id' => 1,
            'fecha_inicio' => '2020-01-01',
            'fecha_fin' => null,
            'estado' => 1,
        ]);

        return $tipo;
    }

    private function procesoDeLaOrden(): ?ProcesosOrden
    {
        return ProcesosOrden::where('orden_trabajo_id', $this->orden->id)->first();
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

    private function rutaStore(): string
    {
        return "/procesos/ordenes-trabajo/{$this->orden->id}/trabajos-empleados";
    }

    /**
     * El combo manda value="" cuando se elige "General a la OT". El
     * middleware ConvertEmptyStringsToNull lo debe volver NULL, no "".
     */
    public function test_omitir_el_proceso_guarda_null_y_no_texto_vacio(): void
    {
        // Lo que hace el navegador: value="" en el option elegido
        $this->post($this->rutaStore(), $this->payload(['proceso_orden_id' => '']))
            ->assertRedirect();

        $trabajo = TrabajosEmpleado::latest('id')->first();

        $this->assertNull(
            $trabajo->proceso_orden_id,
            'Un trabajo general debe quedar con proceso_orden_id en NULL'
        );

        // Y no debe haberse guardado la cadena vacia, que en una columna
        // bigint seria 0 y romperia la foreign key
        $crudo = \Illuminate\Support\Facades\DB::table('trabajos_empleados')
            ->where('id', $trabajo->id)
            ->value('proceso_orden_id');

        $this->assertNull($crudo, 'La columna debe guardar NULL de verdad');
    }

    public function test_tambien_se_guarda_null_si_el_campo_no_viene(): void
    {
        $this->post($this->rutaStore(), $this->payload())->assertRedirect();

        $this->assertNull(TrabajosEmpleado::latest('id')->first()->proceso_orden_id);
    }

    public function test_un_proceso_de_la_misma_orden_si_se_guarda(): void
    {
        $proceso = $this->procesoDeLaOrden();

        if ($proceso === null) {
            $this->markTestSkipped('La orden de prueba no tiene procesos');
        }

        $this->post($this->rutaStore(), $this->payload([
            'proceso_orden_id' => $proceso->id,
        ]))->assertRedirect();

        $trabajo = TrabajosEmpleado::latest('id')->first();

        $this->assertEquals($proceso->id, $trabajo->proceso_orden_id);
        $this->assertNotNull($trabajo->proceso_orden);
    }

    /**
     * Pasar de un proceso a "General a la OT" debe liberar el trabajo, no
     * dejar el proceso anterior pegado.
     */
    public function test_actualizar_a_general_libera_el_proceso_anterior(): void
    {
        $proceso = $this->procesoDeLaOrden();

        if ($proceso === null) {
            $this->markTestSkipped('La orden de prueba no tiene procesos');
        }

        $this->post($this->rutaStore(), $this->payload([
            'proceso_orden_id' => $proceso->id,
        ]))->assertRedirect();

        $trabajo = TrabajosEmpleado::latest('id')->first();
        $this->assertEquals($proceso->id, $trabajo->proceso_orden_id);

        // Ahora se edita dejandolo general
        $this->put("/procesos/ordenes-trabajo/{$this->orden->id}/trabajos-empleados/{$trabajo->id}", $this->payload([
            'proceso_orden_id' => '',
        ]))->assertRedirect();

        $this->assertNull(
            $trabajo->fresh()->proceso_orden_id,
            'Al volver a general, el proceso anterior debe quedar en NULL'
        );
    }

    /**
     * Un proceso pertenece a una sola orden: no se puede colar uno de otra.
     */
    public function test_un_proceso_de_otra_orden_se_rechaza(): void
    {
        $proceso = $this->procesoDeLaOrden();

        if ($proceso === null) {
            $this->markTestSkipped('La orden de prueba no tiene procesos');
        }

        // Se mueve el proceso a otra orden, dentro de la transaccion
        $otra = $this->crearOrden();

        $proceso->update(['orden_trabajo_id' => $otra->id]);

        $this->assertEquals(
            $otra->id,
            $proceso->fresh()->orden_trabajo_id,
            'El proceso deberia haberse movido de orden para que la prueba tenga sentido'
        );

        $antes = TrabajosEmpleado::count();

        $this->post($this->rutaStore(), $this->payload([
            'proceso_orden_id' => $proceso->id,
        ]))->assertSessionHasErrors('proceso_orden_id');

        $this->assertEquals(
            $antes,
            TrabajosEmpleado::count(),
            'No deberia haberse creado ningun trabajo'
        );
    }

    private function crearOrden(): OrdenesTrabajo
    {
        $original = OrdenesTrabajo::firstOrFail();

        return OrdenesTrabajo::create([
            'codigo' => 'OT-TEST-' . substr(md5(uniqid('', true)), 0, 6),
            'cliente_id' => $original->cliente_id,
            'fecha' => $original->fecha,
            'descripcion' => 'Orden creada por la prueba automatica',
            'peso_mineral' => $original->peso_mineral,
            'unidad_peso' => $original->unidad_peso,
            'estado' => 1,
        ]);
    }

    /**
     * El combo y la pantalla de detalle deben decir lo mismo, asi que la
     * etiqueta sale de una sola constante.
     */
    public function test_el_combo_ofrece_la_opcion_general(): void
    {
        $html = (string) view(
            'procesos.ordenes_trabajo.trabajos_empleados._form',
            [
                'tiposPago' => TiposPagoEmpleado::all(),
                'procesos' => ProcesosOrden::where('orden_trabajo_id', $this->orden->id)->get(),
            ]
        )->render();

        $this->assertStringContainsString(TrabajosEmpleado::ETIQUETA_GENERAL, $html);
        $this->assertStringContainsString('id="trabajoProcesoAyuda"', $html);

        // La opcion general debe ser la de valor vacio
        $this->assertMatchesRegularExpression(
            '/<option value="">\s*' . preg_quote(TrabajosEmpleado::ETIQUETA_GENERAL, '/') . '\s*<\/option>/',
            $html
        );

        // Y el combo no es obligatorio: se puede dejar en general
        $this->assertDoesNotMatchRegularExpression(
            '/id="trabajoProceso"[^>]*required/',
            $html
        );
    }

    public function test_la_pantalla_de_detalle_usa_la_misma_etiqueta(): void
    {
        $proceso = $this->procesoDeLaOrden();

        if ($proceso === null) {
            $this->markTestSkipped('La orden de prueba no tiene procesos');
        }

        $this->post($this->rutaStore(), $this->payload([
            'proceso_orden_id' => $proceso->id,
        ]))->assertRedirect();

        $trabajo = TrabajosEmpleado::latest('id')->first();

        // Con proceso: sale el codigo y la etapa del proceso
        $this->getJson("/procesos/ordenes-trabajo/{$this->orden->id}/trabajos-empleados/{$trabajo->id}")
            ->assertOk()
            ->assertJsonPath('proceso', $proceso->nombre_completo);

        // Sin proceso: sale la etiqueta compartida
        $trabajo->update(['proceso_orden_id' => null]);

        $this->getJson("/procesos/ordenes-trabajo/{$this->orden->id}/trabajos-empleados/{$trabajo->id}")
            ->assertOk()
            ->assertJsonPath('proceso', TrabajosEmpleado::ETIQUETA_GENERAL);
    }
}

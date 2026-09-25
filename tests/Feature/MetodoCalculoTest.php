<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\EmpleadosPago;
use App\Models\OrdenesTrabajo;
use App\Models\TiposPagoEmpleado;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * El metodo de calculo del tipo de pago decide si la cantidad
 * multiplica la tarifa o no.
 *
 * "Por trabajo" / "Fijo"  -> total = tarifa
 * "Por hora" / "Por dia" -> total = cantidad x tarifa
 */
class MetodoCalculoTest extends TestCase
{
    use DatabaseTransactions;

    private OrdenesTrabajo $orden;

    private Empleado $empleado;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = \App\Models\User::create([
            'name' => 'Admin',
            'email' => 'mc-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);

        $this->orden = OrdenesTrabajo::firstOrFail();
        $this->empleado = Empleado::where('estado', 1)->firstOrFail();
    }

    /**
     * Crear un tipo de pago con el metodo indicado y una tarifa vigente.
     */
    private function crearTipoConTarifa(
        string $metodo,
        float $tarifa,
        int $monedaId = 1
    ): TiposPagoEmpleado {
        $tipo = TiposPagoEmpleado::create([
            'nombre' => 'Test ' . $metodo,
            'codigo' => strtoupper(substr(md5($metodo . $tarifa), 0, 8)),
            'metodo_calculo' => $metodo,
            'estado' => 1,
        ]);

        EmpleadosPago::create([
            'empleado_id' => $this->empleado->id,
            'tipo_pago_id' => $tipo->id,
            'tarifa' => $tarifa,
            'moneda_id' => $monedaId,
            'fecha_inicio' => '2020-01-01',
            'fecha_fin' => null,
            'estado' => 1,
        ]);

        return $tipo;
    }

    private function registrarTrabajo(
        TiposPagoEmpleado $tipo,
        float $cantidad
    ) {
        return $this->post(
            "/procesos/ordenes-trabajo/{$this->orden->id}/trabajos-empleados",
            [
                'empleado_id' => $this->empleado->id,
                'tipo_pago_id' => $tipo->id,
                'fecha' => '2026-03-10',
                'hora_inicio' => '08:00',
                'hora_fin' => '17:00',
                'cantidad' => $cantidad,
                'unidad' => 'hora',
                'descripcion' => 'Prueba automatica',
            ]
        );
    }

    public function test_tarifa_ignora_la_cantidad(): void
    {
        $tipo = $this->crearTipoConTarifa(
            TiposPagoEmpleado::METODO_TARIFA,
            700
        );

        $this->registrarTrabajo($tipo, 8)->assertRedirect();

        $trabajo = \App\Models\TrabajosEmpleado::latest('id')->first();

        // 8 horas x 700 seria 5600, pero la tarifa ES el pago
        $this->assertEquals(8, (float) $trabajo->cantidad, 'La cantidad debe guardarse como dato');
        $this->assertEquals(700, (float) $trabajo->tarifa);
        $this->assertEquals(700, (float) $trabajo->total, 'El total debe ser la tarifa, no cantidad x tarifa');
        $this->assertEquals(700, (float) $trabajo->total_nio);
    }

    public function test_cantidad_por_tarifa_multiplica(): void
    {
        $tipo = $this->crearTipoConTarifa(
            TiposPagoEmpleado::METODO_CANTIDAD_X_TARIFA,
            100
        );

        $this->registrarTrabajo($tipo, 8)->assertRedirect();

        $trabajo = \App\Models\TrabajosEmpleado::latest('id')->first();

        $this->assertEquals(800, (float) $trabajo->total);
        $this->assertEquals(800, (float) $trabajo->total_nio);
    }

    public function test_la_cantidad_cero_no_altera_el_total_tarifa(): void
    {
        $tipo = $this->crearTipoConTarifa(
            TiposPagoEmpleado::METODO_TARIFA,
            500
        );

        $this->registrarTrabajo($tipo, 0)->assertRedirect();

        $trabajo = \App\Models\TrabajosEmpleado::latest('id')->first();

        $this->assertEquals(500, (float) $trabajo->total);
    }

    public function test_editar_recalcula_con_la_regla(): void
    {
        $tipo = $this->crearTipoConTarifa(
            TiposPagoEmpleado::METODO_TARIFA,
            700
        );

        $this->registrarTrabajo($tipo, 8)->assertRedirect();

        $trabajo = \App\Models\TrabajosEmpleado::latest('id')->first();
        $this->assertEquals(700, (float) $trabajo->total);

        // Se cambia la cantidad a 20: el total debe seguir siendo 700
        $this->put(
            "/procesos/ordenes-trabajo/{$this->orden->id}/trabajos-empleados/{$trabajo->id}",
            [
                'empleado_id' => $this->empleado->id,
                'tipo_pago_id' => $tipo->id,
                'fecha' => '2026-03-10',
                'cantidad' => 20,
                'unidad' => 'hora',
                'estado' => 1,
            ]
        )->assertRedirect();

        $trabajo->refresh();

        $this->assertEquals(20, (float) $trabajo->cantidad);
        $this->assertEquals(700, (float) $trabajo->total, 'La regla TARIFA debe seguir aplicando al editar');
    }

    public function test_tarifa_vigente_informa_el_metodo_de_calculo(): void
    {
        $tipo = $this->crearTipoConTarifa(
            TiposPagoEmpleado::METODO_TARIFA,
            700
        );

        $r = $this->getJson(
            "/procesos/ordenes-trabajo/{$this->orden->id}/trabajos-empleados/tarifa"
            . '?empleado_id=' . $this->empleado->id
            . '&tipo_pago_id=' . $tipo->id
            . '&fecha=2026-03-10'
        );

        $r->assertOk();
        $r->assertJsonPath('tarifa', 700);
        $r->assertJsonPath('metodo_calculo', TiposPagoEmpleado::METODO_TARIFA);
    }

    public function test_el_crud_guarda_el_metodo_de_calculo(): void
    {
        $this->post('/configuracion/tipos-pago-empleado', [
            'nombre' => 'Por tarea',
            'codigo' => 'TAREA',
            'metodo_calculo' => TiposPagoEmpleado::METODO_TARIFA,
            'descripcion' => 'Pago unico por tarea',
            'estado' => 1,
        ])->assertRedirect();

        $creado = TiposPagoEmpleado::where('codigo', 'TAREA')->firstOrFail();

        $this->assertEquals(
            TiposPagoEmpleado::METODO_TARIFA,
            $creado->metodo_calculo,
            'El metodo de calculo debe guardarse, no descartarse en silencio'
        );
    }

    public function test_el_crud_rechaza_un_metodo_desconocido(): void
    {
        $this->post('/configuracion/tipos-pago-empleado', [
            'nombre' => 'Inventado',
            'codigo' => 'X',
            'metodo_calculo' => 'LO_QUE_SEA',
            'estado' => 1,
        ])->assertSessionHasErrors('metodo_calculo');
    }

    /**
     * Regresion: el controlador paso 'metodosCalculo' al compact() sin
     * definir la variable, y create()/edit() devolvian un error 500.
     */
    public function test_el_formulario_de_crear_carga_con_las_opciones_de_calculo(): void
    {
        $r = $this->get('/configuracion/tipos-pago-empleado/create');

        $r->assertOk();
        $r->assertSee('name="metodo_calculo"', false);
        $r->assertSee('value="' . TiposPagoEmpleado::METODO_TARIFA . '"', false);
        $r->assertSee('value="' . TiposPagoEmpleado::METODO_CANTIDAD_X_TARIFA . '"', false);
        $r->assertSee('name="codigo"', false);
    }

    public function test_el_formulario_de_editar_carga_y_marca_el_metodo_actual(): void
    {
        $tipo = $this->crearTipoConTarifa(
            TiposPagoEmpleado::METODO_TARIFA,
            700
        );

        $r = $this->get('/configuracion/tipos-pago-empleado/' . $tipo->id . '/edit');

        $r->assertOk();
        $r->assertSee('name="metodo_calculo"', false);

        // La opcion TARIFA debe venir marcada
        $this->assertMatchesRegularExpression(
            '/value="TARIFA"\s+selected/',
            $r->getContent()
        );
    }

    public function test_el_calculo_del_modelo(): void
    {
        $tarifa = new TiposPagoEmpleado(['metodo_calculo' => TiposPagoEmpleado::METODO_TARIFA]);
        $this->assertEquals(700.0, $tarifa->calcularTotal(8, 700));

        $porHora = new TiposPagoEmpleado(['metodo_calculo' => TiposPagoEmpleado::METODO_CANTIDAD_X_TARIFA]);
        $this->assertEquals(800.0, $porHora->calcularTotal(8, 100));
        $this->assertEquals(500.0, $porHora->calcularTotal(25, 20));
    }

    public function test_el_show_muestra_el_metodo_y_el_codigo(): void
    {
        $tipo = $this->crearTipoConTarifa(
            TiposPagoEmpleado::METODO_TARIFA,
            700
        );

        $r = $this->get('/configuracion/tipos-pago-empleado/' . $tipo->id);

        $r->assertOk();
        $r->assertSee('Método de cálculo');
        $r->assertSee(TiposPagoEmpleado::METODO_TARIFA);
        $r->assertSee('La tarifa es el pago total');
        $r->assertSee('total = tarifa');
        $r->assertSee($tipo->codigo);
    }

    public function test_el_show_explica_la_regla_multiplicativa(): void
    {
        $tipo = $this->crearTipoConTarifa(
            TiposPagoEmpleado::METODO_CANTIDAD_X_TARIFA,
            100
        );

        $r = $this->get('/configuracion/tipos-pago-empleado/' . $tipo->id);

        $r->assertOk();
        $r->assertSee(TiposPagoEmpleado::METODO_CANTIDAD_X_TARIFA);
        $r->assertSee('total = cantidad × tarifa');
    }

    /**
     * Select2 se carga en create y edit, y solo se marca el select de
     * metodo_calculo: el de estado es un checkbox disfrazado, no un combo.
     */
    public function test_create_y_edit_cargan_select2(): void
    {
        foreach (['create'] as $accion) {
            $this->get('/configuracion/tipos-pago-empleado/' . $accion)
                ->assertOk()
                ->assertSee('custom-select2-', false)
                ->assertSee('select2-init-', false)
                ->assertSee('class="form-select select2', false);
        }

        $tipo = $this->crearTipoConTarifa(
            TiposPagoEmpleado::METODO_TARIFA,
            700
        );

        $this->get('/configuracion/tipos-pago-empleado/' . $tipo->id . '/edit')
            ->assertOk()
            ->assertSee('select2-init-', false)
            ->assertSee('class="form-select select2', false);
    }

    public function test_el_show_no_advierte_si_la_regla_no_es_tarifa(): void
    {
        $tipo = $this->crearTipoConTarifa(
            TiposPagoEmpleado::METODO_CANTIDAD_X_TARIFA,
            100
        );

        $r = $this->get('/configuracion/tipos-pago-empleado/' . $tipo->id);

        $r->assertOk();
        $r->assertDontSee('conservan el total que se les calculó');
    }
}

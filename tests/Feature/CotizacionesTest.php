<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Moneda;
use App\Models\OrdenesTrabajo;
use App\Models\TiposParticipacion;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CotizacionesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin cotizaciones',
            'email' => 'cotizaciones-'.uniqid().'@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

    public function test_el_menu_comercial_abre_el_listado_de_cotizaciones(): void
    {
        $this->get(route('comercial.cotizaciones.index'))
            ->assertOk()
            ->assertSee('Cotizaciones')
            ->assertSee(route('comercial.cotizaciones.index'));
    }

    public function test_el_borrador_se_puede_guardar_y_editar(): void
    {
        $cotizacion = $this->crearCotizacion();

        $this->assertSame(Cotizacion::ESTADO_BORRADOR, $cotizacion->estado);
        $this->assertFalse($cotizacion->tipoParticipacion->sePuedeBorrar());
        $this->assertFalse($cotizacion->unidadMedida->puedeEliminarse());

        $this->get(route('comercial.cotizaciones.show', $cotizacion))
            ->assertOk()
            ->assertSee('window.print()')
            ->assertSee('Cotización de procesamiento');

        $this->put(route('comercial.cotizaciones.update', $cotizacion), $this->datosCotizacion([
            'descripcion' => 'Material de la veta norte',
        ]))->assertRedirect(route('comercial.cotizaciones.show', $cotizacion));

        $this->assertSame('Material de la veta norte', $cotizacion->fresh()->descripcion);
    }

    public function test_la_aceptacion_crea_una_orden_y_copia_los_porcentajes_al_acuerdo(): void
    {
        $cotizacion = $this->crearCotizacion();

        $this->post(route('comercial.cotizaciones.enviar', $cotizacion))
            ->assertRedirect(route('comercial.cotizaciones.show', $cotizacion));

        $this->post(route('comercial.cotizaciones.aceptar', $cotizacion))
            ->assertRedirect();

        $cotizacion->refresh();
        $this->assertSame(Cotizacion::ESTADO_ACEPTADA, $cotizacion->estado);
        $this->assertNotNull($cotizacion->orden_trabajo_id);

        $orden = OrdenesTrabajo::findOrFail($cotizacion->orden_trabajo_id);
        $this->assertSame($cotizacion->cliente_id, $orden->cliente_id);
        $acuerdo = $orden->acuerdos_ordens_where_orden_trabajo()->firstOrFail();
        $this->assertSame(25.0, (float) $acuerdo->porcentaje_cliente);
        $this->assertSame(75.0, (float) $acuerdo->porcentaje_empresa);
        $this->assertSame((float) $cotizacion->peso_mineral, (float) $orden->peso_mineral);
    }

    public function test_la_tarifa_fija_y_su_moneda_se_copian_al_acuerdo(): void
    {
        $cotizacion = $this->crearCotizacion('tarifa');

        $this->post(route('comercial.cotizaciones.enviar', $cotizacion));
        $this->post(route('comercial.cotizaciones.aceptar', $cotizacion));

        $acuerdo = OrdenesTrabajo::findOrFail($cotizacion->fresh()->orden_trabajo_id)
            ->acuerdos_ordens_where_orden_trabajo()
            ->firstOrFail();

        $this->assertSame(250.0, (float) $acuerdo->tarifa_servicio);
        $this->assertSame((int) $cotizacion->moneda_id, (int) $acuerdo->moneda_id);
        $this->assertNull($acuerdo->porcentaje_cliente);
    }

    public function test_una_unidad_del_catalogo_de_mas_de_diez_caracteres_se_conserva_al_aceptar(): void
    {
        $unidad = UnidadMedida::create([
            'nombre' => 'tonelada larga',
            'estado' => true,
        ]);
        $cotizacion = $this->crearCotizacion('porcentajes', [
            'unidad_peso_id' => $unidad->id,
        ]);

        $this->post(route('comercial.cotizaciones.enviar', $cotizacion));
        $this->post(route('comercial.cotizaciones.aceptar', $cotizacion));

        $orden = OrdenesTrabajo::findOrFail($cotizacion->fresh()->orden_trabajo_id);
        $this->assertSame('tonelada larga', $orden->unidad_peso);
    }

    public function test_una_cotizacion_enviada_no_se_puede_editar(): void
    {
        $cotizacion = $this->crearCotizacion();
        $this->post(route('comercial.cotizaciones.enviar', $cotizacion));

        $this->put(route('comercial.cotizaciones.update', $cotizacion), [])
            ->assertNotFound();
    }

    public function test_no_se_puede_enviar_ni_aceptar_una_cotizacion_vencida(): void
    {
        $datos = $this->datosCotizacion([
            'fecha' => '2026-01-01',
            'fecha_vencimiento' => '2026-01-01',
        ]);
        $this->post(route('comercial.cotizaciones.store'), $datos)->assertRedirect();
        $cotizacion = Cotizacion::query()->latest('id')->firstOrFail();

        $this->from(route('comercial.cotizaciones.show', $cotizacion))
            ->post(route('comercial.cotizaciones.enviar', $cotizacion))
            ->assertRedirect(route('comercial.cotizaciones.show', $cotizacion))
            ->assertSessionHasErrors('fecha_vencimiento');

        $this->delete(route('comercial.cotizaciones.destroy', $cotizacion))
            ->assertNotFound();
        $this->assertNotSoftDeleted($cotizacion);
    }

    public function test_porcentajes_distintos_de_cien_no_se_guardan(): void
    {
        $this->from(route('comercial.cotizaciones.create'))
            ->post(route('comercial.cotizaciones.store'), $this->datosCotizacion([
                'porcentaje_cliente' => 30,
                'porcentaje_empresa' => 60,
            ]))
            ->assertRedirect(route('comercial.cotizaciones.create'))
            ->assertSessionHasErrors('porcentaje_empresa');

        $this->assertDatabaseMissing('cotizaciones', ['porcentaje_cliente' => 30]);
    }

    public function test_no_se_puede_eliminar_la_orden_creada_desde_una_cotizacion_aceptada(): void
    {
        $cotizacion = $this->crearCotizacion();
        $this->post(route('comercial.cotizaciones.enviar', $cotizacion));
        $this->post(route('comercial.cotizaciones.aceptar', $cotizacion));
        $orden = OrdenesTrabajo::findOrFail($cotizacion->fresh()->orden_trabajo_id);

        $this->from(route('procesos.ordenes_trabajo.show', $orden))
            ->delete(route('procesos.ordenes_trabajo.destroy', $orden))
            ->assertRedirect(route('procesos.ordenes_trabajo.show', $orden));

        $this->assertNotSoftDeleted($orden);
    }

    private function crearCotizacion(string $modalidad = 'porcentajes', array $extra = []): Cotizacion
    {
        $this->post(route('comercial.cotizaciones.store'), $this->datosCotizacion([
            'modalidad' => $modalidad,
            ...$extra,
        ]))->assertRedirect();

        return Cotizacion::query()->latest('id')->firstOrFail();
    }

    private function datosCotizacion(array $extra = []): array
    {
        $cliente = Cliente::query()->where('estado', true)->firstOrFail();
        $unidad = UnidadMedida::query()->where('estado', true)->firstOrFail();
        $tipo = TiposParticipacion::paraRegistrar()->firstOrFail();
        $moneda = Moneda::query()->where('estado', true)->firstOrFail();
        $base = Moneda::base();

        $datos = array_merge([
            'cliente_id' => $cliente->id,
            'fecha' => today()->toDateString(),
            'fecha_vencimiento' => today()->addDays(30)->toDateString(),
            'descripcion' => 'Procesamiento de mineral',
            'peso_mineral' => '12.500',
            'unidad_peso_id' => $unidad->id,
            'tipo_participacion_id' => $tipo->id,
            'modalidad' => 'porcentajes',
            'porcentaje_cliente' => '25.0000',
            'porcentaje_empresa' => '75.0000',
            'tarifa_servicio' => '',
            'moneda_id' => $base?->id ?? $moneda->id,
            'observaciones' => 'Condiciones acordadas',
        ], $extra);

        if (($datos['modalidad'] ?? 'porcentajes') === 'tarifa') {
            return array_merge($datos, [
                'modalidad' => 'tarifa',
                'porcentaje_cliente' => '',
                'porcentaje_empresa' => '',
                'tarifa_servicio' => '250.00',
                'moneda_id' => $moneda->id,
            ]);
        }

        return $datos;
    }
}

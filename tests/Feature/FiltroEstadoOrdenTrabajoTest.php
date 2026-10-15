<?php

namespace Tests\Feature;

use App\Models\OrdenesTrabajo;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FiltroEstadoOrdenTrabajoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin filtro OT',
            'email' => 'orden-filtro-'.uniqid().'@test.local',
            'password' => bcrypt('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

    public function test_el_filtro_estado_cero_llega_a_datatables_y_solo_muestra_ordenes_canceladas(): void
    {
        $base = OrdenesTrabajo::query()->firstOrFail();
        $sufijo = strtoupper(Str::random(8));
        $cancelada = $this->crearOrden($base, 'OT-FILTRO-CANCELADA-'.$sufijo, OrdenesTrabajo::ESTADO_CANCELADA);
        $pendiente = $this->crearOrden($base, 'OT-FILTRO-PENDIENTE-'.$sufijo, OrdenesTrabajo::ESTADO_PENDIENTE);

        $pagina = $this->get(route('procesos.ordenes_trabajo.index', ['estado' => 0]))
            ->assertOk();

        $pagina->assertSee('estado=0')
            ->assertSee('Órdenes registradas')
            ->assertSee('Pendientes')
            ->assertSee('En proceso')
            ->assertSee('Finalizadas')
            ->assertSee('Filtrar órdenes')
            ->assertSee('Limpiar')
            ->assertSee('La búsqueda de la tabla se aplica dentro del estado seleccionado.')
            ->assertSee('href="'.route('procesos.ordenes_trabajo.index').'"', false)
            ->assertSee('value="0" selected', false);

        $datos = $this->withHeader('X-Requested-With', 'XMLHttpRequest')->getJson(route('procesos.ordenes_trabajo.index', [
            'estado' => 0,
            'draw' => 1,
            'start' => 0,
            'length' => 100,
        ]))->assertOk();

        $codigos = collect($datos->json('data'))->pluck('codigo');

        $this->assertContains($cancelada->codigo, $codigos);
        $this->assertNotContains($pendiente->codigo, $codigos);
        $this->assertTrue($codigos->every(fn (string $codigo) =>
            (int) OrdenesTrabajo::query()->where('codigo', $codigo)->value('estado') === OrdenesTrabajo::ESTADO_CANCELADA
        ));

        $todos = $this->withHeader('X-Requested-With', 'XMLHttpRequest')->getJson(route('procesos.ordenes_trabajo.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 100,
        ]))->assertOk();
        $codigosSinFiltro = collect($todos->json('data'))->pluck('codigo');

        $this->assertContains($cancelada->codigo, $codigosSinFiltro);
        $this->assertContains($pendiente->codigo, $codigosSinFiltro);
    }

    private function crearOrden(OrdenesTrabajo $base, string $codigo, int $estado): OrdenesTrabajo
    {
        return OrdenesTrabajo::create([
            'codigo' => $codigo,
            'cliente_id' => $base->cliente_id,
            'fecha' => today(),
            'descripcion' => 'Orden para verificar el filtro por estado',
            'peso_mineral' => 1,
            'unidad_peso' => $base->unidad_peso,
            'unidad_peso_id' => $base->unidad_peso_id,
            'estado' => $estado,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\OrdenesTrabajo;
use App\Models\User;
use App\Services\NavegacionMenu;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NavegacionMenuTest extends TestCase
{
    use DatabaseTransactions;

    public function test_usuario_sin_permisos_solo_recibe_inicio_y_no_se_filtran_destinos_restringidos(): void
    {
        $user = User::factory()->create();
        $this->assertSame([], app(NavegacionMenu::class)->opciones(null));
        $options = app(NavegacionMenu::class)->opciones($user);
        $this->assertSame(['home'], array_column($options, 'id'));
        $response = $this->actingAs($user)->get('/')->assertOk();
        $options = $this->payload($response->getContent());
        $this->assertSame(['home'], array_column($options, 'id'));
        $response->assertSee('Buscar en el menú')->assertSee('js/menu-search.js', false);
    }

    public function test_los_accesos_de_consulta_no_ofrecen_crear_registros_sin_permiso(): void
    {
        $user = User::factory()->create();
        $user->syncPermissions(['ordenes_trabajo.view', 'cobros.view', 'clientes.view']);
        $options = collect(app(NavegacionMenu::class)->opciones($user))->keyBy('id');
        $this->assertTrue($options['procesos-cobros-index']['quick']);
        $this->assertFalse($options->has('procesos-cobros-create'));
        $this->assertFalse($options->has('procesos-ordenes_trabajo-create'));
        $this->assertFalse($options->has('configuracion-monedas-index'));
        $response = $this->actingAs($user)->get('/')->assertOk();
        $this->assertSame($options->keys()->all(), array_column($this->payload($response->getContent()), 'id'));
        $response->assertSee('mina-shortcuts-grid', false);
    }

    public function test_el_catalogo_cubre_el_menu_y_todos_los_destinos_tienen_ruta_get(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $options = app(NavegacionMenu::class)->opciones($user);
        $urls = array_column($options, 'url');
        preg_match_all("/route\('([^']+)'\)/", file_get_contents(resource_path('views/components/menu/vertical-menu.blade.php')), $matches);
        foreach (array_unique($matches[1]) as $name) {
            $this->assertContains(route($name), $urls, 'Falta el menú '.$name);
            $this->assertContains('GET', Route::getRoutes()->getByName($name)->methods());
        }
        $this->assertCount(8, array_filter($options, fn ($o) => $o['quick']));
        $this->assertCount(count($options), array_unique(array_column($options, 'id')));
    }

    public function test_acuerdos_ingresos_y_costos_orientan_a_elegir_ot_o_abren_su_seccion_actual(): void
    {
        $user = User::factory()->create();
        $user->syncPermissions(['ordenes_trabajo.view', 'acuerdos_orden.view', 'ingresos.view', 'movimientos_costo.view']);
        $service = app(NavegacionMenu::class);
        $global = collect($service->opciones($user))->keyBy('id');
        $this->assertSame(route('procesos.ordenes_trabajo.index'), $global['tarea-ot-acuerdos']['url']);
        $this->assertStringContainsString('Primero abra una OT', $global['tarea-ot-acuerdos']['description']);
        $order = OrdenesTrabajo::firstOrFail();
        $current = collect($service->opciones($user, $order))->keyBy('id');
        $this->assertSame(route('procesos.ordenes_trabajo.show', $order).'#acuerdos', $current['tarea-ot-acuerdos']['url']);
        $this->assertSame(route('procesos.ordenes_trabajo.show', $order).'#ingresos', $current['tarea-ot-ingresos']['url']);
        $this->assertSame(route('procesos.ordenes_trabajo.show', $order).'#costos-orden', $current['tarea-ot-costos']['url']);
        $response = $this->actingAs($user)->get(route('procesos.ordenes_trabajo.show', $order))->assertOk()
            ->assertSee('id="ingresos"', false)->assertSee('id="costos-orden"', false);
        $rendered = collect($this->payload($response->getContent()))->keyBy('id');
        $this->assertSame($current['tarea-ot-acuerdos']['url'], $rendered['tarea-ot-acuerdos']['url']);
    }

    private function payload(string $html): array
    {
        preg_match('/<script type="application\/json" data-search-destinations>(.*?)<\/script>/s', $html, $matches);
        $this->assertArrayHasKey(1, $matches);

        return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    }
}

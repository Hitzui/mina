<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('reportes.view', 'web');
        Permission::findOrCreate('reportes.export', 'web');
    }

    public function test_el_centro_de_reportes_muestra_informes_y_filtros(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes.view', 'reportes.export']);

        $this->actingAs($usuario)
            ->get(route('reportes.index', [
                'tipo' => 'ordenes',
                'fecha_desde' => '2026-10-01',
                'fecha_hasta' => '2026-10-31',
            ]))
            ->assertOk()
            ->assertSee('Órdenes de trabajo (resumen)')
            ->assertSee('Ingresos NIO')
            ->assertSee('Costo total NIO')
            ->assertSee('Excel')
            ->assertSee('PDF');

        $this->get(route('reportes.index', [
            'categoria' => 'ordenes',
            'tipo' => 'costos',
            'orden' => 'OT-0001',
            'fecha_desde' => '2026-10-01',
            'fecha_hasta' => '2026-10-31',
        ]))->assertOk()
            ->assertSee('Categoría')
            ->assertSee('Costos por orden y proceso')
            ->assertSee('Costo NIO');
    }

    public function test_exportar_reporte_en_excel_y_pdf(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes.view', 'reportes.export']);
        $parametros = [
            'tipo' => 'clientes',
            'fecha_desde' => '2026-10-01',
            'fecha_hasta' => '2026-10-31',
        ];

        $this->actingAs($usuario)
            ->get(route('reportes.exportar', ['formato' => 'xlsx'] + $parametros))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->get(route('reportes.exportar', ['formato' => 'pdf'] + $parametros))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_los_reportes_requieren_permiso_de_consulta_y_exportacion(): void
    {
        $sinPermisos = $this->usuarioConPermisos([]);

        $this->actingAs($sinPermisos)
            ->get(route('reportes.index'))
            ->assertForbidden();

        $soloConsulta = $this->usuarioConPermisos(['reportes.view']);
        $this->actingAs($soloConsulta)
            ->get(route('reportes.exportar', ['formato' => 'pdf']))
            ->assertForbidden();
    }

    public function test_todos_los_informes_se_pueden_consultar(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes.view']);
        $this->actingAs($usuario);

        foreach (['caja', 'cierres', 'cobros', 'ordenes', 'procesos', 'costos', 'ingresos', 'produccion', 'trabajos', 'compras', 'inventario', 'clientes'] as $tipo) {
            $this->get(route('reportes.index', [
                'tipo' => $tipo,
                'fecha_desde' => '2026-10-01',
                'fecha_hasta' => '2026-10-31',
            ]))->assertOk();
        }
    }

    private function usuarioConPermisos(array $permisos): User
    {
        $usuario = User::create([
            'name' => 'Usuario de reportes',
            'email' => 'reportes-'.Str::lower(Str::random(10)).'@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $usuario->syncPermissions($permisos);

        return $usuario;
    }
}

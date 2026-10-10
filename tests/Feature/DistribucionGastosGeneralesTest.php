<?php

namespace Tests\Feature;

use App\Models\OrdenesTrabajo;
use App\Services\DistribucionGastosGenerales;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DistribucionGastosGeneralesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_prorratea_en_centavos_y_entrega_el_residuo_al_proceso_mayor(): void
    {
        $orden = $this->ordenNueva();
        $etapaId = (int) DB::table('etapas')->whereNull('deleted_at')->value('id');
        $monedaId = (int) DB::table('monedas')
            ->where('estado', true)
            ->where('es_moneda_base', true)
            ->value('id');
        $categoriaId = (int) DB::table('categorias_costos')
            ->where('estado', true)
            ->whereNull('deleted_at')
            ->value('id');

        if (! $etapaId || ! $monedaId || ! $categoriaId) {
            $this->markTestSkipped('Hace falta una etapa, moneda base y categoría de costo activa.');
        }

        $procesoIds = [];
        foreach (range(1, 3) as $numero) {
            $procesoIds[] = DB::table('procesos_orden')->insertGetId([
                'orden_trabajo_id' => $orden->id,
                'etapa_id' => $etapaId,
                'codigo' => 'PRORRATEO-'.uniqid().'-'.$numero,
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('movimientos_costos')->insert([
                'orden_trabajo_id' => $orden->id,
                'proceso_orden_id' => end($procesoIds),
                'categoria_costo_id' => $categoriaId,
                'fecha' => now()->toDateString(),
                'descripcion' => 'Costo directo de prueba',
                'cantidad' => 1,
                'costo_unitario' => 1,
                'costo_total' => 1,
                'costo_unitario_nio' => 1,
                'costo_total_nio' => 1,
                'moneda_id' => $monedaId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('movimientos_costos')->insert([
            'orden_trabajo_id' => $orden->id,
            'proceso_orden_id' => null,
            'categoria_costo_id' => $categoriaId,
            'fecha' => now()->toDateString(),
            'descripcion' => 'Gasto general de prueba',
            'cantidad' => 1,
            'costo_unitario' => 10,
            'costo_total' => 10,
            'costo_unitario_nio' => 10,
            'costo_total_nio' => 10,
            'moneda_id' => $monedaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resultado = app(DistribucionGastosGenerales::class)->paraOrden($orden);

        $this->assertSame(10.0, $resultado['gastos_generales_nio']);
        $this->assertSame(10.0, $resultado['gastos_generales_distribuidos_nio']);
        $this->assertSame(0.0, $resultado['gastos_generales_pendientes_nio']);
        $this->assertSame(3.34, $resultado['por_proceso'][$procesoIds[0]]['gasto_general_asignado_nio']);
        $this->assertSame(3.33, $resultado['por_proceso'][$procesoIds[1]]['gasto_general_asignado_nio']);
        $this->assertSame(3.33, $resultado['por_proceso'][$procesoIds[2]]['gasto_general_asignado_nio']);
        $this->assertSame(4.34, $resultado['por_proceso'][$procesoIds[0]]['costo_total_con_generales_nio']);
    }

    public function test_no_distribuye_si_un_costo_directo_extranjero_no_tiene_equivalente_nio(): void
    {
        $orden = $this->ordenNueva();
        $etapaId = (int) DB::table('etapas')->whereNull('deleted_at')->value('id');
        $monedaId = (int) DB::table('monedas')
            ->where('estado', true)
            ->where('es_moneda_base', false)
            ->value('id');
        $monedaBaseId = (int) DB::table('monedas')
            ->where('estado', true)
            ->where('es_moneda_base', true)
            ->value('id');
        $categoriaId = (int) DB::table('categorias_costos')
            ->where('estado', true)
            ->whereNull('deleted_at')
            ->value('id');

        if (! $etapaId || ! $monedaId || ! $monedaBaseId || ! $categoriaId) {
            $this->markTestSkipped('Hace falta una etapa, monedas base y extranjera, y categoría de costo activa.');
        }

        $procesoId = DB::table('procesos_orden')->insertGetId([
            'orden_trabajo_id' => $orden->id,
            'etapa_id' => $etapaId,
            'codigo' => 'P-INCOMPLETO-'.uniqid(),
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('movimientos_costos')->insert([
            'orden_trabajo_id' => $orden->id,
            'proceso_orden_id' => $procesoId,
            'categoria_costo_id' => $categoriaId,
            'fecha' => now()->toDateString(),
            'descripcion' => 'Costo extranjero sin conversión histórica',
            'cantidad' => 1,
            'costo_unitario' => 100,
            'costo_total' => 100,
            'moneda_id' => $monedaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('movimientos_costos')->insert([
            'orden_trabajo_id' => $orden->id,
            'proceso_orden_id' => null,
            'categoria_costo_id' => $categoriaId,
            'fecha' => now()->toDateString(),
            'descripcion' => 'Gasto general conocido',
            'cantidad' => 1,
            'costo_unitario' => 20,
            'costo_total' => 20,
            'costo_unitario_nio' => 20,
            'costo_total_nio' => 20,
            'moneda_id' => $monedaBaseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resultado = app(DistribucionGastosGenerales::class)->paraOrden($orden);

        $this->assertSame(1, $resultado['registros_directos_sin_equivalente']);
        $this->assertFalse($resultado['base_directa_completa']);
        $this->assertSame(0.0, $resultado['gastos_generales_distribuidos_nio']);
        $this->assertSame(20.0, $resultado['gastos_generales_pendientes_nio']);
        $this->assertSame(0.0, $resultado['por_proceso'][$procesoId]['gasto_general_asignado_nio']);
    }

    private function ordenNueva(): OrdenesTrabajo
    {
        $clienteId = (int) DB::table('clientes')
            ->whereNull('deleted_at')
            ->value('id');

        if (! $clienteId) {
            $this->markTestSkipped('Hace falta un cliente activo para crear una orden de prueba.');
        }

        $id = DB::table('ordenes_trabajo')->insertGetId([
            'codigo' => 'PRORRATEO-'.uniqid(),
            'cliente_id' => $clienteId,
            'fecha' => now()->toDateString(),
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return OrdenesTrabajo::findOrFail($id);
    }
}

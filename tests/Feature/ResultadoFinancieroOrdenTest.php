<?php

namespace Tests\Feature;

use App\Models\AcuerdosOrden;
use App\Models\Ingreso;
use App\Models\Liquidacione;
use App\Models\Moneda;
use App\Models\MovimientosCosto;
use App\Models\OrdenesTrabajo;
use App\Models\PreciosOro;
use App\Models\TiposIngreso;
use App\Models\TiposParticipacion;
use App\Services\ResultadoFinancieroOrden;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResultadoFinancieroOrdenTest extends TestCase
{
    use DatabaseTransactions;

    public function test_calcula_ingresos_reales_y_tarifa_pactada_por_separado(): void
    {
        [$orden, $base, $tipo, $categoria] = $this->datosNecesarios();
        $fecha = '2099-02-03';

        AcuerdosOrden::create([
            'orden_trabajo_id' => $orden->id,
            'tipo_participacion_id' => $tipo,
            'tarifa_servicio' => 5000,
            'moneda_id' => $base->id,
            'estado' => true,
        ]);
        Ingreso::create([
            'orden_trabajo_id' => $orden->id,
            'tipo_ingreso_id' => TiposIngreso::where('estado', true)->value('id'),
            'fecha' => $fecha,
            'cantidad' => 1,
            'precio_unitario' => 5000,
            'total' => 5000,
            'precio_unitario_nio' => 5000,
            'total_nio' => 5000,
            'moneda_id' => $base->id,
            'estado' => 1,
        ]);
        MovimientosCosto::create([
            'orden_trabajo_id' => $orden->id,
            'proceso_orden_id' => null,
            'categoria_costo_id' => $categoria,
            'fecha' => $fecha,
            'descripcion' => 'Costo de prueba',
            'cantidad' => 1,
            'costo_unitario' => 3100,
            'costo_total' => 3100,
            'costo_unitario_nio' => 3100,
            'costo_total_nio' => 3100,
            'moneda_id' => $base->id,
        ]);

        $resultado = app(ResultadoFinancieroOrden::class)->paraOrden($orden);

        $this->assertSame(5000.0, $resultado['ingresos_registrados_nio']);
        $this->assertSame(5000.0, $resultado['tarifa_pactada_nio']);
        $this->assertSame(3100.0, $resultado['costo_total_nio']);
        $this->assertSame(1900.0, $resultado['resultado_real_nio']);
        $this->assertSame(1900.0, $resultado['resultado_proyectado_nio']);
        $this->assertTrue($resultado['completo']);
    }

    public function test_valora_solo_los_gramos_liquidados_para_la_empresa_con_precio_historico(): void
    {
        [$orden, $base, $tipo, $categoria] = $this->datosNecesarios();
        $fecha = '2099-03-05';

        AcuerdosOrden::create([
            'orden_trabajo_id' => $orden->id,
            'tipo_participacion_id' => $tipo,
            'porcentaje_cliente' => 30,
            'porcentaje_empresa' => 70,
            'moneda_id' => $base->id,
            'estado' => true,
        ]);
        PreciosOro::create([
            'fecha' => $fecha,
            'precio' => 1000,
            'unidad' => 'gramo',
            'moneda_id' => $base->id,
            'fuente' => 'Prueba rentabilidad',
        ]);
        Liquidacione::create([
            'orden_trabajo_id' => $orden->id,
            'fecha' => $fecha,
            'gramos_recuperados' => 10,
            'gramos_cliente' => 3,
            'gramos_empresa' => 7,
            'porcentaje_cliente' => 30,
            'porcentaje_empresa' => 70,
        ]);
        MovimientosCosto::create([
            'orden_trabajo_id' => $orden->id,
            'proceso_orden_id' => null,
            'categoria_costo_id' => $categoria,
            'fecha' => $fecha,
            'descripcion' => 'Costo de tratamiento',
            'cantidad' => 1,
            'costo_unitario' => 3100,
            'costo_total' => 3100,
            'costo_unitario_nio' => 3100,
            'costo_total_nio' => 3100,
            'moneda_id' => $base->id,
        ]);

        $resultado = app(ResultadoFinancieroOrden::class)->paraOrden($orden);

        $this->assertSame(7.0, $resultado['oro_empresa_gramos']);
        $this->assertSame(7000.0, $resultado['oro_empresa_nio']);
        $this->assertSame(3900.0, $resultado['resultado_real_nio']);
        $this->assertSame(3900.0, $resultado['resultado_proyectado_nio']);
    }

    /** @return array{OrdenesTrabajo, Moneda, int, int} */
    private function datosNecesarios(): array
    {
        $clienteId = DB::table('clientes')->whereNull('deleted_at')->value('id');
        $base = Moneda::base();
        $tipo = TiposParticipacion::where('estado', true)->whereNull('deleted_at')->value('id');
        $tipoIngreso = TiposIngreso::where('estado', true)->whereNull('deleted_at')->value('id');
        $categoria = DB::table('categorias_costos')->where('estado', true)->whereNull('deleted_at')->value('id');

        if (! $clienteId || ! $base || ! $tipo || ! $tipoIngreso || ! $categoria) {
            $this->markTestSkipped('Hace falta cliente, moneda base, tipos activos y categoría de costo.');
        }

        $orden = OrdenesTrabajo::create([
            'codigo' => 'OT-RESULTADO-'.strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'cliente_id' => $clienteId,
            'fecha' => '2099-01-01',
            'descripcion' => 'Prueba de rentabilidad',
            'peso_mineral' => 1,
            'unidad_peso' => 'kg',
            'estado' => OrdenesTrabajo::ESTADO_EN_PROCESO,
        ]);

        return [$orden, $base, (int) $tipo, (int) $categoria];
    }
}

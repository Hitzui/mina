<?php

namespace Tests\Feature;

use App\Models\Equipo;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesoEquipo;
use App\Models\ProcesosOrden;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Depreciacion de los equipos usados en cada proceso.
 *
 * Un equipo puede trabajar en varios procesos seguidos, nunca en el
 * mismo instante. Y la depreciacion de cada uso queda guardada como foto,
 * para que tocar despues la vida util del equipo no mueva los costos ya
 * registrados.
 */
class DepreciacionEquiposTest extends TestCase
{
    use DatabaseTransactions;

    private ProcesosOrden $proceso;

    private ProcesosOrden $otroProceso;

    protected function setUp(): void
    {
        parent::setUp();

        $orden = OrdenesTrabajo::firstOrFail();
        $this->proceso = $this->crearProceso($orden);
        $this->otroProceso = $this->crearProceso($orden);
    }

    private function crearProceso(OrdenesTrabajo $orden): ProcesosOrden
    {
        $p = new ProcesosOrden();
        $p->orden_trabajo_id = $orden->id;
        $p->etapa_id = DB::table('etapas')->first()->id;
        $p->codigo = 'P-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $p->fecha_inicio = '2026-01-01 08:00:00';
        $p->fecha_fin = '2026-12-31 17:00:00';
        $p->peso_entrada = 1;
        $p->peso_salida = 1;
        $p->estado = 1;
        $p->save();

        return $p;
    }

    private function crearEquipo(array $attrs = []): Equipo
    {
        $e = new Equipo();
        $e->codigo = 'EQ-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $e->nombre = 'Equipo de prueba';
        $e->valor_adquisicion = 120000;
        $e->valor_residual = 20000;
        $e->vida_util_meses = 120;
        $e->estado = 1;

        foreach ($attrs as $k => $v) {
            $e->$k = $v;
        }

        $e->save();

        return $e;
    }

    private function asignar(Equipo $equipo, ProcesosOrden $proceso, string $desde, ?string $hasta): ProcesoEquipo
    {
        $a = new ProcesoEquipo();
        $a->proceso_orden_id = $proceso->id;
        $a->equipo_id = $equipo->id;
        $a->fecha_inicio = $desde;
        $a->fecha_fin = $hasta;
        $a->validarPeriodo();
        $a->validarSinSolapamiento();
        $a->calcularDepreciacion();
        $a->save();

        return $a;
    }

    // ------------------------------------------------------------------
    // La formula
    // ------------------------------------------------------------------

    public function test_la_tasa_diaria_reparte_el_valor_en_la_vida_util(): void
    {
        // (120000 - 20000) / (120 meses * 30 dias) = 27.7778
        $equipo = $this->crearEquipo();

        $this->assertEqualsWithDelta(27.7778, $equipo->depreciacionDiaria(), 0.001);
    }

    public function test_la_depreciacion_de_un_periodo(): void
    {
        $equipo = $this->crearEquipo();

        $a = $this->asignar($equipo, $this->proceso, '2026-10-01 08:00:00', '2026-10-05 08:00:00');

        // 4 dias a 27.7778 = 111.11
        $this->assertEqualsWithDelta(111.11, (float) $a->depreciacion_total, 0.02);
    }

    public function test_un_equipo_sin_valor_residual_deprecia_todo(): void
    {
        $equipo = $this->crearEquipo(['valor_residual' => 0]);

        // 120000 / (120 * 30) = 33.3333, mas caro por dia que con residual
        $this->assertEqualsWithDelta(33.3333, $equipo->depreciacionDiaria(), 0.001);
    }

    public function test_un_equipo_sin_vida_util_no_deprecia(): void
    {
        // Sin vida util no se divide entre cero: deprecia cero
        $equipo = $this->crearEquipo(['vida_util_meses' => 0]);

        $this->assertEquals(0.0, $equipo->depreciacionDiaria());
    }

    public function test_un_equipo_sin_valor_no_deprecia(): void
    {
        $equipo = $this->crearEquipo([
            'valor_adquisicion' => 0,
            'valor_residual' => 0,
        ]);

        $this->assertEquals(0.0, $equipo->depreciacionDiaria());
    }

    public function test_la_depreciacion_no_pasa_del_valor_residual(): void
    {
        // Un equipo de 1000 con residual 900 solo tiene 100 por depreciar
        $equipo = $this->crearEquipo([
            'valor_adquisicion' => 1000,
            'valor_residual' => 900,
            'vida_util_meses' => 1,
        ]);

        // 100 / 30 = 3.33 por dia, pero 1000 dias serian 3333
        $this->assertEqualsWithDelta(3.3333, $equipo->depreciacionDiaria(), 0.001);
        $this->assertEquals(100.0, $equipo->depreciacionPorDias(1000));
    }

    public function test_un_periodo_sin_dias_no_deprecia(): void
    {
        $equipo = $this->crearEquipo();

        $this->assertEquals(0.0, $equipo->depreciacionPorDias(0));
        $this->assertEquals(0.0, $equipo->depreciacionPorDias(-5));
    }

    // ------------------------------------------------------------------
    // El periodo
    // ------------------------------------------------------------------

    public function test_la_fin_debe_ser_posterior_a_la_de_inicio(): void
    {
        $equipo = $this->crearEquipo();

        $this->expectException(ValidationException::class);

        $this->asignar($equipo, $this->proceso, '2026-10-05 08:00:00', '2026-10-01 08:00:00');
    }

    public function test_los_dias_de_uso_son_la_diferencia(): void
    {
        $equipo = $this->crearEquipo();

        $a = $this->asignar($equipo, $this->proceso, '2026-10-01 08:00:00', '2026-10-11 08:00:00');

        $this->assertEquals(10.0, $a->diasDeUso());
    }

    public function test_un_periodo_abierto_cuenta_hasta_hoy(): void
    {
        $equipo = $this->crearEquipo();

        $a = $this->asignar($equipo, $this->proceso, now()->subDays(3)->format('Y-m-d H:i:s'), null);

        $this->assertEqualsWithDelta(3.0, $a->diasDeUso(), 0.1);
    }

    // ------------------------------------------------------------------
    // Un equipo no puede estar en dos procesos a la vez
    // ------------------------------------------------------------------

    public function test_no_se_puede_solapar_con_otro_proceso(): void
    {
        $equipo = $this->crearEquipo();

        $this->asignar($equipo, $this->proceso, '2026-10-01 08:00:00', '2026-10-05 08:00:00');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/no puede trabajar en dos procesos/');

        // Del 3 al 7: se cruza con el anterior
        $this->asignar($equipo, $this->otroProceso, '2026-10-03 08:00:00', '2026-10-07 08:00:00');
    }

    public function test_no_se_puede_solapar_por_poco(): void
    {
        $equipo = $this->crearEquipo();

        $this->asignar($equipo, $this->proceso, '2026-10-01 08:00:00', '2026-10-05 08:00:00');

        // Empieza un minuto antes de que termine el otro
        $this->expectException(ValidationException::class);

        $this->asignar($equipo, $this->otroProceso, '2026-10-04 23:59:00', '2026-10-08 08:00:00');
    }

    public function test_periodos_seguidos_son_validos(): void
    {
        $equipo = $this->crearEquipo();

        // Justamente despues, no se solapa
        $a = $this->asignar($equipo, $this->proceso, '2026-10-01 08:00:00', '2026-10-05 08:00:00');
        $b = $this->asignar($equipo, $this->otroProceso, '2026-10-05 08:00:00', '2026-10-09 08:00:00');

        $this->assertGreaterThan(0, (float) $a->depreciacion_total);
        $this->assertGreaterThan(0, (float) $b->depreciacion_total);

        // 4 dias cada uno
        $this->assertEqualsWithDelta(111.11, (float) $a->depreciacion_total, 0.02);
        $this->assertEqualsWithDelta(111.11, (float) $b->depreciacion_total, 0.02);
    }

    public function test_un_periodo_abierto_bloquea_lo_que_venga_despues(): void
    {
        $equipo = $this->crearEquipo();

        // Sigue asignado, sin fecha de fin
        $this->asignar($equipo, $this->proceso, '2026-10-01 08:00:00', null);

        $this->expectException(ValidationException::class);

        $this->asignar($equipo, $this->otroProceso, '2026-11-01 08:00:00', '2026-11-05 08:00:00');
    }

    public function test_editar_no_se_choca_consigo_mismo(): void
    {
        $equipo = $this->crearEquipo();

        $a = $this->asignar($equipo, $this->proceso, '2026-10-01 08:00:00', '2026-10-05 08:00:00');

        // Cambiarle las fechas sin salir del periodo no debe fallar
        $a->fecha_inicio = '2026-10-01 09:00:00';
        $a->fecha_fin = '2026-10-05 09:00:00';
        $a->validarSinSolapamiento();

        $this->assertTrue(true, 'no deberia lanzar al editarse a si mismo');
    }

    public function test_otros_equipos_no_se_chocan_entre_si(): void
    {
        $a = $this->crearEquipo();
        $b = $this->crearEquipo();

        // Mismo tramo, equipos distintos: vale
        $this->asignar($a, $this->proceso, '2026-10-01 08:00:00', '2026-10-05 08:00:00');
        $this->asignar($b, $this->proceso, '2026-10-01 08:00:00', '2026-10-05 08:00:00');

        $this->assertSame(2, ProcesoEquipo::where('proceso_orden_id', $this->proceso->id)->count());
    }

    // ------------------------------------------------------------------
    // El costo del proceso
    // ------------------------------------------------------------------

    public function test_la_depreciacion_se_suma_al_costo_del_proceso(): void
    {
        $equipo = $this->crearEquipo();

        $this->asignar($equipo, $this->proceso, '2026-10-01 08:00:00', '2026-10-05 08:00:00');
        $this->asignar($equipo, $this->proceso, '2026-11-01 08:00:00', '2026-11-05 08:00:00');

        // 4 + 4 dias = 222.22
        $this->assertEqualsWithDelta(
            222.22,
            $this->proceso->fresh()->costo_equipos,
            0.02
        );

        // Y el otro proceso no arrastra el costo
        $this->assertEquals(0.0, $this->otroProceso->fresh()->costo_equipos);
    }

    public function test_el_costo_total_suma_mano_de_obra_y_equipos(): void
    {
        $equipo = $this->crearEquipo();

        $this->asignar($equipo, $this->proceso, '2026-10-01 08:00:00', '2026-10-05 08:00:00');

        $proceso = $this->proceso->fresh();

        $this->assertEqualsWithDelta(0.0, $proceso->costo_empleados, 0.01);
        $this->assertEqualsWithDelta(111.11, $proceso->costo_equipos, 0.02);
        $this->assertEqualsWithDelta(111.11, $proceso->costo_total, 0.02);
    }

    public function test_la_depreciacion_guardada_no_cambia_si_se_toca_el_equipo(): void
    {
        $equipo = $this->crearEquipo();

        $a = $this->asignar($equipo, $this->proceso, '2026-10-01 08:00:00', '2026-10-05 08:00:00');
        $guardado = (float) $a->depreciacion_total;
        $this->assertEqualsWithDelta(111.11, $guardado, 0.02);

        // Se duplica la vida util: la tasa nueva es la mitad, pero el
        // costo ya registrado no se toca
        $equipo->update(['vida_util_meses' => 240]);
        $equipo->refresh();

        $this->assertEqualsWithDelta(13.8889, $equipo->depreciacionDiaria(), 0.001);
        $this->assertEqualsWithDelta(
            $guardado,
            (float) $a->fresh()->depreciacion_total,
            0.001,
            'El costo de un proceso ya registrado no debe cambiar'
        );
    }

    public function test_al_borrar_una_asignacion_baja_el_costo_del_proceso(): void
    {
        $equipo = $this->crearEquipo();

        $a = $this->asignar($equipo, $this->proceso, '2026-10-01 08:00:00', '2026-10-05 08:00:00');
        $this->assertEqualsWithDelta(111.11, $this->proceso->fresh()->costo_equipos, 0.02);

        $a->delete();

        $this->assertEquals(0.0, $this->proceso->fresh()->costo_equipos);
    }
}

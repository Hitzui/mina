<?php

namespace Tests\Feature;

use App\Models\Cajas;
use App\Models\MetodosPagos;
use App\Models\Moneda;
use App\Models\MovimientoCaja;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MovimientoCajaModalTest extends TestCase
{
    use DatabaseTransactions;

    private Cajas $caja;

    private Cajas $otraCaja;

    private MetodosPagos $metodo;

    private Moneda $moneda;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('movimientos_caja.view', 'web');
        Permission::findOrCreate('movimientos_caja.create', 'web');

        $this->caja = $this->crearCaja('Caja asignada');
        $this->otraCaja = $this->crearCaja('Otra caja');
        $this->metodo = MetodosPagos::create([
            'nombre' => 'Método '.Str::upper(Str::random(7)),
            'estado' => true,
        ]);
        $this->moneda = Moneda::query()->where('estado', true)->firstOrFail();

        $usuario = User::create([
            'name' => 'Usuario movimientos caja',
            'email' => 'mov-caja-'.Str::lower(Str::random(10)).'@test.local',
            'password' => Hash::make('prueba-de-prueba'),
            'caja_id' => $this->caja->id,
        ]);
        $usuario->syncPermissions(['movimientos_caja.view', 'movimientos_caja.create']);
        $this->actingAs($usuario);
    }

    public function test_el_registro_se_abre_en_modal_y_solo_muestra_la_caja_asignada(): void
    {
        $this->get(route('procesos.movimientos_caja.index'))
            ->assertOk()
            ->assertSee('modalMovimientoCaja')
            ->assertSee('Registrar movimiento')
            ->assertSee('Traslado entre métodos')
            ->assertSee('Método de pago de destino')
            ->assertSee('Caja asignada a su usuario')
            ->assertSee($this->caja->nombre)
            ->assertDontSee('name="caja_id"', false)
            ->assertDontSee('name="caja_origen_id"', false);
    }

    public function test_el_servidor_usa_la_caja_asignada_aunque_se_envie_otro_id(): void
    {
        $this->post(route('procesos.movimientos_caja.store'), $this->datosMovimiento([
            'accion' => 'entrada',
            'caja_id' => $this->otraCaja->id,
            'monto' => '50.00',
            'descripcion' => 'Entrada de prueba',
        ]))->assertRedirect(route('procesos.movimientos_caja.index'));

        $movimiento = MovimientoCaja::query()->where('descripcion', 'Entrada de prueba')->firstOrFail();
        $this->assertSame((int) $this->caja->id, (int) $movimiento->caja_id);
    }

    public function test_rechaza_un_egreso_mayor_al_saldo_disponible(): void
    {
        $this->registrarSaldoInicial(100);

        $this->from(route('procesos.movimientos_caja.index'))
            ->post(route('procesos.movimientos_caja.store'), $this->datosMovimiento([
                'accion' => 'salida',
                'monto' => '100.01',
                'descripcion' => 'Retiro que excede saldo',
            ]))
            ->assertRedirect(route('procesos.movimientos_caja.index'))
            ->assertSessionHasErrors('monto');

        $this->assertDatabaseMissing('movimientos_caja', ['descripcion' => 'Retiro que excede saldo']);
        $this->assertSame(1, MovimientoCaja::query()->where('caja_id', $this->caja->id)->count());
    }

    public function test_permite_retirar_el_saldo_disponible_exacto(): void
    {
        $this->registrarSaldoInicial(100);

        $this->post(route('procesos.movimientos_caja.store'), $this->datosMovimiento([
            'accion' => 'salida',
            'monto' => '100.00',
            'descripcion' => 'Retiro del saldo completo',
        ]))->assertRedirect(route('procesos.movimientos_caja.index'));

        $this->assertDatabaseHas('movimientos_caja', [
            'caja_id' => $this->caja->id,
            'tipo' => MovimientoCaja::SALIDA,
            'monto' => '100.00',
            'descripcion' => 'Retiro del saldo completo',
        ]);
    }

    public function test_una_transferencia_no_puede_superar_el_saldo_de_la_caja_asignada(): void
    {
        $this->registrarSaldoInicial(100);

        $this->from(route('procesos.movimientos_caja.index'))
            ->post(route('procesos.movimientos_caja.store'), $this->datosMovimiento([
                'accion' => 'transferencia',
                'caja_destino_id' => $this->otraCaja->id,
                'monto' => '100.01',
            ]))
            ->assertRedirect(route('procesos.movimientos_caja.index'))
            ->assertSessionHasErrors('monto');

        $this->assertDatabaseMissing('movimientos_caja', [
            'caja_id' => $this->otraCaja->id,
            'origen' => MovimientoCaja::ORIGEN_TRANSFERENCIA,
        ]);
    }

    public function test_trasladar_dinero_entre_metodos_mueve_saldo_sin_cambiar_el_total_de_la_caja(): void
    {
        $this->registrarSaldoInicial(100);
        $metodoBancario = MetodosPagos::create([
            'nombre' => 'Banco '.Str::upper(Str::random(6)),
            'estado' => true,
        ]);

        $this->post(route('procesos.movimientos_caja.store'), $this->datosMovimiento([
            'accion' => 'traslado_metodo',
            'metodo_destino_id' => $metodoBancario->id,
            'monto' => '40.00',
        ]))->assertRedirect(route('procesos.movimientos_caja.index'));

        $traslados = MovimientoCaja::query()
            ->where('origen', MovimientoCaja::ORIGEN_TRANSFERENCIA)
            ->where('caja_id', $this->caja->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $traslados);
        $salida = $traslados->firstWhere('tipo', MovimientoCaja::SALIDA);
        $entrada = $traslados->firstWhere('tipo', MovimientoCaja::ENTRADA);
        $this->assertSame((int) $this->metodo->id, (int) $salida->metodo_pago_id);
        $this->assertSame((int) $metodoBancario->id, (int) $entrada->metodo_pago_id);
        $this->assertSame((int) $entrada->id, (int) $salida->transferencia_id);
        $this->assertSame((int) $salida->id, (int) $entrada->transferencia_id);
        $this->assertSame('60.00', $this->saldoDe($this->metodo->id));
        $this->assertSame('40.00', $this->saldoDe($metodoBancario->id));
    }

    public function test_traslado_entre_metodos_no_puede_superar_el_saldo_del_metodo_de_origen(): void
    {
        $this->registrarSaldoInicial(100);
        $metodoBancario = MetodosPagos::create([
            'nombre' => 'Banco '.Str::upper(Str::random(6)),
            'estado' => true,
        ]);

        $this->from(route('procesos.movimientos_caja.index'))
            ->post(route('procesos.movimientos_caja.store'), $this->datosMovimiento([
                'accion' => 'traslado_metodo',
                'metodo_destino_id' => $metodoBancario->id,
                'monto' => '100.01',
            ]))
            ->assertRedirect(route('procesos.movimientos_caja.index'))
            ->assertSessionHasErrors('monto');

        $this->assertSame(1, MovimientoCaja::query()->where('caja_id', $this->caja->id)->count());
    }

    private function crearCaja(string $sufijo): Cajas
    {
        return Cajas::create([
            'nombre' => $sufijo.' '.Str::upper(Str::random(6)),
            'descripcion' => 'Caja para prueba de egresos',
            'estado' => true,
        ]);
    }

    private function registrarSaldoInicial(float $monto): void
    {
        $this->post(route('procesos.movimientos_caja.store'), $this->datosMovimiento([
            'accion' => 'saldo_inicial',
            'monto' => number_format($monto, 2, '.', ''),
            'descripcion' => 'Saldo inicial de prueba',
        ]))->assertRedirect(route('procesos.movimientos_caja.index'));
    }

    private function datosMovimiento(array $extra = []): array
    {
        return array_merge([
            'accion' => 'entrada',
            'fecha' => today()->format('Y-m-d'),
            'metodo_pago_id' => $this->metodo->id,
            'moneda_id' => $this->moneda->id,
            'monto' => '25.00',
            'descripcion' => 'Movimiento de prueba',
        ], $extra);
    }

    private function saldoDe(int $metodoId): string
    {
        return (string) MovimientoCaja::query()
            ->where('caja_id', $this->caja->id)
            ->where('metodo_pago_id', $metodoId)
            ->where('moneda_id', $this->moneda->id)
            ->selectRaw(
                'COALESCE(ROUND(SUM(CASE WHEN tipo = ? THEN monto ELSE -monto END), 2), 0) AS saldo',
                [MovimientoCaja::ENTRADA]
            )
            ->value('saldo');
    }
}

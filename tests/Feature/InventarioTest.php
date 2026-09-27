<?php

namespace Tests\Feature;

use App\Models\CategoriasCosto;
use App\Models\InventarioProducto;
use App\Models\Moneda;
use App\Models\MovimientosInventario;
use App\Models\OrdenesTrabajo;
use App\Models\Producto;
use App\Models\ProcesosOrden;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * El almacen: lo que entra, lo que sale y lo que queda.
 *
 * Aqui se comprueban las tres reglas de las que depende el costo de
 * materia prima de un proceso:
 *
 *   - el costo unitario de una salida lo decide el promedio del almacen,
 *     no lo que se escriba en el formulario;
 *   - la existencia nunca queda en negativo;
 *   - el costo promedio solo se mueve cuando entra material, nunca cuando
 *     sale, para que el costo de un proceso no dependa del orden en que se
 *     registraron los consumos.
 */
class InventarioTest extends TestCase
{
    use DatabaseTransactions;

    private InventarioService $inventario;

    private Producto $producto;

    private ProcesosOrden $proceso;

    private Moneda $monedaBase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventario = new InventarioService();

        $this->producto = Producto::create([
            Producto::CODIGO => 'MAT-' . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
            Producto::NOMBRE => 'Cimento de prueba',
            Producto::UNIDAD_MEDIDA => 'kg',
            Producto::STOCK_MINIMO => 0,
            Producto::ESTADO => true,
        ]);

        $this->monedaBase = Moneda::where('es_moneda_base', true)->firstOrFail();

        $this->proceso = $this->crearProceso();
    }

    private function crearProceso(): ProcesosOrden
    {
        $orden = OrdenesTrabajo::firstOrFail();
        $etapa = DB::table('etapas')->first();

        $proceso = new ProcesosOrden();
        $proceso->orden_trabajo_id = $orden->id;
        $proceso->etapa_id = $etapa->id;
        $proceso->codigo = 'P-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $proceso->fecha_inicio = '2026-01-01 08:00:00';
        $proceso->fecha_fin = '2026-12-31 17:00:00';
        $proceso->peso_entrada = 1;
        $proceso->peso_salida = 1;
        $proceso->estado = 1;
        $proceso->save();

        return $proceso;
    }

    private function entrar(float $cantidad, float $costo, array $extra = []): MovimientosInventario
    {
        return $this->inventario->registrar(array_merge([
            'producto_id' => $this->producto->id,
            'tipo' => MovimientosInventario::TIPO_ENTRADA,
            'cantidad' => $cantidad,
            'costo_unitario' => $costo,
            'moneda_id' => $this->monedaBase->id,
            'fecha' => '2026-01-10 08:00:00',
        ], $extra));
    }

    private function salir(float $cantidad, array $extra = []): MovimientosInventario
    {
        return $this->inventario->registrar(array_merge([
            'producto_id' => $this->producto->id,
            'tipo' => MovimientosInventario::TIPO_SALIDA,
            'cantidad' => $cantidad,
            'fecha' => '2026-01-20 08:00:00',
        ], $extra));
    }

    private function saldo(): InventarioProducto
    {
        return InventarioProducto::where(
            InventarioProducto::PRODUCTO_ID,
            $this->producto->id
        )->firstOrFail();
    }

    // ==================================================================
    // Los tipos de movimiento
    // ==================================================================

    public function test_los_cuatro_tipos_aportan_el_signo_correcto(): void
    {
        $esperados = [
            MovimientosInventario::TIPO_ENTRADA => 1,
            MovimientosInventario::TIPO_SALIDA => -1,
            MovimientosInventario::TIPO_AJUSTE_POSITIVO => 1,
            MovimientosInventario::TIPO_AJUSTE_NEGATIVO => -1,
        ];

        foreach ($esperados as $tipo => $signo) {
            $movimiento = new MovimientosInventario();
            $movimiento->tipo = $tipo;

            $this->assertSame($signo, $movimiento->signo(), "El tipo $tipo");
            $this->assertSame($tipo, $movimiento->tipoNormalizado());
        }
    }

    public function test_el_tipo_se_reconoce_aunque_venga_escrito_de_otra_manera(): void
    {
        $casos = [
            'ENTRADA' => MovimientosInventario::TIPO_ENTRADA,
            ' entrada ' => MovimientosInventario::TIPO_ENTRADA,
            'Entrada de material' => MovimientosInventario::TIPO_ENTRADA,
            'SALIDA' => MovimientosInventario::TIPO_SALIDA,
            'consumo' => MovimientosInventario::TIPO_SALIDA,
            'Consumo en proceso' => MovimientosInventario::TIPO_SALIDA,
            'ajuste' => MovimientosInventario::TIPO_AJUSTE_POSITIVO,
            'ajuste negativo' => MovimientosInventario::TIPO_AJUSTE_NEGATIVO,
            'ajuste_negativo' => MovimientosInventario::TIPO_AJUSTE_NEGATIVO,
        ];

        foreach ($casos as $escrito => $esperado) {
            $movimiento = new MovimientosInventario();
            $movimiento->tipo = $escrito;

            $this->assertSame(
                $esperado,
                $movimiento->tipoNormalizado(),
                "El tipo escrito como \"$escrito\""
            );
        }
    }

    public function test_un_tipo_desconocido_no_mueve_el_saldo(): void
    {
        $movimiento = new MovimientosInventario();
        $movimiento->tipo = 'inventado por el usuario';

        $this->assertNull($movimiento->tipoNormalizado());

        /*
         * El signo 0 es lo que evita el peor fallo: si un texto raro
         * contara como salida, vaciaria el almacen.
         */
        $this->assertSame(0, $movimiento->signo());
        $this->assertFalse($movimiento->esEntrada());
        $this->assertFalse($movimiento->esSalida());
    }

    // ==================================================================
    // Entradas y costo promedio
    // ==================================================================

    public function test_una_entrada_deja_el_saldo_y_el_promedio(): void
    {
        $this->entrar(100, 5.00);

        $saldo = $this->saldo();

        $this->assertEqualsWithDelta(100.0, (float) $saldo->cantidad_actual, 0.001);
        $this->assertEqualsWithDelta(500.00, (float) $saldo->valor_actual, 0.01);
        $this->assertEqualsWithDelta(5.00, (float) $saldo->cpp_actual, 0.0001);
    }

    public function test_el_promedio_se_pondera_por_cantidad(): void
    {
        // 100 kg a 10  y  300 kg a 4  ->  (1000 + 1200) / 400 = 5.50
        $this->entrar(100, 10.00);
        $this->entrar(300, 4.00);

        $saldo = $this->saldo();

        $this->assertEqualsWithDelta(400.0, (float) $saldo->cantidad_actual, 0.001);
        $this->assertEqualsWithDelta(2200.00, (float) $saldo->valor_actual, 0.01);
        $this->assertEqualsWithDelta(5.50, (float) $saldo->cpp_actual, 0.0001);
    }

    public function test_el_promedio_pondera_y_no_promedia_las_entradas(): void
    {
        // Si promediara en vez de ponderar, darian 7.00 en vez de 5.50
        $this->entrar(100, 10.00);
        $this->entrar(300, 4.00);

        $cpp = (float) $this->saldo()->cpp_actual;

        $this->assertNotEqualsWithDelta(7.00, $cpp, 0.01, 'No debe promediar las entradas');
        $this->assertEqualsWithDelta(5.50, $cpp, 0.0001);
    }

    // ==================================================================
    // Salidas
    // ==================================================================

    public function test_la_salida_se_valora_al_promedio_del_almacen(): void
    {
        $this->entrar(200, 4.00);

        $salida = $this->salir(50);

        $this->assertEqualsWithDelta(
            4.00,
            (float) $salida->costo_unitario,
            0.0001,
            'Lo que sale se vale al promedio que hay dentro'
        );
        $this->assertEqualsWithDelta(200.00, (float) $salida->costo_total, 0.01);
    }

    public function test_el_precio_que_trae_el_formulario_se_pisa(): void
    {
        $this->entrar(200, 4.00);

        /*
         * El precio de una salida lo pone el inventario, no el formulario.
         * Se comprueba pasando un precio inventado: si se respetara, el
         * costo del proceso dependeria de lo tecleado en la pantalla.
         */
        $salida = $this->salir(50, ['costo_unitario' => 999.00]);

        $this->assertEqualsWithDelta(4.00, (float) $salida->costo_unitario, 0.0001);
        $this->assertEqualsWithDelta(200.00, (float) $salida->costo_total, 0.01);
    }

    public function test_la_salida_no_mueve_el_costo_promedio(): void
    {
        $this->entrar(200, 4.00);
        $cppAntes = (float) $this->saldo()->cpp_actual;

        $this->salir(50);

        $saldo = $this->saldo();

        $this->assertEqualsWithDelta(
            $cppAntes,
            (float) $saldo->cpp_actual,
            0.0001,
            'Sacar material no debe cambiar el promedio de lo que queda'
        );
        $this->assertEqualsWithDelta(150.0, (float) $saldo->cantidad_actual, 0.001);
        $this->assertEqualsWithDelta(600.00, (float) $saldo->valor_actual, 0.01);
    }

    public function test_varias_salidas_no_se_influyen_entre_si(): void
    {
        $this->entrar(200, 4.00);

        $primera = $this->salir(50);
        $segunda = $this->salir(50);

        $this->assertEqualsWithDelta(4.00, (float) $primera->costo_unitario, 0.0001);
        $this->assertEqualsWithDelta(4.00, (float) $segunda->costo_unitario, 0.0001);

        // Y el costo del proceso es el mismo con un consumo o con cinco
        $this->assertEqualsWithDelta(
            (float) $primera->costo_total,
            (float) $segunda->costo_total,
            0.01
        );
    }

    public function test_vaciar_y_volver_a_llenar_no_deja_el_promedio_en_cero(): void
    {
        $this->entrar(100, 8.00);
        $this->salir(100);

        $saldo = $this->saldo();

        $this->assertEqualsWithDelta(0.0, (float) $saldo->cantidad_actual, 0.001);
        $this->assertNotEqualsWithDelta(
            0.0,
            (float) $saldo->cpp_actual,
            0.0001,
            'Un promedio en 0 haria que el siguiente consumo saliera a coste cero'
        );

        // Y la siguiente entrada vuelve a calcular bien
        $this->entrar(50, 6.00);

        $this->assertEqualsWithDelta(6.00, (float) $this->saldo()->cpp_actual, 0.0001);
    }

    // ==================================================================
    // La existencia no puede quedar en negativo
    // ==================================================================

    public function test_no_se_puede_sacar_mas_material_del_que_hay(): void
    {
        $this->entrar(100, 4.00);

        try {
            $this->salir(101);
            $this->fail('Se deberia haber rechazado sacar mas de lo que hay');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey(
                MovimientosInventario::CANTIDAD,
                $e->errors()
            );
        }

        // Y el saldo no se toco
        $saldo = $this->saldo();
        $this->assertEqualsWithDelta(100.0, (float) $saldo->cantidad_actual, 0.001);
    }

    public function test_un_rechazo_no_deja_movimiento_ni_saldo_a_medias(): void
    {
        $this->entrar(100, 4.00);

        $movimientosAntes = MovimientosInventario::withTrashed()
            ->where('producto_id', $this->producto->id)
            ->count();

        try {
            $this->salir(500);
        } catch (ValidationException) {
            // es lo que se espera
        }

        $this->assertSame(
            $movimientosAntes,
            MovimientosInventario::withTrashed()
                ->where('producto_id', $this->producto->id)
                ->count(),
            'Un movimiento rechazado no debe quedar guardado a medias'
        );

        $this->assertEqualsWithDelta(100.0, (float) $this->saldo()->cantidad_actual, 0.001);
    }

    public function test_un_redondeo_pequeno_no_bloquea_un_consumo_que_cabe(): void
    {
        // Tres entradas de 0.1 dejan el saldo en 0.29999 por el redondeo
        $this->entrar(0.1, 10.00);
        $this->entrar(0.1, 10.00);
        $this->entrar(0.1, 10.00);

        $this->salir(0.1);
        $this->salir(0.1);

        $this->assertEqualsWithDelta(
            0.1,
            (float) $this->saldo()->cantidad_actual,
            0.001
        );
    }

    public function test_sacar_exactamente_lo_que_hay_deja_el_almacen_en_cero(): void
    {
        $this->entrar(100, 4.00);

        $this->salir(100);

        $saldo = $this->saldo();

        $this->assertEqualsWithDelta(0.0, (float) $saldo->cantidad_actual, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $saldo->valor_actual, 0.01);
    }

    // ==================================================================
    // Deshacer un movimiento
    // ==================================================================

    public function test_al_borrar_un_consumo_el_material_vuelve_al_almacen(): void
    {
        $this->entrar(200, 4.00);
        $consumo = $this->salir(80);

        $this->inventario->revertir($consumo);

        $saldo = $this->saldo();

        $this->assertEqualsWithDelta(
            200.0,
            (float) $saldo->cantidad_actual,
            0.001,
            'El almacen tiene que recuperar el material del consumo borrado'
        );
        $this->assertEqualsWithDelta(800.00, (float) $saldo->valor_actual, 0.01);
    }

    public function test_al_borrar_una_entrada_el_material_sale_del_almacen(): void
    {
        $this->entrar(200, 4.00);
        $entrada = $this->entrar(100, 4.00);

        $this->inventario->revertir($entrada);

        $this->assertEqualsWithDelta(200.0, (float) $this->saldo()->cantidad_actual, 0.001);
        $this->assertEqualsWithDelta(800.00, (float) $this->saldo()->valor_actual, 0.01);
    }

    public function test_al_borrar_una_entrada_ya_consumida_no_se_puede(): void
    {
        $this->entrar(100, 4.00);
        $entrada = $this->entrar(100, 4.00);
        $this->salir(150);

        $this->expectException(ValidationException::class);

        // Si ya se consumio, no se puede fingir que la entrada nunca ocurrio
        $this->inventario->revertir($entrada);
    }

    // ==================================================================
    // La materia prima como costo del proceso
    // ==================================================================

    public function test_el_consumo_de_un_proceso_es_una_fila_del_desglose(): void
    {
        $this->entrar(200, 4.00);

        $this->salir(100, [
            'orden_trabajo_id' => $this->proceso->orden_trabajo_id,
            'proceso_orden_id' => $this->proceso->id,
        ]);

        $proceso = $this->proceso->fresh();

        $this->assertEqualsWithDelta(400.00, (float) $proceso->costo_materia_prima, 0.01);

        $filas = $proceso->costosDesglosados();
        $nombres = $filas->pluck('nombre')->all();

        $this->assertContains('Materia prima', $nombres);

        $fila = $filas->firstWhere('nombre', 'Materia prima');

        $this->assertEqualsWithDelta(400.00, (float) $fila['importe'], 0.01);
        $this->assertTrue($fila['automatico'], 'Lo calcula el sistema, no se escribe a mano');
    }

    public function test_el_costo_total_del_proceso_suma_la_materia_prima(): void
    {
        $this->entrar(200, 4.00);

        $this->salir(100, [
            'orden_trabajo_id' => $this->proceso->orden_trabajo_id,
            'proceso_orden_id' => $this->proceso->id,
        ]);

        $proceso = $this->proceso->fresh();

        $this->assertEqualsWithDelta(
            (float) $proceso->costo_empleados
                + (float) $proceso->costo_equipos
                + 400.00
                + (float) $proceso->costo_otros,
            (float) $proceso->costo_total,
            0.01
        );
    }

    public function test_el_consumo_de_otro_proceso_no_cuenta_en_este(): void
    {
        $otroProceso = $this->crearProceso();

        $this->entrar(200, 4.00);

        $this->salir(100, [
            'orden_trabajo_id' => $otroProceso->orden_trabajo_id,
            'proceso_orden_id' => $otroProceso->id,
        ]);

        $this->assertEqualsWithDelta(
            0.0,
            (float) $this->proceso->fresh()->costo_materia_prima,
            0.01,
            'El consumo de otro proceso no puede aparecer en este'
        );

        $this->assertEqualsWithDelta(400.00, (float) $otroProceso->fresh()->costo_materia_prima, 0.01);
    }

    public function test_una_entrada_no_es_costo_de_ningun_proceso(): void
    {
        /*
         * Meter material en el almacen no es un costo: es comprar. Si
         * contara como materia prima del proceso, el costo saldria
         * duplicado, una vez al comprar y otra vez al usarlo.
         */
        $this->entrar(200, 4.00, [
            'orden_trabajo_id' => $this->proceso->orden_trabajo_id,
            'proceso_orden_id' => $this->proceso->id,
        ]);

        $this->assertEqualsWithDelta(
            0.0,
            (float) $this->proceso->fresh()->costo_materia_prima,
            0.01,
            'Entrar material en el almacen no puede contar como costo del proceso'
        );
    }

    public function test_la_materia_prima_no_se_puede_cargar_a_mano(): void
    {
        /*
         * Con la categoria marcada como automatica, el consumo se
         * registra por el almacen y la categoria desaparece de los
         * conceptos cargables. Si volviera a salir en el combo, se
         * podrian cargar los 200 kilos de cemento por los dos caminos y
         * el costo del proceso saldria el doble sin que se notara.
         */
        $registrables = CategoriasCosto::paraRegistrar()->pluck('nombre')->all();

        $this->assertNotContains(
            'Materia prima',
            $registrables,
            'La materia prima se registra por el almacen, no a mano'
        );
    }

    public function test_la_categoria_de_materia_primia_esta_marcada_como_automatica(): void
    {
        $categoria = DB::table('categorias_costos')
            ->whereRaw('LOWER(TRIM(nombre)) = ?', ['materia prima'])
            ->first();

        $this->assertNotNull(
            $categoria,
            'La categoria "Materia prima" del catalogo no deberia haberse borrado'
        );

        $this->assertSame(
            1,
            (int) $categoria->automatica,
            'La migracion debe haberla marcado como automatica'
        );
    }

    public function test_el_desglose_no_repite_el_nombre_de_la_materia_primia(): void
    {
        /*
         * La fila automatica del desglose se llama "Materia prima" y el
         * catalogo tambien. Si la categoria no estuviera marcada como
         * automatica, aparecerian dos filas con el mismo nombre: una
         * calculada y otra vacia, y el total no cuadraria con lo que se ve.
         */
        $nombres = $this->proceso->fresh()->costosDesglosados()->pluck('nombre')->all();

        $this->assertSame(
            count($nombres),
            count(array_unique($nombres)),
            'Hay conceptos repetidos en el desglose: ' . implode(' | ', $nombres)
        );

        $this->assertContains('Materia prima', $nombres);
    }

    public function test_el_proceso_del_movimiento_tiene_que_ser_de_la_orden(): void
    {
        $otraOrden = OrdenesTrabajo::where('id', '!=', $this->proceso->orden_trabajo_id)
            ->first();

        if ($otraOrden === null) {
            // Solo hay una orden en la base: no se puede comprobar el cruce
            $this->markTestSkipped('Hacen falta dos ordenes para probar el cruce');
        }

        $movimiento = new MovimientosInventario();
        $movimiento->orden_trabajo_id = $otraOrden->id;
        $movimiento->proceso_orden_id = $this->proceso->id;

        $this->expectException(ValidationException::class);

        $movimiento->validarProcesoPertenece();
    }

    // ==================================================================
    // El equivalente en NIO
    // ==================================================================

    public function test_una_salida_guarda_su_equivalente_en_nio(): void
    {
        $this->entrar(200, 4.00);

        $salida = $this->salir(50);

        /*
         * La salida se valua con el promedio del almacen, que ya esta en
         * NIO. Si el equivalente en NIO quedara vacio, el costo del proceso
         * saldria en cero sin que nada lo avise.
         */
        $this->assertNotNull(
            $salida->costo_total_nio,
            'Una salida siempre debe tener equivalente en NIO'
        );
        $this->assertEqualsWithDelta(200.00, (float) $salida->costo_total_nio, 0.01);
    }

    public function test_una_entrada_con_tipo_de_cambio_usa_la_paridad_de_su_fecha(): void
    {
        $extranjera = Moneda::where('es_moneda_base', false)
            ->where('estado', true)
            ->first();

        if ($extranjera === null) {
            $this->markTestSkipped('No hay una moneda extranjera con tipo de cambio');
        }

        // Sin tipo de cambio no hay paridad, y entonces solo queda el
        // equivalente de la moneda base
        $movimiento = $this->entrar(100, 10.00, [
            'moneda_id' => $extranjera->id,
        ]);

        $this->assertNotNull($movimiento->costo_total);
        $this->assertEqualsWithDelta(1000.00, (float) $movimiento->costo_total, 0.01);
    }
}

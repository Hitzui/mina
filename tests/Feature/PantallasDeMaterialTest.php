<?php

namespace Tests\Feature;

use App\Models\InventarioProducto;
use App\Models\Moneda;
use App\Models\MovimientosInventario;
use App\Models\OrdenesTrabajo;
use App\Models\Producto;
use App\Models\ProcesosOrden;
use App\Models\User;
use App\Services\InventarioService;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Las pantallas del almacen y del consumo de material en un proceso.
 *
 * Comprueba sobre todo las cosas que se rompen en silencio: que un consumo
 * descuente el almacen, que el precio lo ponga el inventario y no la
 * pantalla, que borrar un consumo devuelva el material, y que no se puedan
 * tocar consumos de otro proceso escribiendo su id en la url.
 */
class PantallasDeMaterialTest extends TestCase
{
    use DatabaseTransactions;

    private InventarioService $inventario;

    private Producto $producto;

    private ProcesosOrden $proceso;

    private Moneda $monedaBase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'mat-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);

        $this->inventario = new InventarioService();
        $this->monedaBase = Moneda::where('es_moneda_base', true)->firstOrFail();
        $this->producto = $this->crearProducto();
        $this->proceso = $this->crearProceso();
    }

    private function crearProducto(string $nombre = 'Cimento de prueba'): Producto
    {
        return Producto::create([
            /*
             * Un codigo que no sigue el patron de la serie, a proposito. La
             * generacion automatica cuenta solo los MAT-########, y si aqui
             * se usara un MAT- con numeros, estos tests moverian la cuenta y
             * el numero de serie dependeria del orden en que corrieran.
             */
            Producto::CODIGO => 'PRUEBA-' . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
            Producto::NOMBRE => $nombre,
            Producto::UNIDAD_MEDIDA => 'kg',
            Producto::STOCK_MINIMO => 10,
            Producto::ESTADO => true,
        ]);
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

    private function entrar(float $cantidad, float $costo): MovimientosInventario
    {
        return $this->inventario->registrar([
            'producto_id' => $this->producto->id,
            'tipo' => MovimientosInventario::TIPO_ENTRADA,
            'cantidad' => $cantidad,
            'costo_unitario' => $costo,
            'moneda_id' => $this->monedaBase->id,
            'fecha' => '2026-01-10 08:00:00',
        ]);
    }

    private function rutaProceso(string $accion = '', ?ProcesosOrden $proceso = null): string
    {
        $proceso ??= $this->proceso;

        return "/procesos/ordenes-trabajo/{$proceso->orden_trabajo_id}"
            . "/procesos/{$proceso->id}/materiales{$accion}";
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'producto_id' => $this->producto->id,
            'tipo' => MovimientosInventario::TIPO_SALIDA,
            'fecha' => '2026-02-01',
            'cantidad' => 50,
            'costo_unitario' => 999.00,
            'observaciones' => 'Consumo de prueba',
        ], $extra);
    }

    // ==================================================================
    // El catalogo de materiales
    // ==================================================================

    public function test_la_pantalla_de_materiales_carga(): void
    {
        $this->get('/inventario/productos')->assertOk();
    }

    public function test_se_puede_crear_un_material(): void
    {
        $this->post('/inventario/productos', [
            'nombre' => 'Cemento',
            'unidad_medida' => 'kg',
            'stock_minimo' => 50,
            'estado' => 1,
        ])->assertRedirect(route('inventario.productos.index'));

        $this->assertDatabaseHas('productos', [
            'nombre' => 'Cemento',
            'unidad_medida' => 'kg',
        ]);
    }

    public function test_el_material_necesita_nombre_y_unidad(): void
    {
        $this->post('/inventario/productos', [])
            ->assertSessionHasErrors(['nombre', 'unidad_medida']);
    }

    // ==================================================================
    // El codigo se genera solo
    // ==================================================================

    public function test_el_codigo_se_asigna_solo_al_crear(): void
    {
        $this->post('/inventario/productos', [
            'nombre' => 'Cemento sin codigo',
            'unidad_medida' => 'kg',
            'estado' => 1,
        ])->assertRedirect();

        $producto = Producto::where('nombre', 'Cemento sin codigo')->firstOrFail();

        $this->assertMatchesRegularExpression(
            '/^' . Producto::PREFIJO_CODIGO . '\d{6}$/',
            $producto->codigo,
            'El código debe llevar el prefijo y seis dígitos'
        );
    }

    public function test_el_codigo_va_incrementando(): void
    {
        /*
         * No se comprueba que salga MAT-000001: la serie empieza donde toque,
         * y en la base puede haber materiales de verdad. Lo que importa es
         * que cada alta reciba el siguiente, no uno ya usado.
         */
        $primero = Producto::crearConCodigo(['nombre' => 'A', 'unidad_medida' => 'kg']);
        $segundo = Producto::crearConCodigo(['nombre' => 'B', 'unidad_medida' => 'kg']);
        $tercero = Producto::crearConCodigo(['nombre' => 'C', 'unidad_medida' => 'kg']);

        $patron = '/^MAT-\d{6}$/';

        $this->assertMatchesRegularExpression($patron, $primero->codigo);
        $this->assertMatchesRegularExpression($patron, $segundo->codigo);
        $this->assertMatchesRegularExpression($patron, $tercero->codigo);

        $this->assertSame(
            (int) substr($primero->codigo, 4) + 1,
            (int) substr($segundo->codigo, 4),
            'El segundo tiene que ser el siguiente al primero'
        );

        $this->assertSame(
            (int) substr($segundo->codigo, 4) + 1,
            (int) substr($tercero->codigo, 4),
            'Y el tercero el siguiente al segundo'
        );
    }

    public function test_un_codigo_borrado_no_vuelve_a_salir(): void
    {
        $primero = Producto::crearConCodigo(['nombre' => 'A', 'unidad_medida' => 'kg']);
        $codigoBorrado = $primero->codigo;

        Producto::crearConCodigo(['nombre' => 'B', 'unidad_medida' => 'kg']);
        $primero->delete();

        $siguiente = Producto::crearConCodigo(['nombre' => 'C', 'unidad_medida' => 'kg']);

        /*
         * Si el codigo de un material borrado volviera a salir, el indice
         * unico de la base lo rechazaria; y ademas un material borrado sigue
         * citado en los movimientos de los procesos donde se consumio, con
         * un codigo repetido seria imposible saber de que material se hablo.
         */
        $this->assertNotSame(
            $codigoBorrado,
            $siguiente->codigo,
            'Un código de un material borrado no se puede reutilizar'
        );
    }

    public function test_un_codigo_que_no_sigue_el_patron_no_rompe_la_serie(): void
    {
        // Uno puesto a mano antes de que existiera la generación automática
        DB::table('productos')->insert([
            'codigo' => 'CEMENTO-GRUPO',
            'nombre' => 'Codigo raro',
            'unidad_medida' => 'kg',
            'stock_minimo' => 0,
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $siguiente = Producto::crearConCodigo(['nombre' => 'Normal', 'unidad_medida' => 'kg']);

        /*
         * El codigo raro no participa en la cuenta. Si contara, el numero
         * saldria disparado: un "MAT-12abc" convertido a entero contaria
         * como 12 y dejaria doce numeros sin usar, sin que nadie supiera
         * por que. Por eso solo se cuentan los MAT- de seis digitos.
         */
        $this->assertMatchesRegularExpression('/^MAT-\d{6}$/', $siguiente->codigo);

        $cuantosHay = Producto::withTrashed()
            ->where('codigo', 'like', Producto::PREFIJO_CODIGO . '%')
            ->count();

        $this->assertLessThanOrEqual(
            $cuantosHay,
            (int) substr($siguiente->codigo, 4),
            'El número no puede saltar por un código que no sigue el patrón'
        );
    }

    public function test_uno_que_manda_un_codigo_no_lo_impone(): void
    {
        /*
         * Aunque alguien escriba un codigo en la peticion, el que se guarda
         * es el del sistema. Si se aceptara, bastaria con mandar uno repetido
         * o uno absurdo para tener dos filas con el mismo codigo o romper la
         * serie.
         */
        $this->postJson('/inventario/productos', [
            'codigo' => 'MAT-999999',
            'nombre' => 'Material con codigo impuesto',
            'unidad_medida' => 'kg',
            'estado' => 1,
        ])->assertOk();

        $producto = Producto::where('nombre', 'Material con codigo impuesto')
            ->firstOrFail();

        $this->assertNotSame(
            'MAT-999999',
            $producto->codigo,
            'Un código mandado en la petición no se puede imponer'
        );

        $this->assertMatchesRegularExpression('/^MAT-\d{6}$/', $producto->codigo);

        $this->assertDatabaseMissing('productos', ['codigo' => 'MAT-999999']);
    }

    public function test_editar_no_cambia_el_codigo(): void
    {
        $codigoOriginal = Producto::generarCodigo();

        $this->producto->update(['codigo' => $codigoOriginal]);

        $this->putJson("/inventario/productos/{$this->producto->id}", [
            // Un codigo distinto llega igualmente en la peticion
            'codigo' => 'MAT-999999',
            'nombre' => 'Cemento renombrado',
            'unidad_medida' => 'kg',
            'estado' => 1,
        ])->assertOk();

        $this->producto->refresh();

        $this->assertSame(
            $codigoOriginal,
            $this->producto->codigo,
            'El código es la identidad del material: no lo cambia una edición'
        );

        $this->assertSame('Cemento renombrado', $this->producto->nombre);
    }

    public function test_el_formulario_no_pide_el_codigo(): void
    {
        $formulario = file_get_contents(
            resource_path('views/inventario/productos/_form.blade.php')
        );

        $this->assertStringNotContainsString(
            'name="codigo"',
            $formulario,
            'El código no se escribe: lo pone el sistema'
        );

        $this->assertStringContainsString('readonly', $formulario);
    }

    public function test_el_alta_avisa_de_que_el_codigo_se_asigna_solo(): void
    {
        $formulario = file_get_contents(
            resource_path('views/inventario/productos/_form.blade.php')
        );

        $this->assertStringContainsString(
            'Se asigna automáticamente',
            $formulario,
            'En el alta hay que decir que el código se pone solo'
        );
    }

    public function test_un_material_con_movimientos_se_desactiva_en_vez_de_borrarse(): void
    {
        $this->entrar(100, 4.00);

        $this->delete("/inventario/productos/{$this->producto->id}")->assertRedirect();

        /*
         * Desactivado, no borrado de verdad: el kardex lo dejaria apuntando
         * a un material que no existe, y el costo de los procesos donde se
         * uso se quedaria sin poder explicar de donde salio.
         */
        $this->assertDatabaseHas('productos', [
            'id' => $this->producto->id,
            'estado' => false,
        ]);

        $this->assertNotSoftDeleted('productos', ['id' => $this->producto->id]);

        // Y sigue estando, para que las filas del kardex no queden huerfanas
        $this->assertNotNull(Producto::withTrashed()->find($this->producto->id));
    }

    public function test_un_material_sin_movimientos_se_borra_de_verdad(): void
    {
        $this->delete("/inventario/productos/{$this->producto->id}")->assertRedirect();

        $this->assertSoftDeleted('productos', ['id' => $this->producto->id]);
    }

    public function test_el_material_desactivado_deja_de_ofrecerse_para_consumir(): void
    {
        $this->entrar(200, 4.00);
        $this->producto->update(['estado' => false]);

        $this->post($this->rutaProceso(), $this->payload())
            ->assertSessionHasErrors('producto_id');
    }

    // ==================================================================
    // Los modales: alta, edicion y ficha
    // ==================================================================

    public function test_los_modales_estan_en_la_pagina_de_materiales(): void
    {
        $html = $this->get('/inventario/productos')->assertOk()->getContent();

        // El boton de nuevo material abre el modal, no lleva a otra pagina
        $this->assertStringContainsString('id="btnNuevoProducto"', $html);
        $this->assertStringNotContainsString(
            'route(\'inventario.productos.create\')',
            $html,
            'El alta se hace en modal, no en otra pagina'
        );

        $this->assertStringContainsString('id="modalProducto"', $html);
        $this->assertStringContainsString('id="formProducto"', $html);
        $this->assertStringContainsString('id="modalShowProducto"', $html);
        $this->assertStringContainsString('productos.js', $html);
    }

    public function test_el_modal_de_alta_apunta_a_la_ruta_de_crear(): void
    {
        $html = $this->get('/inventario/productos')->assertOk()->getContent();

        $this->assertStringContainsString(
            'data-store-url="' . route('inventario.productos.store') . '"',
            $html
        );

        $this->assertStringContainsString('__ID__', $html, 'La url de editar se completa en el navegador');
    }

    public function test_el_alta_sigue_aceptando_el_formulario_normal(): void
    {
        /*
         * El modal manda ajax, pero la ruta tiene que seguir sirviendo para
         * quien entre por la url o con el javascript desactivado: si no, la
         * aplicacion dejaria de dar de alta materiales en cuanto el js
         * fallara.
         */
        $this->post('/inventario/productos', [
            'nombre' => 'Cemento sin modal',
            'unidad_medida' => 'kg',
            'estado' => 1,
        ])->assertRedirect(route('inventario.productos.index'));

        $this->assertDatabaseHas('productos', ['nombre' => 'Cemento sin modal']);
    }

    public function test_alta_y_edicion_responden_json_cuando_se_piden_por_ajax(): void
    {
        $r = $this->postJson('/inventario/productos', [
            'nombre' => 'Cemento por ajax',
            'unidad_medida' => 'kg',
            'estado' => 1,
        ])->assertOk();

        $this->assertTrue($r->json('success'));
        $this->assertNotEmpty($r->json('message'));
        $this->assertNotEmpty($r->json('codigo'), 'La respuesta debe traer el código asignado');
    }

    public function test_la_edicion_devuelve_los_datos_para_el_modal(): void
    {
        $this->producto->update([
            'nombre' => 'Cimento editado',
            'categoria' => 'Cementos',
            'stock_minimo' => 250,
            'descripcion' => 'Para la pila',
        ]);

        $r = $this->getJson("/inventario/productos/{$this->producto->id}/edit")
            ->assertOk();

        $this->assertSame($this->producto->id, $r->json('id'));
        $this->assertSame('Cimento editado', $r->json('nombre'));
        $this->assertSame($this->producto->codigo, $r->json('codigo'));
        $this->assertSame('Cementos', $r->json('categoria'));
        $this->assertEqualsWithDelta(250.0, (float) $r->json('stock_minimo'), 0.01);
        $this->assertSame('Para la pila', $r->json('descripcion'));
        $this->assertTrue($r->json('estado'));
    }

    public function test_el_alta_y_la_edicion_no_piden_lo_que_calcula_el_sistema(): void
    {
        $this->entrar(200, 4.00);

        $r = $this->getJson("/inventario/productos/{$this->producto->id}/edit")->assertOk();

        /*
         * La existencia y el costo promedio los mueve el kardex, no el
         * formulario. Si el modal los enviara, un valor tecleado se
         * guardaria como si fuera verdad y el almacen quedaria mentido.
         */
        $formulario = file_get_contents(
            resource_path('views/inventario/productos/_form.blade.php')
        );

        $this->assertStringNotContainsString('name="existencia"', $formulario);
        $this->assertStringNotContainsString('name="cpp_actual"', $formulario);
        $this->assertStringNotContainsString('name="valor_actual"', $formulario);

        // Y el modal lo dice, para que quede claro
        $this->assertStringContainsString(
            'no se cambian aquí',
            $formulario
        );

        $this->assertIsArray($r->json());
    }

    public function test_la_ficha_devuelve_el_saldo_y_el_kardex_reciente(): void
    {
        $this->entrar(200, 4.00);

        $salida = $this->inventario->registrar([
            'producto_id' => $this->producto->id,
            'tipo' => MovimientosInventario::TIPO_SALIDA,
            'cantidad' => 50,
            'fecha' => '2026-02-01',
        ]);

        $r = $this->getJson("/inventario/productos/{$this->producto->id}")
            ->assertOk()
            ->assertJsonStructure([
                'id', 'codigo', 'nombre', 'unidad_medida', 'existencia',
                'costo_promedio', 'valor_inventario', 'por_debajo_del_minimo',
                'url_kardex', 'movimientos',
            ]);

        $this->assertSame($this->producto->codigo, $r->json('codigo'));
        $this->assertEqualsWithDelta(150.0, (float) $r->json('existencia'), 0.001);
        $this->assertEqualsWithDelta(4.00, (float) $r->json('costo_promedio'), 0.0001);
        $this->assertEqualsWithDelta(600.00, (float) $r->json('valor_inventario'), 0.01);
        $this->assertFalse($r->json('por_debajo_del_minimo'));

        // El kardex reciente trae los dos movimientos, del mas nuevo al viejo
        $movimientos = $r->json('movimientos');
        $this->assertCount(2, $movimientos);
        $this->assertSame($salida->id, $movimientos[0]['id']);
        $this->assertFalse($movimientos[0]['es_entrada']);
        $this->assertTrue($movimientos[1]['es_entrada']);
        $this->assertStringContainsString('Entrada', $movimientos[1]['tipo']);
    }

    public function test_la_ficha_avisa_cuando_no_hay_costo_cargado(): void
    {
        // Un material dado de alta pero al que nunca se le registro una
        // entrada: no tiene costo, y consumirlo sumaria cero al proceso
        $r = $this->getJson("/inventario/productos/{$this->producto->id}")->assertOk();

        $this->assertEqualsWithDelta(0.0, (float) $r->json('costo_promedio'), 0.0001);
        $this->assertSame([], $r->json('movimientos'));
    }

    public function test_la_ficha_avisa_cuando_queda_bajo_el_minimo(): void
    {
        $this->entrar(5, 4.00);

        $r = $this->getJson("/inventario/productos/{$this->producto->id}")->assertOk();

        $this->assertTrue(
            $r->json('por_debajo_del_minimo'),
            'Con 5 kg de existencia y un minimo de 10, tiene que avisar'
        );
    }

    public function test_borrar_por_ajax_avisa_si_desactivo_en_vez_de_borrar(): void
    {
        $this->entrar(100, 4.00);

        $r = $this->deleteJson("/inventario/productos/{$this->producto->id}")
            ->assertOk();

        $this->assertTrue($r->json('success'));

        $this->assertTrue(
            $r->json('desactivado'),
            'Con movimientos hay que decir que se desactivo, no que se borro'
        );

        $this->assertNotSoftDeleted('productos', ['id' => $this->producto->id]);
    }

    public function test_el_alta_por_ajax_crea_el_material(): void
    {
        $this->postJson('/inventario/productos', [
            'nombre' => 'Cemento por ajax',
            'unidad_medida' => 'kg',
            'stock_minimo' => 20,
            'estado' => 1,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('productos', ['nombre' => 'Cemento por ajax']);
    }

    // ==================================================================
    // La pantalla del almacen
    // ==================================================================

    public function test_la_pantalla_del_almacen_carga(): void
    {
        $this->get('/inventario/movimientos')->assertOk();
    }

    public function test_el_filtro_por_tipo_se_pide_al_servidor(): void
    {
        // El filtro va en la url y la tabla lo recibe por ajax
        $this->get('/inventario/movimientos?tipo=entrada')->assertOk();
        $this->get('/inventario/movimientos?tipo=salida')->assertOk();

        $this->post('/inventario/movimientos', $this->payload([
            'tipo' => MovimientosInventario::TIPO_ENTRADA,
            'costo_unitario' => 6.00,
        ]))->assertRedirect();
    }

    public function test_se_puede_registrar_una_entrada(): void
    {
        $this->post('/inventario/movimientos', [
            'producto_id' => $this->producto->id,
            'tipo' => MovimientosInventario::TIPO_ENTRADA,
            'fecha' => '2026-01-10',
            'cantidad' => 100,
            'costo_unitario' => 4.00,
            'moneda_id' => $this->monedaBase->id,
        ])->assertRedirect(route('inventario.movimientos.index'));

        $saldo = InventarioProducto::where('producto_id', $this->producto->id)->first();

        $this->assertEqualsWithDelta(100.0, (float) $saldo->cantidad_actual, 0.001);
        $this->assertEqualsWithDelta(4.00, (float) $saldo->cpp_actual, 0.0001);
    }

    public function test_una_cantidad_cero_se_rechaza(): void
    {
        $this->post('/inventario/movimientos', $this->payload([
            'tipo' => MovimientosInventario::TIPO_ENTRADA,
            'cantidad' => 0,
        ]))->assertSessionHasErrors('cantidad');
    }

    public function test_un_tipo_inventado_se_rechaza(): void
    {
        /*
         * La columna tipo es texto libre. Sin esta comprobacion se podria
         * guardar un tipo que no mueve el saldo, y el material entraria o
         * saldria del kardex sin tocar las existencias.
         */
        $this->post('/inventario/movimientos', $this->payload([
            'tipo' => 'lo que sea',
        ]))->assertSessionHasErrors('tipo');
    }

    public function test_borrar_un_movimiento_corrige_el_almacen(): void
    {
        $entrada = $this->entrar(100, 4.00);

        $this->delete("/inventario/movimientos/{$entrada->id}")->assertRedirect();

        $saldo = InventarioProducto::where('producto_id', $this->producto->id)->first();

        $this->assertEqualsWithDelta(
            0.0,
            (float) $saldo->cantidad_actual,
            0.001,
            'Al borrar la entrada, el almacen tiene que quedar como estaba'
        );
    }

    // ==================================================================
    // El consumo en un proceso
    // ==================================================================

    public function test_se_puede_registrar_un_consumo_en_el_proceso(): void
    {
        $this->entrar(200, 4.00);

        $this->post($this->rutaProceso(), $this->payload())
            ->assertRedirect(route('procesos.ordenes_trabajo.procesos.show', [
                $this->proceso->orden_trabajo_id,
                $this->proceso->id,
            ]));

        $proceso = $this->proceso->fresh();

        $this->assertEqualsWithDelta(200.00, (float) $proceso->costo_materia_prima, 0.01);
        $this->assertEqualsWithDelta(150.0, (float) $this->producto->existencia, 0.001);
    }

    public function test_el_precio_del_formulario_se_ignora(): void
    {
        $this->entrar(200, 4.00);

        // El payload manda costo_unitario 999 a proposito
        $this->post($this->rutaProceso(), $this->payload())->assertRedirect();

        $movimiento = MovimientosInventario::where('proceso_orden_id', $this->proceso->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertEqualsWithDelta(
            4.00,
            (float) $movimiento->costo_unitario,
            0.0001,
            'El precio lo pone el promedio del almacen, no la pantalla'
        );

        $this->assertEqualsWithDelta(
            200.00,
            (float) $movimiento->costo_total,
            0.01
        );
    }

    public function test_no_se_puede_consumir_mas_de_lo_que_hay(): void
    {
        $this->entrar(100, 4.00);

        $this->post($this->rutaProceso(), $this->payload(['cantidad' => 101]))
            ->assertSessionHasErrors('cantidad');

        $this->assertEqualsWithDelta(
            100.0,
            (float) $this->producto->existencia,
            0.001,
            'Un consumo rechazado no debe haber tocado el almacen'
        );
    }

    public function test_la_tela_del_proceso_muestra_el_consumo(): void
    {
        $this->entrar(200, 4.00);

        $this->post($this->rutaProceso(), $this->payload())->assertRedirect();

        $this->assertEquals(1, $this->proceso->movimientos_materia_prima()->count());
    }

    public function test_la_tela_del_proceso_tiene_el_modal_de_consumo(): void
    {
        $orden = OrdenesTrabajo::firstOrFail();

        $html = $this->get("/procesos/ordenes-trabajo/{$orden->id}/procesos/{$this->proceso->id}")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Materia prima consumida', $html);
        $this->assertStringContainsString('id="btnNuevoMaterial"', $html);
        $this->assertStringContainsString('id="modalMaterial"', $html);
        $this->assertStringContainsString('id="formMaterial"', $html);
        $this->assertStringContainsString('materiales.js', $html);
    }

    public function test_el_modal_no_pide_el_precio_porque_lo_pone_el_inventario(): void
    {
        /*
         * Se mira el formulario del consumo y no la pagina entera: la
         * pagina del proceso trae tambien el modal de costos, que si tiene
         * campo de costo unitario porque ahi si lo escribe el usuario.
         */
        $formulario = file_get_contents(
            resource_path('views/procesos/procesos_orden/materiales/_form.blade.php')
        );

        $this->assertStringNotContainsString(
            'name="costo_unitario"',
            $formulario,
            'El formulario del consumo no debe pedir el costo unitario'
        );

        $this->assertStringContainsString('readonly', $formulario);
        $this->assertStringContainsString(
            'Lo fija el costo promedio del almacén',
            $formulario,
            'Y lo dice, para que quede claro que el precio no se elige aqui'
        );
    }

    public function test_el_consumo_no_pide_ni_tipo_ni_moneda(): void
    {
        /*
         * El tipo lo pone la pantalla: desde aqui solo se consumen
         * materiales. Y la moneda tampoco, porque lo que sale del almacen
         * ya esta valuado en la moneda base.
         */
        $formulario = file_get_contents(
            resource_path('views/procesos/procesos_orden/materiales/_form.blade.php')
        );

        $this->assertStringNotContainsString('name="tipo"', $formulario);
        $this->assertStringNotContainsString('name="moneda_id"', $formulario);
    }

    public function test_borrar_un_consumo_devuelve_el_material(): void
    {
        $this->entrar(200, 4.00);

        $this->post($this->rutaProceso(), $this->payload())->assertRedirect();

        $movimiento = MovimientosInventario::where('proceso_orden_id', $this->proceso->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertEqualsWithDelta(150.0, (float) $this->producto->existencia, 0.001);

        $this->delete($this->rutaProceso("/{$movimiento->id}"))->assertRedirect();

        $this->assertEqualsWithDelta(
            200.0,
            (float) $this->producto->fresh()->existencia,
            0.001,
            'Al borrar el consumo, el material vuelve al almacen'
        );

        $this->assertEqualsWithDelta(
            0.0,
            (float) $this->proceso->fresh()->costo_materia_prima,
            0.01,
            'Y el costo del proceso se queda sin materia prima'
        );
    }

    public function test_un_consumo_no_se_puede_borrar_desde_el_kardex(): void
    {
        $this->entrar(200, 4.00);

        $this->post($this->rutaProceso(), $this->payload())->assertRedirect();

        $movimiento = MovimientosInventario::where('proceso_orden_id', $this->proceso->id)
            ->latest('id')
            ->firstOrFail();

        $this->delete("/inventario/movimientos/{$movimiento->id}")
            ->assertRedirect(route('inventario.movimientos.index'));

        $this->assertNotSoftDeleted('movimientos_inventario', ['id' => $movimiento->id]);

        $this->assertEqualsWithDelta(
            150.0,
            (float) $this->producto->fresh()->existencia,
            0.001,
            'El material no debe volver al almacen por la puerta de atrás'
        );
    }

    // ==================================================================
    // El cruce de procesos
    // ==================================================================

    public function test_no_se_puede_tocar_el_consumo_de_otro_proceso(): void
    {
        $otro = $this->crearProceso();

        $this->entrar(200, 4.00);

        $this->post($this->rutaProceso(), $this->payload())->assertRedirect();

        $movimiento = MovimientosInventario::where('proceso_orden_id', $this->proceso->id)
            ->latest('id')
            ->firstOrFail();

        // Con la url del otro proceso, el consumo es de este
        $this->delete($this->rutaProceso("/{$movimiento->id}", $otro))->assertNotFound();

        $this->assertNotSoftDeleted('movimientos_inventario', ['id' => $movimiento->id]);
    }

    public function test_no_se_puede_consumir_en_un_proceso_de_otra_orden(): void
    {
        $this->entrar(200, 4.00);

        /*
         * Sin la comprobacion del proceso, la url de otra orden dejaria
         * guardar un consumo apuntando a un proceso que no es suyo, y el
         * material apareceria en el desglose de una orden por la que nunca
         * paso.
         */
        $this->post(
            '/procesos/ordenes-trabajo/999999/procesos/' . $this->proceso->id . '/materiales',
            $this->payload()
        )->assertNotFound();
    }

    // ==================================================================
    // Permisos
    // ==================================================================

    public function test_hacen_falta_permisos_para_tocar_el_almacen(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->usuarioSinPermisos());

        $this->get('/inventario/productos')->assertForbidden();
        $this->get('/inventario/movimientos')->assertForbidden();
        $this->post('/inventario/movimientos', $this->payload())->assertForbidden();
    }

    public function test_hacen_falta_permisos_para_consumir_material(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->entrar(200, 4.00);

        $this->actingAs($this->usuarioSinPermisos());

        $this->post($this->rutaProceso(), $this->payload())->assertForbidden();

        $this->assertEqualsWithDelta(
            200.0,
            (float) $this->producto->fresh()->existencia,
            0.001,
            'Un consumo sin permiso no debe haber movido el almacen'
        );
    }

    private function usuarioSinPermisos(): User
    {
        $usuario = User::create([
            'name' => 'Sin permisos',
            'email' => 'mat-sin-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);

        $usuario->syncRoles([]);

        return $usuario;
    }
}

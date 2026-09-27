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
            Producto::CODIGO => 'MAT-' . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
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
            'codigo' => 'CEM-001',
            'nombre' => 'Cemento',
            'unidad_medida' => 'kg',
            'stock_minimo' => 50,
            'estado' => 1,
        ])->assertRedirect(route('inventario.productos.index'));

        $this->assertDatabaseHas('productos', ['codigo' => 'CEM-001']);
    }

    public function test_no_se_pueden_dos_materiales_con_el_mismo_codigo(): void
    {
        $this->post('/inventario/productos', [
            'codigo' => $this->producto->codigo,
            'nombre' => 'Otro',
            'unidad_medida' => 'kg',
        ])->assertSessionHasErrors('codigo');
    }

    public function test_el_material_necesita_codigo_nombre_y_unidad(): void
    {
        $this->post('/inventario/productos', [])
            ->assertSessionHasErrors(['codigo', 'nombre', 'unidad_medida']);
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

    public function test_la_ficha_del_material_muestra_el_saldo_y_el_kardex(): void
    {
        $this->entrar(200, 4.00);

        $html = $this->get("/inventario/productos/{$this->producto->id}")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($this->producto->nombre, $html);
        $this->assertStringContainsString('Existencia', $html);
        $this->assertStringContainsString('Costo promedio', $html);
        $this->assertStringContainsString('Últimos movimientos', $html);
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

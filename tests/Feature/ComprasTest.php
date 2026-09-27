<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\InventarioProducto;
use App\Models\Moneda;
use App\Models\MovimientosInventario;
use App\Models\Producto;
use App\Models\Proveedore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Compras: lo que se le compra a un proveedor, y lo que eso le hace al
 * almacen.
 *
 * Lo que se comprueba aqui, por encima de las pantallas, es la regla del
 * almacen: una compra pendiente es un papel, y solo al finalizarla entra el
 * material. De ahi salen los casos que mas se Importan:
 *
 *   - pendiente o en proceso no mueven el almacen;
 *   - al finalizar, el material entra y el costo promedio se mueve solo;
 *   - al deshacer, el material sale y el promedio vuelve a su valor;
 *   - deshacer no se permite si el material ya se consumio, y se dice por que;
 *   - los totales los suma el servidor, no el formulario.
 */
class ComprasTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'compras-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

    private function crearProveedor(): Proveedore
    {
        return Proveedore::crearConCodigo([
            Proveedore::NOMBRE => 'Proveedor de la prueba de compras',
            Proveedore::ESTADO => true,
        ]);
    }

    private function crearMaterial(string $nombre = 'Cemento de la prueba'): Producto
    {
        return Producto::crearConCodigo([
            Producto::NOMBRE => $nombre,
            Producto::UNIDAD_MEDIDA => 'kg',
            Producto::ESTADO => true,
        ]);
    }

    private function moneda(): Moneda
    {
        return Moneda::where('es_moneda_base', true)->firstOrFail();
    }

    /**
     * Los datos de una compra con una sola linea.
     */
    private function datosCompra(
        Proveedore $proveedor,
        Producto $material,
        array $extra = []
    ): array {
        return array_merge([
            'proveedor_id' => $proveedor->id,
            'fecha' => '2026-09-20',
            'moneda_id' => $this->moneda()->id,
            'estado' => Compra::ESTADO_FINALIZADA,
            'productos' => [
                ['producto_id' => $material->id, 'cantidad' => 1000, 'costo_unitario' => 5.00],
            ],
        ], $extra);
    }

    /**
     * Crea la compra por la pantalla, con lo que de verdad hace un usuario.
     */
    private function crearCompra(array $datos): \Illuminate\Testing\TestResponse
    {
        return $this->post('/inventario/compras', $datos);
    }

    // ==================================================================
    // El codigo y los totales los pone el sistema
    // ==================================================================

    public function test_el_codigo_se_asigna_solo_y_es_del_prefijo_de_compras(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material))
            ->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();

        $this->assertMatchesRegularExpression(
            '/^' . Compra::PREFIJO_CODIGO . '\d{6}$/',
            $compra->codigo
        );
    }

    public function test_el_formulario_no_puede_imponer_el_codigo(): void
    {
        /*
         * El codigo lo pone el sistema. Si la pantalla lo aceptara, un
         * codigo repetido reventaria el indice unico de la tabla, y uno
         * inventado dejaria la serie de compras con un hueco que el
         * siguiente no rellena.
         */
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material, [
            'codigo' => 'CMP-999999',
        ]))->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();

        $this->assertNotSame('CMP-999999', $compra->codigo);
    }

    public function test_los_totales_los_suma_el_servidor_y_no_el_formulario(): void
    {
        /*
         * Si el total se aceptara del formulario, el almacen recibiria el
         * material por un importe que no es el de su linea, y el costo
         * promedio quedaria con ese error dentro sin que nada lo dijera.
         */
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material, [
            'subtotal' => 1,
            'impuesto' => 1,
            'total' => 1,
        ]))->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();

        $this->assertSame(5000.0, round((float) $compra->subtotal, 2));
        $this->assertSame(5000.0, round((float) $compra->total, 2));
    }

    public function test_el_impuesto_se_calcula_sobre_el_subtotal_de_las_lineas(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material, [
            'porcentaje_impuesto' => 15,
        ]))->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();

        $this->assertSame(5000.0, round((float) $compra->subtotal, 2));
        $this->assertSame(750.0, round((float) $compra->impuesto, 2));
        $this->assertSame(5750.0, round((float) $compra->total, 2));
    }

    public function test_una_compra_sin_lineas_no_se_guarda(): void
    {
        $proveedor = $this->crearProveedor();

        $datos = $this->datosCompra($proveedor, $this->crearMaterial());
        $datos['productos'] = [];

        $this->from('/inventario/compras/create')
            ->post('/inventario/compras', $datos)
            ->assertRedirect('/inventario/compras/create')
            ->assertSessionHasErrors('productos');

        $this->assertSame(0, Compra::count());
    }

    public function test_una_linea_con_cantidad_cero_no_se_guarda(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $datos = $this->datosCompra($proveedor, $material);
        $datos['productos'][0]['cantidad'] = 0;

        $this->from('/inventario/compras/create')
            ->post('/inventario/compras', $datos)
            ->assertSessionHasErrors('productos.0.cantidad');

        $this->assertSame(0, Compra::count());
    }

    // ==================================================================
    // La regla del almacen: solo entra al finalizarse
    // ==================================================================

    public function test_una_compra_pendiente_no_mete_material_al_almacen(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material, [
            'estado' => Compra::ESTADO_PENDIENTE,
        ]))->assertRedirect();

        $material->refresh();

        $this->assertSame(0.0, round((float) $material->existencia, 3));
        $this->assertSame(
            0,
            MovimientosInventario::where('compra_id', Compra::latest('id')->value('id'))->count()
        );
    }

    public function test_una_compra_en_proceso_tampoco_mete_material(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material, [
            'estado' => Compra::ESTADO_EN_PROCESO,
        ]))->assertRedirect();

        $material->refresh();

        $this->assertSame(0.0, round((float) $material->existencia, 3));
    }

    public function test_una_compra_finalizada_mete_el_material_al_almacen(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material))->assertRedirect();

        $material->refresh();

        $this->assertSame(1000.0, round((float) $material->existencia, 3));
        $this->assertSame(5.0, round((float) $material->costo_promedio, 4));
    }

    public function test_la_compra_y_su_entrada_de_almacen_van_unidas(): void
    {
        /*
         * El movimiento tiene que decir de que compra vino. Buscarlo por el
         * texto de referencia no serviria, porque ese campo lo escribe
         * quien registra el movimiento a mano.
         */
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material))->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();

        $movimiento = MovimientosInventario::where('compra_id', $compra->id)->firstOrFail();

        $this->assertSame(MovimientosInventario::TIPO_ENTRADA, $movimiento->tipoNormalizado());
        $this->assertSame(1000.0, round((float) $movimiento->cantidad, 3));
        $this->assertSame($compra->codigo, $movimiento->referencia);
        $this->assertNull($movimiento->orden_trabajo_id);
        $this->assertNull($movimiento->proceso_orden_id);
    }

    public function test_una_segunda_compra_a_otro_precio_mueve_el_costo_promedio(): void
    {
        $proveedor = $this->crearProveedor();
        $cemento = $this->crearMaterial();

        // 1000 kg a 5.00
        $this->crearCompra($this->datosCompra($proveedor, $cemento))->assertRedirect();

        // 1000 kg a 7.00
        $this->crearCompra($this->datosCompra($proveedor, $cemento, [
            'fecha' => '2026-09-25',
            'productos' => [
                ['producto_id' => $cemento->id, 'cantidad' => 1000, 'costo_unitario' => 7.00],
            ],
        ]))->assertRedirect();

        $cemento->refresh();

        $this->assertSame(2000.0, round((float) $cemento->existencia, 3));

        // (1000x5 + 1000x7) / 2000 = 6.00
        $this->assertSame(6.0, round((float) $cemento->costo_promedio, 4));
    }

    public function test_finalizar_una_compra_pendiente_hace_que_entre_el_material(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material, [
            'estado' => Compra::ESTADO_PENDIENTE,
        ]))->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();
        $material->refresh();

        $this->assertSame(0.0, round((float) $material->existencia, 3));

        $this->put('/inventario/compras/' . $compra->id, $this->datosCompra(
            $proveedor,
            $material,
            ['estado' => Compra::ESTADO_FINALIZADA]
        ))->assertRedirect();

        $material->refresh();

        $this->assertSame(1000.0, round((float) $material->existencia, 3));
    }

    public function test_deshacer_una_compra_finalizada_saca_el_material(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material))->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();

        $this->put('/inventario/compras/' . $compra->id, $this->datosCompra(
            $proveedor,
            $material,
            ['estado' => Compra::ESTADO_CANCELADA]
        ))->assertRedirect();

        $material->refresh();

        $this->assertSame(0.0, round((float) $material->existencia, 3));
    }

    public function test_deshacer_una_entrada_devuelve_el_costo_promedio_a_su_valor(): void
    {
        /*
         * El promedio se queda quieto cuando se consume, pero no cuando se
         * deshace una entrada: el material se devuelve porque no llego a
         * estar dentro, y el promedio de lo que queda es otro. Si se
         * dejara, el costo del almacen se mediria contra un material que ya
         * no esta.
         */
        $proveedor = $this->crearProveedor();
        $cemento = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $cemento, [
            'productos' => [
                ['producto_id' => $cemento->id, 'cantidad' => 1000, 'costo_unitario' => 5.00],
            ],
        ]))->assertRedirect();

        $primera = Compra::orderBy('id')->firstOrFail();

        $this->crearCompra($this->datosCompra($proveedor, $cemento, [
            'fecha' => '2026-09-25',
            'productos' => [
                ['producto_id' => $cemento->id, 'cantidad' => 1000, 'costo_unitario' => 7.00],
            ],
        ]))->assertRedirect();

        $cemento->refresh();
        $this->assertSame(6.0, round((float) $cemento->costo_promedio, 4));

        $segunda = Compra::orderByDesc('id')->firstOrFail();

        $this->put('/inventario/compras/' . $segunda->id, $this->datosCompra(
            $proveedor,
            $cemento,
            [
                'estado' => Compra::ESTADO_CANCELADA,
                'productos' => [
                    ['producto_id' => $cemento->id, 'cantidad' => 1000, 'costo_unitario' => 7.00],
                ],
            ]
        ))->assertRedirect();

        $cemento->refresh();

        $this->assertSame(1000.0, round((float) $cemento->existencia, 3));
        $this->assertSame(5.0, round((float) $cemento->costo_promedio, 4));
        $this->assertNotSame(0, $primera->id);
    }

    public function test_no_se_puede_deshacer_una_compra_cuyo_material_ya_se_consumio(): void
    {
        /*
         * Si se dejara, el almacen quedaria en negativo: a partir de ahi
         * los materiales siguientes saldrian gratis, porque el motor de
         * inventario no deja gastar mas de lo que hay. Mejor negarse y
         * decir por que.
         */
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material))->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();

        // Se consume parte del material que trajo la compra
        app(\App\Services\InventarioService::class)->registrar([
            'producto_id' => $material->id,
            'tipo' => MovimientosInventario::TIPO_SALIDA,
            'cantidad' => 600,
            'fecha' => '2026-09-26 08:00:00',
        ]);

        $material->refresh();
        $this->assertSame(400.0, round((float) $material->existencia, 3));

        $this->from('/inventario/compras/' . $compra->id . '/edit')
            ->put('/inventario/compras/' . $compra->id, $this->datosCompra(
                $proveedor,
                $material,
                ['estado' => Compra::ESTADO_CANCELADA]
            ))
            ->assertRedirect('/inventario/compras/' . $compra->id . '/edit')
            ->assertSessionHasErrors('estado');

        $material->refresh();

        // El almacen se queda como estaba: el intento no cambio nada
        $this->assertSame(400.0, round((float) $material->existencia, 3));
    }

    public function test_corregir_una_compra_finalizada_cambia_el_almacen(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material))->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();

        $this->put('/inventario/compras/' . $compra->id, $this->datosCompra(
            $proveedor,
            $material,
            [
                'productos' => [
                    ['producto_id' => $material->id, 'cantidad' => 400, 'costo_unitario' => 8.00],
                ],
            ]
        ))->assertRedirect();

        $material->refresh();

        $this->assertSame(400.0, round((float) $material->existencia, 3));
        $this->assertSame(8.0, round((float) $material->costo_promedio, 4));
    }

    public function test_quitar_una_linea_de_una_compra_finalizada_su_material_del_almacen(): void
    {
        $proveedor = $this->crearProveedor();
        $cemento = $this->crearMaterial();
        $arena = $this->crearMaterial('Arena de la prueba');

        $this->crearCompra($this->datosCompra($proveedor, $cemento, [
            'productos' => [
                ['producto_id' => $cemento->id, 'cantidad' => 1000, 'costo_unitario' => 5.00],
                ['producto_id' => $arena->id, 'cantidad' => 500, 'costo_unitario' => 2.00],
            ],
        ]))->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();

        $cemento->refresh();
        $arena->refresh();

        $this->assertSame(1000.0, round((float) $cemento->existencia, 3));
        $this->assertSame(500.0, round((float) $arena->existencia, 3));

        // Se quita la linea de la arena
        $this->put('/inventario/compras/' . $compra->id, $this->datosCompra(
            $proveedor,
            $cemento,
            [
                'productos' => [
                    ['producto_id' => $cemento->id, 'cantidad' => 1000, 'costo_unitario' => 5.00],
                ],
            ]
        ))->assertRedirect();

        $arena->refresh();
        $compra->refresh();

        $this->assertSame(0.0, round((float) $arena->existencia, 3));
        $this->assertCount(1, $compra->detalles);
    }

    public function test_el_mismo_material_en_dos_lineas_entra_una_sola_vez(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material, [
            'productos' => [
                ['producto_id' => $material->id, 'cantidad' => 400, 'costo_unitario' => 5.00],
                ['producto_id' => $material->id, 'cantidad' => 600, 'costo_unitario' => 6.00],
            ],
        ]))->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();
        $material->refresh();

        $this->assertSame(1, MovimientosInventario::where('compra_id', $compra->id)->count());
        $this->assertSame(1000.0, round((float) $material->existencia, 3));

        // (400x5 + 600x6) / 1000 = 5.60
        $this->assertSame(5.6, round((float) $material->costo_promedio, 4));
    }

    // ==================================================================
    // Borrar la compra
    // ==================================================================

    public function test_borrar_una_compra_finalizada_su_material_del_almacen(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material))->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();

        $this->delete('/inventario/compras/' . $compra->id)
            ->assertRedirect('/inventario/compras');

        $material->refresh();

        $this->assertSame(0.0, round((float) $material->existencia, 3));
        $this->assertSame(0, $compra->fresh() === null ? 0 : 1);
    }

    public function test_borrar_una_compra_no_deja_movimientos_vivos(): void
    {
        /*
         * Si los movimientos quedaran, al restaurar la compra volverian a
         * contar en el kardex sin que nadie los volviera a crear, y el
         * almacen se encontraria con material que no sabe de donde salio.
         */
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material))->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();
        $idMovimiento = MovimientosInventario::where('compra_id', $compra->id)->value('id');

        $this->delete('/inventario/compras/' . $compra->id);

        $this->assertTrue(
            MovimientosInventario::withTrashed()->where('id', $idMovimiento)->first()?->deleted_at !== null,
            'El movimiento de la compra deberia quedar dado de baja'
        );
    }

    public function test_borrar_una_compra_no_toca_las_compras_que_mas(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material))->assertRedirect();
        $this->crearCompra($this->datosCompra($proveedor, $material, [
            'fecha' => '2026-09-26',
        ]))->assertRedirect();

        $primera = Compra::orderBy('id')->firstOrFail();
        $segunda = Compra::orderByDesc('id')->firstOrFail();

        $this->delete('/inventario/compras/' . $primera->id);

        $material->refresh();

        $this->assertSame(1000.0, round((float) $material->existencia, 3));
        $this->assertNotNull($segunda->fresh());
    }

    // ==================================================================
    // El proveedor con compras ya no se borra
    // ==================================================================

    public function test_un_proveedor_con_compras_se_desactiva_en_vez_de_borrarse(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material))->assertRedirect();

        $this->delete('/inventario/proveedores/' . $proveedor->id);

        $proveedor->refresh();

        $this->assertNotNull($proveedor, 'El proveedor no deberia haberse borrado de verdad');
        $this->assertFalse((bool) $proveedor->estado);
    }

    // ==================================================================
    // Las pantallas
    // ==================================================================

    public function test_la_lista_de_compras_se_ve(): void
    {
        $this->get('/inventario/compras')
            ->assertOk()
            ->assertSee('Compras')
            ->assertSee('compras-table', false);
    }

    public function test_la_pantalla_de_alta_se_ve(): void
    {
        $this->get('/inventario/compras/create')
            ->assertOk()
            ->assertSee('Nueva compra')
            ->assertSee('id="formCompra"', false)
            ->assertSee('id="btnAgregarLinea"', false);
    }

    public function test_el_alta_no_trae_una_fila_con_material_escogido(): void
    {
        /*
         * La rejilla arranca vacia a proposito: si trajera el primer
         * material puesto, el alta pareceria una compra ya empezada y
         * habria que vaciarla a mano.
         */
        $this->get('/inventario/compras/create')
            ->assertOk()
            ->assertSee('[]', false);
    }

    public function test_la_pantalla_de_edicion_trae_las_lineas_que_ya_tenia(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material))->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();

        $this->get('/inventario/compras/' . $compra->id . '/edit')
            ->assertOk()
            ->assertSee('datosLineas', false)
            ->assertSee('"producto_id":' . $material->id, false)
            ->assertSee('"cantidad":1000', false)
            ->assertSee('"costo_unitario":5', false);
    }

    public function test_la_ficha_avisa_de_si_el_material_esta_en_el_almacen(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material, [
            'estado' => Compra::ESTADO_PENDIENTE,
        ]))->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();

        $this->get('/inventario/compras/' . $compra->id)
            ->assertOk()
            ->assertSee('todavía')
            ->assertSee('no ha entrado al almacén', false);

        $compra->update(['estado' => Compra::ESTADO_FINALIZADA]);
        app(\App\Services\ComprasInventarioService::class)->sincronizar($compra->fresh());

        $this->get('/inventario/compras/' . $compra->id)
            ->assertOk()
            ->assertSee('está finalizada');
    }

    public function test_la_ficha_muestra_las_entradas_que_cerro_la_compra(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial('Barro de la prueba');

        $this->crearCompra($this->datosCompra($proveedor, $material))->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();

        $this->get('/inventario/compras/' . $compra->id)
            ->assertOk()
            ->assertSee('Entradas que esta compra generó al almacén', false)
            ->assertSee('Barro de la prueba');
    }

    public function test_el_boton_de_borrar_de_una_compra_finalizada_avisa_de_que_sale_el_material(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material))->assertRedirect();

        /*
         * Las filas de la tabla las dibuja el navegador con los datos que
         * pide por ajax, asi que en el html de la pagina no hay ni una.
         * Preguntar a la tabla es como la ve el usuario de verdad, y es lo
         * unico que comprueba el texto del boton.
         */
        $json = $this->ajaxJson('/inventario/compras');

        $filas = $json['data'];

        $this->assertCount(1, $filas);

        $acciones = $filas[0]['action'];

        $this->assertStringContainsString('data-confirm-delete', $acciones);
        $this->assertStringContainsString('el material saldrá del almacén', $acciones);
        $this->assertStringContainsString('Eliminar la compra', $acciones);
    }

    public function test_el_boton_de_borrar_de_una_compra_no_finalizada_no_amenaza_con_el_almacen(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material, [
            'estado' => Compra::ESTADO_PENDIENTE,
        ]))->assertRedirect();

        $acciones = $this->ajaxJson('/inventario/compras')['data'][0]['action'];

        $this->assertStringNotContainsString('el material saldrá del almacén', $acciones);
        $this->assertStringContainsString('no ha entrado material al almacén', $acciones);
    }

    public function test_la_fila_de_la_tabla_dice_si_el_material_entro_o_no(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material, [
            'estado' => Compra::ESTADO_PENDIENTE,
        ]))->assertRedirect();

        $fila = $this->ajaxJson('/inventario/compras')['data'][0];

        $this->assertSame('Pendiente', strip_tags($fila['estado']));
        $this->assertSame(Compra::firstOrFail()->codigo, $fila['codigo']);
    }

    /**
     * Le pide los datos a una tabla por ajax, como hace el navegador.
     *
     * Yajra solo contesta json si ve las dos cabeceras: la de que es una
     * peticion de ajax y la de que se acepta json. Con una de las dos se
     * queda pensando que es una visita normal y devuelve html.
     *
     * @return array<string, mixed>
     */
    private function ajaxJson(string $url): array
    {
        $respuesta = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($url);

        $respuesta->assertOk();

        return json_decode($respuesta->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_el_js_de_la_rejilla_carga_en_alta_y_edicion(): void
    {
        $this->assertFileExists(public_path('js/inventario/compras.js'));

        $this->get('/inventario/compras/create')
            ->assertOk()
            ->assertSee('js/inventario/compras.js', false);
    }

    public function test_el_js_de_la_rejilla_engancha_select2_a_las_filas_nuevas(): void
    {
        /*
         * Select2 solo se engancha a los selects que encuentra al abrir la
         * pagina. Si las filas nuevas se agregan sin avisarle, las lineas
         * de abajo salen como un select del sistema operativo mientras las
         * de arriba salen con buscador, y el formulario parece de dos
         * tipos.
         */
        $js = file_get_contents(public_path('js/inventario/compras.js'));

        $this->assertStringContainsString('iniciarSelect2', $js);
        $this->assertStringContainsString("addClass('select2')", $js);
        $this->assertStringContainsString("select2('destroy')", $js);
    }

    public function test_el_js_cambia_el_indice_de_ejemplo_de_la_fila_clonada(): void
    {
        /*
         * El servidor espera productos[0], productos[1]. Si el indice de
         * ejemplo se quedara, las dos lineas se pisarian y solo se guardaria
         * una.
         */
        $js = file_get_contents(public_path('js/inventario/compras.js'));

        $this->assertStringContainsString("replace('__i__', indice)", $js);
    }

    public function test_el_js_avisa_del_material_antes_de_dejar_guardar_sin_lineas(): void
    {
        $js = file_get_contents(public_path('js/inventario/compras.js'));

        $this->assertStringContainsString('revisarAntesDeGuardar', $js);
        $this->assertStringContainsString('preventDefault', $js);
    }

    // ==================================================================
    // El modelo
    // ==================================================================

    public function test_los_cuatro_estados_de_compra_son_los_mismos_que_los_de_la_orden(): void
    {
        $this->assertSame(
            \App\Models\OrdenesTrabajo::ESTADOS,
            Compra::ESTADOS,
            'La compra deberia usar el mismo catalogo de estados que la orden, '
            . 'porque describen lo mismo: una cosa que esta, pendiente, en '
            . 'curso o terminada'
        );
    }

    public function test_el_subtotal_de_la_linea_lo_calcula_el_modelo(): void
    {
        $detalle = new DetalleCompra();
        $detalle->cantidad = 3;
        $detalle->costo_unitario = 2.5;
        $detalle->calcularSubtotal();

        $this->assertSame(7.5, round((float) $detalle->subtotal, 2));
    }

    public function test_una_linea_con_cantidad_cero_no_calcula_subtotal(): void
    {
        $detalle = new DetalleCompra();
        $detalle->cantidad = 0;
        $detalle->costo_unitario = 5;

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $detalle->calcularSubtotal();
    }

    public function test_el_saldo_del_material_se_guarda_al_entrar_una_compra(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material))->assertRedirect();

        $saldo = InventarioProducto::where('producto_id', $material->id)->firstOrFail();

        $this->assertSame(1000.0, round((float) $saldo->cantidad_actual, 3));
        $this->assertSame(5000.0, round((float) $saldo->valor_actual, 2));
    }

    public function test_las_lineas_de_una_compra_amanecen_soft_delete(): void
    {
        $proveedor = $this->crearProveedor();
        $cemento = $this->crearMaterial();
        $arena = $this->crearMaterial('Arena de la prueba');

        $this->crearCompra($this->datosCompra($proveedor, $cemento, [
            'productos' => [
                ['producto_id' => $cemento->id, 'cantidad' => 100, 'costo_unitario' => 5.00],
                ['producto_id' => $arena->id, 'cantidad' => 50, 'costo_unitario' => 2.00],
            ],
        ]))->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();
        $arena->refresh();

        $this->put('/inventario/compras/' . $compra->id, $this->datosCompra(
            $proveedor,
            $cemento,
            [
                'productos' => [
                    ['producto_id' => $cemento->id, 'cantidad' => 100, 'costo_unitario' => 5.00],
                ],
            ]
        ))->assertRedirect();

        $this->assertSame(1, DetalleCompra::where('compra_id', $compra->id)->count());
        $this->assertSame(
            1,
            DetalleCompra::onlyTrashed()->where('compra_id', $compra->id)->count()
        );
    }
}

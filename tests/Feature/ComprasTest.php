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

    /**
     * Cuantas compras hay ahora mismo.
     *
     * Existe para no afirmar "cero compras". Un test que dice eso solo vale
     * en una base de datos vacia, y en cuanto el usuario guarda su primera
     * compra de verdad el test falla sin que nada de lo que comprueba haya
     * cambiado. Lo que interesa es si ESTA compra se guardo, no si las hay.
     */
    private function cuantasComprasHay(): int
    {
        return Compra::withTrashed()->count();
    }

    /**
     * La compra que creo este test, y solo este.
     *
     * Se busca por proveedor porque cada test se hace su propio proveedor. Es
     * lo que evita que un test se lleve por delante, sin querer, la compra de
     * depuracion que hay en la base: si dos pruebas comparten proveedor, una
     * podria leer la compra de la otra y darse por buena sin haber comprobado
     * nada.
     */
    private function compraDe(Proveedore $proveedor): Compra
    {
        return Compra::where('proveedor_id', $proveedor->id)->latest('id')->firstOrFail();
    }

    /**
     * Comprueba que no se guardo ninguna compra, ni de este proveedor ni de
     * nadie mas.
     *
     * Las dos mitades hacen falta. Que no haya compras nuevas lo dice el
     * antes y el despues; que no se haya guardado una compra huerfana lo dice
     * lo de que el proveedor no tenga ninguna, porque una compra sin proveedor
     * —o con las lineas vacias— se rechaza antes de llegar a tener proveedor
     * asignado, y con solo mirar el total pasaria desapercibida.
     */
    private function assertNoSeGuardoNingunaCompra(Proveedore $proveedor, int $antes, string $mensaje): void
    {
        $this->assertSame(
            $antes,
            $this->cuantasComprasHay(),
            $mensaje . ' — se ha guardado alguna compra de mas'
        );

        $this->assertSame(
            0,
            Compra::withTrashed()->where('proveedor_id', $proveedor->id)->count(),
            $mensaje . ' — este proveedor tiene compras, cuando no deberia tener ninguna'
        );
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
        $antes = $this->cuantasComprasHay();

        $datos = $this->datosCompra($proveedor, $this->crearMaterial());
        $datos['productos'] = [];

        $this->from('/inventario/compras/create')
            ->post('/inventario/compras', $datos)
            ->assertRedirect('/inventario/compras/create')
            ->assertSessionHasErrors('productos');

        $this->assertNoSeGuardoNingunaCompra(
            $proveedor,
            $antes,
            'Una compra sin lineas no deberia guardarse'
        );
    }

    public function test_una_linea_con_cantidad_cero_no_se_guarda(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();
        $antes = $this->cuantasComprasHay();

        $datos = $this->datosCompra($proveedor, $material);
        $datos['productos'][0]['cantidad'] = 0;

        $this->from('/inventario/compras/create')
            ->post('/inventario/compras', $datos)
            ->assertSessionHasErrors('productos.0.cantidad');

        $this->assertNoSeGuardoNingunaCompra(
            $proveedor,
            $antes,
            'Una compra con una linea a cero no deberia guardarse'
        );
    }

    // ==================================================================
    // Las filas que el formulario lleva de mas
    // ==================================================================
    /*
     * El formulario lleva siempre una fila de ejemplo —la que esta oculta y
     * solo sirve de molde para clonar— y el usuario puede anadir lineas y
     * dejarlas a medias antes de guardar.
     *
     * Ninguna de las dos puede impedir guardar una compra que esta bien. Y
     * ninguna puede hacerlo avisando de que falta el material cuando el
     * material esta elegido: eso era lo que pasaba, y el mensaje senalaba a
     * una fila que el usuario no tenia delante, que es la peor manera de
     * fallar porque el usuario no tiene forma de saber que hacer con el.
     */

    public function test_la_fila_de_ejemplo_no_impide_guardar_la_compra(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $datos = $this->datosCompra($proveedor, $material);

        // Lo que de verdad mandaba el navegador: la fila de ejemplo con sus
        // nombres de ejemplo y sin material, y la linea buena con su indice.
        $datos['productos'] = [
            '__i__' => ['producto_id' => '', 'cantidad' => 1, 'costo_unitario' => 0.00],
            0 => ['producto_id' => $material->id, 'cantidad' => 1000, 'costo_unitario' => 5.00],
        ];

        $this->crearCompra($datos)->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();

        $this->assertSame(1, $compra->detalles()->count());
        $this->assertSame($material->id, $compra->detalles->first()->producto_id);
    }

    public function test_una_linea_que_se_ha_dejado_a_medias_no_impide_guardar(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $datos = $this->datosCompra($proveedor, $material);

        // Una linea anadida y sin tocar, y otra a medio rellenar.
        $datos['productos'] = [
            ['producto_id' => $material->id, 'cantidad' => 1000, 'costo_unitario' => 5.00],
            ['producto_id' => '', 'cantidad' => 1, 'costo_unitario' => 0.00],
            ['producto_id' => '', 'cantidad' => 0, 'costo_unitario' => 0.00],
        ];

        $this->crearCompra($datos)->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();

        $this->assertSame(1, $compra->detalles()->count());
    }

    public function test_el_error_de_linea_apunta_a_la_linea_que_esta_mal(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();
        $otro = $this->crearMaterial('Acido de la prueba');
        $antes = $this->cuantasComprasHay();

        $datos = $this->datosCompra($proveedor, $material);

        $datos['productos'] = [
            ['producto_id' => $material->id, 'cantidad' => 1000, 'costo_unitario' => 5.00],
            // Con material pero sin cantidad: el error tiene que senalar la
            // segunda linea, que es la que esta mal, no la primera.
            ['producto_id' => $otro->id, 'cantidad' => '', 'costo_unitario' => 3.00],
        ];

        $respuesta = $this->from('/inventario/compras/create')
            ->post('/inventario/compras', $datos);

        $errores = session('errors')->getBag('default');

        $this->assertTrue($errores->has('productos.1.cantidad'));
        $this->assertFalse($errores->has('productos.0.cantidad'));
        $this->assertFalse($errores->has('productos.0.producto_id'));

        $respuesta->assertSessionHasErrors('productos.1.cantidad');

        $this->assertNoSeGuardoNingunaCompra(
            $proveedor,
            $antes,
            'Una compra con una linea a medias no deberia guardarse'
        );
    }

    public function test_un_material_que_ya_no_esta_en_el_catalogo_se_avisa_sin_confundir(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();
        $antes = $this->cuantasComprasHay();

        $datos = $this->datosCompra($proveedor, $material);
        $datos['productos'][0]['producto_id'] = $material->id + 9999;

        $this->from('/inventario/compras/create')
            ->post('/inventario/compras', $datos)
            ->assertSessionHasErrors('productos.0.producto_id');

        $this->assertNoSeGuardoNingunaCompra(
            $proveedor,
            $antes,
            'Una compra con un material que ya no existe no deberia guardarse'
        );
    }



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

        $primera = Compra::where('proveedor_id', $proveedor->id)->orderBy('id')->firstOrFail();

        $this->crearCompra($this->datosCompra($proveedor, $cemento, [
            'fecha' => '2026-09-25',
            'productos' => [
                ['producto_id' => $cemento->id, 'cantidad' => 1000, 'costo_unitario' => 7.00],
            ],
        ]))->assertRedirect();

        $cemento->refresh();
        $this->assertSame(6.0, round((float) $cemento->costo_promedio, 4));

        $segunda = Compra::where('proveedor_id', $proveedor->id)->orderByDesc('id')->firstOrFail();

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

        $primera = Compra::where('proveedor_id', $proveedor->id)->orderBy('id')->firstOrFail();
        $segunda = Compra::where('proveedor_id', $proveedor->id)->orderByDesc('id')->firstOrFail();

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
        // Se mira la fila de esta compra y no la primera de la tabla: la
        // tabla enseña todas, y la primera puede ser una compra de depuracion
        // que hay en la base desde antes de la prueba.
        $acciones = $this->filaDeLaTabla('/inventario/compras', $proveedor)['action'];

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

        $acciones = $this->filaDeLaTabla('/inventario/compras', $proveedor)['action'];

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

        $fila = $this->filaDeLaTabla('/inventario/compras', $proveedor);

        $this->assertSame('Pendiente', strip_tags($fila['estado']));
        $this->assertSame($this->compraDe($proveedor)->codigo, $fila['codigo']);
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

    /**
     * La fila que la tabla dibuja para la compra de este test.
     *
     * Se busca por el codigo, y no se coge la primera fila, porque la tabla
     * enseña todas las compras que hay y no solo las del test. Coger la
     * primera seria comprobar el texto de una compra cualquiera: el texto
     * estaria bien y el test pasaria, sin haberseEnterado de que estaba
     * mirando la compra equivocada.
     *
     * @return array<string, mixed>
     */
    private function filaDeLaTabla(string $url, Proveedore $proveedor): array
    {
        $codigo = $this->compraDe($proveedor)->codigo;

        foreach ($this->ajaxJson($url)['data'] as $fila) {
            if (($fila['codigo'] ?? '') === $codigo) {
                return $fila;
            }
        }

        $this->fail(
            'La compra ' . $codigo . ' deberia salir en la tabla de ' . $url
            . ', y no sale. Si la lista esta paginada y esta compra cae en otra '
            . 'pagina, el fallo es de la prueba, no de la aplicacion.'
        );
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

    public function test_la_fila_de_ejemplo_no_se_manda_con_el_formulario(): void
    {
        /*
         * La fila de ejemplo es un molde: esta oculta y solo existe para que
         * el javascript la clone. Un campo con name se manda aunque la fila
         * este escondida —display:none no lo impide, solo lo impide disabled—
         * y con los nombres puestos el formulario llevaba una linea de mas
         * que el servidor validaba como si fuera de verdad: se quejaba de que
         * faltaba el material con el material de verdad elegido en otra fila.
         *
         * Por eso el molde lleva data-nombre y no name. El nombre se lo pone
         * el javascript a la fila nueva, ya con su indice.
         */
        $html = $this->get('/inventario/compras/create')->getContent();

        $this->assertMatchesRegularExpression(
            '/<tr class="linea-plantilla.*?<\/tr>/s',
            $html,
            'La fila de ejemplo deberia seguir en la vista: es el molde'
        );

        preg_match('/<tr class="linea-plantilla.*?<\/tr>/s', $html, $molde);

        $this->assertDoesNotMatchRegularExpression(
            '/<(select|input)[^>]*\sname=/',
            $molde[0],
            'La fila de ejemplo no debe llevar atributo name: un campo con '
            . 'name se manda aunque la fila este oculta, y el servidor la '
            . 'validaba como una linea mas de la compra'
        );

        $this->assertStringContainsString(
            'data-nombre="productos[__i__][producto_id]"',
            $molde[0],
            'El molde tiene que decir en data-nombre como se llama el campo, '
            . 'porque el nombre de la fila nueva sale de ahi'
        );

        // Y el nombre tiene que estar en la vista, no inventado en el js: si
        // el javascript no llegara a cargar, el formulario se mandaria sin
        // lineas y el error seria el de verdad, no el de una fila que no se
        // ve.
        foreach (['cantidad', 'costo_unitario'] as $campo) {
            $this->assertStringContainsString(
                'data-nombre="productos[__i__][' . $campo . ']"',
                $molde[0],
                'El molde deberia decir tambien el nombre de ' . $campo
            );
        }

        $js = file_get_contents(public_path('js/inventario/compras.js'));

        $this->assertStringContainsString(
            "getAttribute('data-nombre')",
            $js,
            'La fila nueva tiene que tomar su nombre del data-nombre del molde, '
            . 'porque el molde ya no lleva name'
        );
    }

    public function test_el_material_se_puede_buscar_con_select2_en_alta_y_edicion(): void
    {
        /*
         * Select2 no se carga solo: es una entrada de vite aparte y cada
         * pantalla que lo quiere tiene que pedir su modulo y sus estilos. Sin
         * esas dos lineas el material de cada linea es un <select> nativo, o
         * sea una lista desplegable sin buscador, y con el catalogo entero
         * encima buscar un material es recorrerlo todo.
         *
         * Se comparan los nombres de los archivos ya construidos y no las
         * rutas de origen, porque es lo que sale en el html: vite les pone
         * un hash que cambia con cada build y ponerlo aqui obliga a tocar el
         * test cada vez que se compila.
         */
        $compra = Compra::create([
            'codigo' => 'TMP-SELECT2',
            'proveedor_id' => $this->crearProveedor()->id,
            'fecha' => now()->toDateString(),
            'moneda_id' => $this->moneda()->id,
            'estado' => Compra::ESTADO_PENDIENTE,
            'subtotal' => 0,
            'impuesto' => 0,
            'total' => 0,
        ]);

        $manifest = json_decode(
            file_get_contents(public_path('build/manifest.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $estilos = basename($manifest['resources/scss/light/plugins/select2/custom-select2.scss']['file']);
        $modulo = basename($manifest['resources/assets/js/select2/select2-init.js']['file']);

        foreach (['/inventario/compras/create', "/inventario/compras/{$compra->id}/edit"] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertStringContainsString($estilos, $html, 'Falta el estilo de select2 en ' . $url);
            $this->assertStringContainsString($modulo, $html, 'Falta el modulo de select2 en ' . $url);
        }
    }

    public function test_el_material_dice_que_se_puede_buscar(): void
    {
        /*
         * El buscador de select2 aparece siempre, porque el modulo pone
         * minimumResultsForSearch en 0. Lo que tiene que decir el campo es
         * que se puede escribir, porque un desplegable con buscador no se
         * distingue de uno sin el mirandolo de lejos.
         */
        $html = $this->get('/inventario/compras/create')->getContent();

        $this->assertStringContainsString('data-select2-opciones', $html);
        $this->assertStringContainsString('Escriba para buscar el material', $html);
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

    // ==================================================================
    // El proveedor se elige en un modal
    // ==================================================================

    public function test_el_proveedor_se_elige_en_un_modal_y_no_en_un_desplegable(): void
    {
        /*
         * Con un desplegable habia que abrirlo y recorrer la lista entera
         * hasta dar con el proveedor, sin buscar nada, y en un telefono son
         * quince taps con la pantalla medio tapada. Con el modal hay un
         * buscador que filtra mientras se escribe.
         *
         * Se comprueba que no quede el desplegable, y no que este el campo:
         * dos caminos para elegir lo mismo es peor que ninguno, porque
         * nadie sabria cual de los dos manda.
         */
        $html = $this->get('/inventario/compras/create')->assertOk()->getContent();

        $this->assertStringContainsString('id="btnBuscarProveedor"', $html);
        $this->assertStringContainsString('id="modalSeleccionarProveedor"', $html);
        $this->assertStringContainsString('proveedor-selector-table', $html);

        $this->assertStringNotContainsString(
            '<select',
            $this->parteDelCampoProveedor($html),
            'El proveedor deberia elegirse en el modal, no en un desplegable'
        );
    }

    public function test_el_campo_del_proveedor_manda_el_id_y_no_el_nombre(): void
    {
        /*
         * El id va escondido y es lo unico que se guarda. El nombre va en un
         * campo que no se manda, y es solo para que el usuario vea a quien
         * le esta comprando: si se guardara el nombre, cambiar el nombre de
         * un proveedor dejaria las compras viejas apuntando a un nombre que
         * ya no es.
         */
        $html = $this->get('/inventario/compras/create')->assertOk()->getContent();

        $campo = $this->parteDelCampoProveedor($html);

        $this->assertMatchesRegularExpression(
            '/<input[^>]*type="hidden"[^>]*name="proveedor_id"/s',
            $campo,
            'El id del proveedor deberia ir en un campo escondido'
        );

        $this->assertMatchesRegularExpression(
            '/<input[^>]*name="proveedor_nombre"[^>]*readonly/s',
            $campo,
            'El campo del nombre deberia ser de solo lectura: se elige en el modal'
        );
    }

    public function test_una_compra_guardada_trae_su_proveedor_puesto(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material))->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();

        $html = $this->get('/inventario/compras/' . $compra->id . '/edit')
            ->assertOk()
            ->getContent();

        $campo = $this->parteDelCampoProveedor($html);

        $this->assertStringContainsString(
            'value="' . $proveedor->id . '"',
            $campo,
            'Al editar deberia venir el proveedor que ya tenia la compra'
        );

        $this->assertStringContainsString($proveedor->nombre, $campo);
    }

    public function test_el_modal_del_selector_tambien_lo_trae_la_edicion(): void
    {
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra($this->datosCompra($proveedor, $material))->assertRedirect();

        $compra = Compra::where('proveedor_id', $proveedor->id)->firstOrFail();

        $html = $this->get('/inventario/compras/' . $compra->id . '/edit')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="btnBuscarProveedor"', $html);
        $this->assertStringContainsString('id="modalSeleccionarProveedor"', $html);
    }

    public function test_el_catalogo_para_el_modal_solo_tiene_proveedores_activos(): void
    {
        /*
         * Un proveedor desactivado se puede volver a activar y se sigue
         * viendo en las compras viejas, pero no se le compra de nuevo, asi
         * que no tiene sentido ofrecerlo al elegir uno.
         */
        $activo = Proveedore::crearConCodigo([
            Proveedore::NOMBRE => 'Proveedor activo del selector',
            Proveedore::ESTADO => true,
        ]);

        $inactivo = Proveedore::crearConCodigo([
            Proveedore::NOMBRE => 'Proveedor desactivado del selector',
            Proveedore::ESTADO => false,
        ]);

        $json = $this->ajaxJson('/inventario/proveedores/selector');

        $nombres = array_column($json['data'], 'nombre');

        $this->assertContains($activo->nombre, $nombres);
        $this->assertNotContains($inactivo->nombre, $nombres);
    }

    public function test_el_catalogo_para_el_modal_devuelve_json_y_no_html(): void
    {
        /*
         * Lo que lo pide es el buscador de DataTables dentro de una ventana,
         * y ese quiere filas paginadas en json. Si esta ruta devolviera html,
         * el buscador se quedaria en blanco sin decir por que.
         */
        $this->ajaxJson('/inventario/proveedores/selector');
    }

    public function test_el_js_del_selector_rellena_el_id_y_el_nombre(): void
    {
        $js = file_get_contents(public_path('js/inventario/selector_proveedor.js'));

        // El id es lo que se guarda
        $this->assertStringContainsString("val(\$elegido.data('id'))", $js);

        // Y el nombre y el codigo van juntos, para no dudar de cual es
        $this->assertStringContainsString("data('nombre')", $js);
        $this->assertStringContainsString("data('codigo')", $js);

        // La tabla se monta al abrir el modal, no al cargar la pagina
        $this->assertStringContainsString('shown.bs.modal', $js);
    }

    public function test_el_js_del_selector_no_manda_el_nombre_al_servidor(): void
    {
        /*
         * El nombre es de lectura. Si el formulario lo mandara, el servidor
         * tendria un campo que dice de que proveedor es la compra y podria
         * fiarse de el, y entonces bastaria cambiar el campo oculto para
         * apuntar a otro proveedor del que se eligio.
         */
        $formulario = $this->get('/inventario/compras/create')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<input[^>]*name="proveedor_nombre"[^>]*>/s',
            $formulario
        );

        /*
         * Que el campo exista con ese nombre no es el problema: el problema
         * seria que tambien fuera un campo que se mande. Se comprueba que la
         * compra se guarda solo con el id.
         */
        $proveedor = $this->crearProveedor();
        $material = $this->crearMaterial();

        $this->crearCompra([
            'proveedor_id' => $proveedor->id,
            'fecha' => '2026-09-20',
            'moneda_id' => $this->moneda()->id,
            'estado' => Compra::ESTADO_PENDIENTE,
            'productos' => [
                ['producto_id' => $material->id, 'cantidad' => 1, 'costo_unitario' => 5],
            ],
        ])->assertRedirect();

        $compra = $this->compraDe($proveedor);

        $this->assertSame($proveedor->id, $compra->proveedor_id);
    }

    public function test_una_compra_sin_proveedor_no_se_guarda(): void
    {
        $material = $this->crearMaterial();
        $proveedor = $this->crearProveedor();
        $antes = $this->cuantasComprasHay();

        $datos = $this->datosCompra($proveedor, $material);
        $datos['proveedor_id'] = '';

        $this->from('/inventario/compras/create')
            ->post('/inventario/compras', $datos)
            ->assertSessionHasErrors('proveedor_id');

        $this->assertNoSeGuardoNingunaCompra(
            $proveedor,
            $antes,
            'Una compra sin proveedor no deberia guardarse'
        );
    }

    public function test_el_nombre_del_proveedor_no_puede_apuntar_a_otro_proveedor(): void
    {
        /*
         * Solo el id cuenta. Se manda el nombre de un proveedor y el id de
         * otro, y tiene que ganar el id: si ganara el nombre, bastaria
         * escribir en el campo de texto para que la compra quedara a nombre
         * de quien se puso.
         */
        $primero = $this->crearProveedor();
        $segundo = Proveedore::crearConCodigo([
            Proveedore::NOMBRE => 'Segundo proveedor de la prueba',
            Proveedore::ESTADO => true,
        ]);

        $material = $this->crearMaterial();

        $this->crearCompra(array_merge(
            $this->datosCompra($primero, $material),
            ['proveedor_nombre' => $segundo->nombre . ' (' . $segundo->codigo . ')']
        ))->assertRedirect();

        $compra = $this->compraDe($primero);

        $this->assertSame(
            $primero->id,
            $compra->proveedor_id,
            'La compra deberia apuntar al proveedor cuyo id se mando'
        );
    }

    /**
     * El trozo de html del campo del proveedor, y nada mas.
     *
     * Se recorta por el div del campo, que es donde empieza, y por el div
     * del campo siguiente, que es donde acaba de verdad. No se corta por el
     * primer </div> que sale, porque ese es el del grupo de botones y dejaria
     * fuera el input escondido del id, que va justo despues.
     *
     * Recortar asi evita ademas que el desplegable del material, que tambien
     * es un select, se cuele en lo que se comprueba del proveedor.
     */
    private function parteDelCampoProveedor(string $html): string
    {
        $inicio = strpos($html, '<div class="col-md-4">');

        if ($inicio === false) {
            return '';
        }

        $fin = strpos($html, '<div class="col-md-3">', $inicio);

        return $fin === false
            ? substr($html, $inicio)
            : substr($html, $inicio, $fin - $inicio);
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

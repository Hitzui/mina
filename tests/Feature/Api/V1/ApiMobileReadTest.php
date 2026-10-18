<?php

namespace Tests\Feature\Api\V1;

use App\Models\AcuerdosOrden;
use App\Models\Cajas;
use App\Models\Cliente;
use App\Models\CategoriasCosto;
use App\Models\Etapa;
use App\Models\Liquidacione;
use App\Models\Moneda;
use App\Models\MovimientosCosto;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use App\Models\Producto;
use App\Models\InventarioProducto;
use App\Models\MovimientosInventario;
use App\Models\TiposParticipacion;
use App\Models\TiposIngreso;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApiMobileReadTest extends TestCase
{
    use DatabaseTransactions;

    private string $token;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = [
            'ordenes_trabajo.view', 'ordenes_trabajo.create', 'procesos_orden.create', 'clientes.view', 'movimientos_caja.view', 'productos.view',
            'movimientos_inventario.view', 'cotizaciones.view', 'cobros.view',
            'configuracion.monedas.view', 'configuracion.metodos_pago.view',
            'configuracion.unidades_medida.view', 'configuracion.cajas.view',
            'etapas.view', 'configuracion.tipos_produccion.view',
        ];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $cashRegister = Cajas::create([
            'nombre' => 'Caja móvil '.Str::upper(Str::random(6)),
            'estado' => true,
        ]);
        $user = User::create([
            'name' => 'Usuario móvil',
            'email' => 'mobile-'.Str::lower(Str::random(10)).'@test.local',
            'password' => Hash::make('password'),
            'caja_id' => $cashRegister->id,
        ]);
        $this->user = $user;
        $user->syncPermissions($permissions);
        $this->token = $user->createToken('KMP test')->plainTextToken;
    }

    public function test_endpoints_de_lectura_movil_devuelven_json_con_paginacion(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->token];

        $this->getJson('/api/v1/orders?q=__MISSING_ORDER__&per_page=10', $headers)
            ->assertOk()->assertJsonPath('meta.per_page', 10)->assertJsonPath('data', []);
        $this->getJson('/api/v1/cash/summary', $headers)
            ->assertOk()->assertJsonStructure(['data' => ['cash_register', 'balances']]);
        $this->getJson('/api/v1/cash/movements?fecha_desde=2100-01-01', $headers)
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/cash/collections?fecha_desde=2100-01-01', $headers)
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/cash/collections/999999999', $headers)->assertNotFound();
        $this->getJson('/api/v1/inventory/products?q=__MISSING_PRODUCT__', $headers)
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/inventory/movements?fecha_desde=2100-01-01', $headers)
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/quotes?q=__MISSING_QUOTE__', $headers)
            ->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_descarga_offline_incluye_datos_y_paginacion_de_ordenes(): void
    {
        $this->withToken($this->token)->getJson('/api/v1/offline/snapshot?page=1&per_page=10')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['clients', 'employees', 'orders', 'catalogs' => ['units', 'stages', 'employees', 'payment_types', 'equipment']],
                'meta' => ['orders' => ['current_page', 'last_page', 'total']],
            ]);
    }

    public function test_upload_sin_operaciones_finaliza_y_permite_limpiar_la_copia_local(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/offline/upload', ['operations' => []])
            ->assertOk()
            ->assertJsonPath('data.uploaded', 0)
            ->assertJsonPath('data.id_map', []);
    }

    public function test_upload_offline_reutiliza_el_resultado_si_se_reintenta(): void
    {
        $client = Cliente::query()->where('estado', true)->firstOrFail();
        $unit = UnidadMedida::query()->where('estado', true)->firstOrFail();
        $operation = [
            'local_id' => '-offline-order-77',
            'operation_key' => 'offline-test-order-77',
            'type' => 'order.create',
            'payload' => [
                'cliente_id' => $client->id,
                'fecha' => '2026-10-11',
                'peso_mineral' => 12.5,
                'unidad_peso_id' => $unit->id,
                'descripcion' => 'Orden enviada desde la cola offline',
            ],
        ];

        $first = $this->withToken($this->token)->postJson('/api/v1/offline/upload', ['operations' => [$operation]])
            ->assertOk()->assertJsonPath('data.uploaded', 1)->json('data.id_map.-offline-order-77');
        $this->assertNotNull($first);

        $this->withToken($this->token)->postJson('/api/v1/offline/upload', ['operations' => [$operation]])
            ->assertOk()
            ->assertJsonPath('data.uploaded', 1)
            ->assertJsonPath('data.id_map.-offline-order-77', $first);

        $this->assertSame(1, OrdenesTrabajo::query()->where('descripcion', 'Orden enviada desde la cola offline')->count());
    }

    public function test_upload_resuelve_dependencias_entre_orden_y_proceso_locales(): void
    {
        $client = Cliente::query()->where('estado', true)->firstOrFail();
        $unit = UnidadMedida::query()->where('estado', true)->firstOrFail();
        $stage = Etapa::query()->where('estado', true)->firstOrFail();
        $operations = [
            [
                'local_id' => '-200', 'operation_key' => 'offline-dependency-order-200', 'type' => 'order.create',
                'payload' => [
                    'cliente_id' => $client->id, 'fecha' => '2026-10-11', 'peso_mineral' => 5,
                    'unidad_peso_id' => $unit->id, 'descripcion' => 'Orden dependiente de proceso local',
                ],
            ],
            [
                'local_id' => '-201', 'operation_key' => 'offline-dependency-process-201', 'type' => 'process.create',
                'payload' => [
                    'order_id' => -200, 'etapa_id' => $stage->id, 'fecha_inicio' => '2026-10-11',
                    'observaciones' => 'Proceso agregado sin señal',
                ],
            ],
        ];

        $response = $this->withToken($this->token)->postJson('/api/v1/offline/upload', ['operations' => $operations])
            ->assertOk()->assertJsonPath('data.uploaded', 2);
        $orderId = $response->json('data.id_map.-200');
        $processId = $response->json('data.id_map.-201');
        $this->assertNotNull($orderId);
        $this->assertNotNull($processId);
        $this->assertDatabaseHas('procesos_orden', [
            'id' => $processId,
            'orden_trabajo_id' => $orderId,
            'observaciones' => 'Proceso agregado sin señal',
        ]);
    }

    public function test_upload_invalido_no_deja_una_orden_parcialmente_creada(): void
    {
        $client = Cliente::query()->where('estado', true)->firstOrFail();
        $unit = UnidadMedida::query()->where('estado', true)->firstOrFail();
        $operations = [
            [
                'local_id' => '-300', 'operation_key' => 'offline-rollback-order-300', 'type' => 'order.create',
                'payload' => [
                    'cliente_id' => $client->id, 'fecha' => '2026-10-11', 'peso_mineral' => 5,
                    'unidad_peso_id' => $unit->id, 'descripcion' => 'No debe quedar por rollback',
                ],
            ],
            [
                'local_id' => '-301', 'operation_key' => 'offline-rollback-process-301', 'type' => 'process.create',
                'payload' => ['order_id' => -300, 'etapa_id' => 999999],
            ],
        ];

        $this->withToken($this->token)->postJson('/api/v1/offline/upload', ['operations' => $operations])
            ->assertUnprocessable();
        $this->assertDatabaseMissing('ordenes_trabajo', ['descripcion' => 'No debe quedar por rollback']);
        $this->assertDatabaseMissing('mobile_sync_operations', ['operation_key' => 'offline-rollback-order-300']);
    }

    public function test_api_de_costos_de_proceso_reusa_validaciones_y_calcula_totales(): void
    {
        foreach (['movimientos_costo.view', 'movimientos_costo.create', 'movimientos_costo.edit', 'movimientos_costo.delete'] as $permission) {
            $this->user->givePermissionTo($permission);
        }
        $order = OrdenesTrabajo::query()->whereNotIn('estado', [0, 3])->firstOrFail();
        $stage = Etapa::query()->where('estado', true)->firstOrFail();
        $process = new ProcesosOrden();
        $process->orden_trabajo_id = $order->id;
        $process->etapa_id = $stage->id;
        $process->codigo = 'M-'.Str::upper(Str::random(8));
        $process->fecha_inicio = '2026-01-01 08:00:00';
        $process->fecha_fin = '2099-12-31 17:00:00';
        $process->peso_entrada = 1;
        $process->peso_salida = 1;
        $process->estado = 1;
        $process->save();
        $category = CategoriasCosto::paraRegistrar()->firstOrFail();
        $currency = Moneda::query()->where('estado', true)->where('es_moneda_base', true)->firstOrFail();
        $path = "/api/v1/orders/{$order->id}/processes/{$process->id}/costs";

        $this->withToken($this->token)->getJson("/api/v1/orders/{$order->id}/processes/{$process->id}/cost-options")
            ->assertOk()->assertJsonStructure(['data' => ['categories', 'currencies', 'date']]);

        $costId = $this->withToken($this->token)->postJson($path, [
            'categoria_costo_id' => $category->id,
            'fecha' => '2026-10-11',
            'descripcion' => 'Energía de prueba móvil',
            'cantidad' => 2,
            'costo_unitario' => 4.25,
            'moneda_id' => $currency->id,
        ])->assertCreated()->assertJsonPath('data.total', 8.5)->assertJsonPath('data.total_nio', 8.5)->json('data.id');

        $this->withToken($this->token)->getJson($path)
            ->assertOk()->assertJsonPath('data.costs.0.id', $costId)->assertJsonPath('data.costs.0.category', $category->nombre);

        $this->withToken($this->token)->putJson($path.'/'.$costId, [
            'categoria_costo_id' => $category->id,
            'fecha' => '2026-10-11',
            'descripcion' => 'Energía corregida',
            'cantidad' => 3,
            'costo_unitario' => 4.25,
            'moneda_id' => $currency->id,
        ])->assertOk()->assertJsonPath('data.total', 12.75);

        $this->withToken($this->token)->deleteJson($path.'/'.$costId)->assertOk();
        $this->assertSoftDeleted('movimientos_costos', ['id' => $costId]);
    }

    public function test_api_movil_registra_costos_generales_y_sincroniza_offline(): void
    {
        foreach (['movimientos_costo.view', 'movimientos_costo.create', 'movimientos_costo.edit', 'movimientos_costo.delete'] as $permission) {
            $this->user->givePermissionTo($permission);
        }
        $order = OrdenesTrabajo::query()->whereNotIn('estado', [0, 3])->firstOrFail();
        $category = CategoriasCosto::paraRegistrar()->firstOrFail();
        $currency = Moneda::query()->where('estado', true)->where('es_moneda_base', true)->firstOrFail();
        $url = '/api/v1/orders/'.$order->id;
        $this->withToken($this->token)->getJson($url.'/cost-options')
            ->assertOk()->assertJsonStructure(['data' => ['categories', 'currencies', 'date']]);

        $created = $this->withToken($this->token)->postJson($url.'/costs', [
            'categoria_costo_id' => $category->id,
            'fecha' => '2026-10-12',
            'descripcion' => 'Alquiler de prueba móvil',
            'cantidad' => 2,
            'costo_unitario' => 15.25,
            'moneda_id' => $currency->id,
        ])->assertCreated()->assertJsonPath('data.total', 30.5)->assertJsonPath('data.total_nio', 30.5);
        $costId = $created->json('data.id');
        $this->withToken($this->token)->getJson($url.'/costs')
            ->assertOk()->assertJsonPath('data.costs.0.id', $costId)
            ->assertJsonPath('data.costs.0.category_id', $category->id);
        $this->withToken($this->token)->putJson($url.'/costs/'.$costId, [
            'categoria_costo_id' => $category->id,
            'fecha' => '2026-10-12',
            'descripcion' => 'Alquiler corregido',
            'cantidad' => 3,
            'costo_unitario' => 10,
            'moneda_id' => $currency->id,
        ])->assertOk()->assertJsonPath('data.total', 30);
        $this->withToken($this->token)->deleteJson($url.'/costs/'.$costId)->assertOk();
        $this->assertSoftDeleted('movimientos_costos', ['id' => $costId]);

        $localId = '-'.random_int(10000, 99999);
        $key = 'offline-order-cost-'.Str::lower(Str::random(10));
        $operations = [
            [
                'local_id' => $localId, 'operation_key' => $key.'-create', 'type' => 'order_cost.create',
                'payload' => [
                    'order_id' => $order->id, 'categoria_costo_id' => $category->id, 'fecha' => '2026-10-14',
                    'descripcion' => 'Transporte offline', 'cantidad' => 2, 'costo_unitario' => 8, 'moneda_id' => $currency->id,
                ],
            ],
            [
                'local_id' => $localId, 'operation_key' => $key.'-update', 'type' => 'order_cost.update',
                'payload' => [
                    'order_id' => $order->id, 'cost_id' => $localId, 'categoria_costo_id' => $category->id,
                    'fecha' => '2026-10-14', 'descripcion' => 'Transporte corregido', 'cantidad' => 3,
                    'costo_unitario' => 8, 'moneda_id' => $currency->id,
                ],
            ],
            [
                'local_id' => $localId, 'operation_key' => $key.'-delete', 'type' => 'order_cost.delete',
                'payload' => ['order_id' => $order->id, 'cost_id' => $localId],
            ],
        ];
        $uploaded = $this->withToken($this->token)->postJson('/api/v1/offline/upload', ['operations' => $operations])
            ->assertOk()->assertJsonPath('data.uploaded', 3);
        $offlineCostId = $uploaded->json('data.id_map.'.$localId);
        $this->assertNotNull($offlineCostId);
        $this->assertSoftDeleted('movimientos_costos', ['id' => $offlineCostId]);
    }

    public function test_upload_offline_resuelve_creacion_edicion_y_eliminacion_de_costos(): void
    {
        foreach (['movimientos_costo.view', 'movimientos_costo.create', 'movimientos_costo.edit', 'movimientos_costo.delete'] as $permission) {
            $this->user->givePermissionTo($permission);
        }
        $order = OrdenesTrabajo::query()->whereNotIn('estado', [0, 3])->firstOrFail();
        $stage = Etapa::query()->where('estado', true)->firstOrFail();
        $process = new ProcesosOrden();
        $process->orden_trabajo_id = $order->id;
        $process->etapa_id = $stage->id;
        $process->codigo = 'OFF-'.Str::upper(Str::random(8));
        $process->fecha_inicio = '2026-01-01 08:00:00';
        $process->fecha_fin = '2099-12-31 17:00:00';
        $process->estado = 1;
        $process->save();
        $category = CategoriasCosto::paraRegistrar()->firstOrFail();
        $currency = Moneda::query()->where('estado', true)->where('es_moneda_base', true)->firstOrFail();
        $basePayload = [
            'order_id' => $order->id, 'process_id' => $process->id,
            'categoria_costo_id' => $category->id, 'fecha' => '2026-10-11',
            'descripcion' => 'Consumo offline', 'cantidad' => 2,
            'costo_unitario' => 5, 'moneda_id' => $currency->id,
        ];
        $operations = [
            ['local_id' => '-500', 'operation_key' => 'offline-cost-create-500', 'type' => 'cost.create', 'payload' => $basePayload],
            ['local_id' => '-501', 'operation_key' => 'offline-cost-update-501', 'type' => 'cost.update',
                'payload' => [...$basePayload, 'cost_id' => -500, 'cantidad' => 3]],
            ['local_id' => '-502', 'operation_key' => 'offline-cost-delete-502', 'type' => 'cost.delete',
                'payload' => ['order_id' => $order->id, 'process_id' => $process->id, 'cost_id' => -500]],
        ];

        $this->withToken($this->token)->postJson('/api/v1/offline/upload', ['operations' => $operations])
            ->assertOk()->assertJsonPath('data.uploaded', 3);
        $cost = MovimientosCosto::withTrashed()->where('descripcion', 'Consumo offline')->firstOrFail();
        $this->assertSame(15.0, (float) $cost->costo_total);
        $this->assertSoftDeleted('movimientos_costos', ['id' => $cost->id]);
    }

    public function test_api_de_consumo_descuenta_promedio_y_eliminarlo_devuelve_existencias(): void
    {
        $this->user->givePermissionTo('movimientos_inventario.create', 'movimientos_inventario.delete');
        $order = OrdenesTrabajo::query()->whereNotIn('estado', [0, 3])->firstOrFail();
        $stage = Etapa::query()->where('estado', true)->firstOrFail();
        $process = new ProcesosOrden();
        $process->orden_trabajo_id = $order->id;
        $process->etapa_id = $stage->id;
        $process->codigo = 'INV-'.Str::upper(Str::random(8));
        $process->fecha_inicio = '2026-01-01 08:00:00';
        $process->estado = 1;
        $process->save();
        $product = Producto::create([
            Producto::CODIGO => 'MAT-'.Str::upper(Str::random(8)),
            Producto::NOMBRE => 'Cemento API móvil',
            Producto::UNIDAD_MEDIDA => 'kg',
            Producto::STOCK_MINIMO => 0,
            Producto::ESTADO => true,
        ]);
        $baseCurrency = Moneda::query()->where('es_moneda_base', true)->firstOrFail();
        app(\App\Services\InventarioService::class)->registrar([
            'producto_id' => $product->id,
            'tipo' => MovimientosInventario::TIPO_ENTRADA,
            'fecha' => '2026-10-01',
            'cantidad' => 10,
            'costo_unitario' => 4.25,
            'moneda_id' => $baseCurrency->id,
        ]);
        $path = "/api/v1/orders/{$order->id}/processes/{$process->id}/materials";

        $this->withToken($this->token)->getJson("/api/v1/orders/{$order->id}/processes/{$process->id}/material-options")
            ->assertOk()->assertJsonFragment(['id' => $product->id, 'name' => 'Cemento API móvil', 'stock' => 10]);
        $movementId = $this->withToken($this->token)->postJson($path, [
            'producto_id' => $product->id,
            'fecha' => '2026-10-12',
            'cantidad' => 3,
            'costo_unitario' => 999,
        ])->assertCreated()->assertJsonPath('data.unit_cost', 4.25)->assertJsonPath('data.total_nio', 12.75)->json('data.id');

        $this->assertSame(7.0, (float) InventarioProducto::query()->where('producto_id', $product->id)->value('cantidad_actual'));
        $this->withToken($this->token)->getJson($path)
            ->assertOk()->assertJsonPath('data.materials.0.id', $movementId)->assertJsonPath('data.materials.0.quantity', 3);
        $this->withToken($this->token)->deleteJson($path.'/'.$movementId)->assertOk();
        $this->assertSoftDeleted('movimientos_inventario', ['id' => $movementId]);
        $this->assertSame(10.0, (float) InventarioProducto::query()->where('producto_id', $product->id)->value('cantidad_actual'));
    }

    public function test_upload_offline_sincroniza_el_consumo_y_reversion_de_materia_prima(): void
    {
        $this->user->givePermissionTo('movimientos_inventario.create', 'movimientos_inventario.delete');
        $order = OrdenesTrabajo::query()->whereNotIn('estado', [0, 3])->firstOrFail();
        $stage = Etapa::query()->where('estado', true)->firstOrFail();
        $process = new ProcesosOrden();
        $process->orden_trabajo_id = $order->id;
        $process->etapa_id = $stage->id;
        $process->codigo = 'SYNC-'.Str::upper(Str::random(8));
        $process->fecha_inicio = '2026-01-01 08:00:00';
        $process->estado = 1;
        $process->save();
        $product = Producto::create([
            Producto::CODIGO => 'MAT-'.Str::upper(Str::random(8)),
            Producto::NOMBRE => 'Reactivo sync móvil',
            Producto::UNIDAD_MEDIDA => 'L',
            Producto::STOCK_MINIMO => 0,
            Producto::ESTADO => true,
        ]);
        $baseCurrency = Moneda::query()->where('es_moneda_base', true)->firstOrFail();
        app(\App\Services\InventarioService::class)->registrar([
            'producto_id' => $product->id,
            'tipo' => MovimientosInventario::TIPO_ENTRADA,
            'fecha' => '2026-10-01',
            'cantidad' => 20,
            'costo_unitario' => 2,
            'moneda_id' => $baseCurrency->id,
        ]);
        $operations = [
            [
                'local_id' => '-700', 'operation_key' => 'offline-material-consume-700', 'type' => 'material.consume',
                'payload' => ['order_id' => $order->id, 'process_id' => $process->id, 'producto_id' => $product->id,
                    'fecha' => '2026-10-12', 'cantidad' => 5, 'tipo' => 'entrada', 'costo_unitario' => 999],
            ],
            [
                'local_id' => '-701', 'operation_key' => 'offline-material-delete-701', 'type' => 'material.delete',
                'payload' => ['order_id' => $order->id, 'process_id' => $process->id, 'movement_id' => -700],
            ],
        ];

        $response = $this->withToken($this->token)->postJson('/api/v1/offline/upload', ['operations' => $operations])
            ->assertOk()->assertJsonPath('data.uploaded', 2);
        $movementId = $response->json('data.id_map.-700');
        $this->assertNotNull($movementId);
        $this->assertSoftDeleted('movimientos_inventario', ['id' => $movementId]);
        $this->assertSame(20.0, (float) InventarioProducto::query()->where('producto_id', $product->id)->value('cantidad_actual'));
    }

    public function test_lista_clientes_devuelve_datos_paginados_y_requiere_permiso(): void
    {
        $primero = Cliente::create([
            'nombre' => 'API Cliente A '.Str::upper(Str::random(5)),
            'telefono' => '8888-1000',
            'direccion' => 'Managua',
            'estado' => true,
        ]);
        Cliente::create([
            'nombre' => 'API Cliente B '.Str::upper(Str::random(5)),
            'telefono' => '8888-2000',
            'direccion' => 'Leon',
            'estado' => false,
        ]);

        $this->withToken($this->token)->getJson('/api/v1/clients?q=API%20Cliente&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $primero->id)
            ->assertJsonPath('data.0.name', $primero->nombre)
            ->assertJsonPath('data.0.phone', $primero->telefono)
            ->assertJsonPath('data.0.address', $primero->direccion)
            ->assertJsonPath('data.0.is_active', true);

    }

    public function test_api_de_clientes_requiere_permiso_del_modulo(): void
    {
        $cashRegister = Cajas::create([
            'nombre' => 'Caja cliente sin permiso '.Str::upper(Str::random(5)),
            'estado' => true,
        ]);
        $user = User::create([
            'name' => 'Usuario sin permiso clientes',
            'email' => 'mobile-client-denied-'.Str::lower(Str::random(8)).'@test.local',
            'password' => Hash::make('password'),
            'caja_id' => $cashRegister->id,
        ]);

        $this->withToken($user->createToken('KMP sin permiso clientes')->plainTextToken)
            ->getJson('/api/v1/clients')->assertForbidden();
    }

    public function test_dashboard_y_catalogos_respetan_acceso_autenticado(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->token];

        $this->getJson('/api/v1/app/dashboard?periodo=mes', $headers)
            ->assertOk()->assertJsonPath('data.period.key', 'mes');
        $this->getJson('/api/v1/app/catalogs', $headers)
            ->assertOk()->assertJsonStructure(['data' => ['currencies', 'payment_methods', 'units', 'stages', 'products', 'cash_register']]);
    }

    public function test_apis_de_ordenes_requieren_permiso_del_modulo(): void
    {
        $cashRegister = Cajas::create([
            'nombre' => 'Caja sin permiso '.Str::upper(Str::random(6)),
            'estado' => true,
        ]);
        $user = User::create([
            'name' => 'Usuario sin permiso móvil',
            'email' => 'mobile-denied-'.Str::lower(Str::random(8)).'@test.local',
            'password' => Hash::make('password'),
            'caja_id' => $cashRegister->id,
        ]);
        $token = $user->createToken('KMP sin permiso')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/orders')->assertForbidden();
    }

    public function test_detalle_de_orden_no_expone_modulos_sin_permiso(): void
    {
        Permission::findOrCreate('ordenes_trabajo.view', 'web');
        $cliente = Cliente::create([
            'nombre' => 'Cliente API '.Str::upper(Str::random(6)),
            'estado' => true,
        ]);
        $order = OrdenesTrabajo::create([
            'codigo' => 'API-'.Str::upper(Str::random(8)),
            'cliente_id' => $cliente->id,
            'fecha' => today(),
            'descripcion' => 'Orden de prueba API',
            'peso_mineral' => 12.5,
            'unidad_peso' => 'kg',
            'estado' => OrdenesTrabajo::ESTADO_PENDIENTE,
        ]);
        $cashRegister = Cajas::create([
            'nombre' => 'Caja API limitada '.Str::upper(Str::random(6)),
            'estado' => true,
        ]);
        $limitedUser = User::create([
            'name' => 'Usuario solo ordenes',
            'email' => 'mobile-order-'.Str::lower(Str::random(8)).'@test.local',
            'password' => Hash::make('password'),
            'caja_id' => $cashRegister->id,
        ]);
        $limitedUser->givePermissionTo('ordenes_trabajo.view');
        $token = $limitedUser->createToken('KMP order read')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.code', $order->codigo)
            ->assertJsonMissingPath('data.costs')
            ->assertJsonMissingPath('data.incomes')
            ->assertJsonMissingPath('data.agreements')
            ->assertJsonMissingPath('data.liquidations')
            ->assertJsonMissingPath('data.processes');
    }

    public function test_detalle_de_orden_incluye_acuerdos_y_liquidaciones_con_permisos(): void
    {
        $permissions = [
            'ordenes_trabajo.view', 'acuerdos_orden.view', 'liquidaciones.view', 'ingresos.view',
            'movimientos_costo.view', 'trabajos_empleado.view', 'proceso_equipo.view', 'movimientos_inventario.view',
        ];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $client = Cliente::create([
            'nombre' => 'Cliente acuerdos '.Str::upper(Str::random(6)),
            'estado' => true,
        ]);
        $order = OrdenesTrabajo::create([
            'codigo' => 'API-'.Str::upper(Str::random(8)),
            'cliente_id' => $client->id,
            'fecha' => today(),
            'descripcion' => 'Orden API con acuerdos',
            'peso_mineral' => 12.5,
            'unidad_peso' => 'kg',
            'estado' => OrdenesTrabajo::ESTADO_PENDIENTE,
        ]);
        $currency = Moneda::create([
            'codigo' => Str::upper(Str::random(3)),
            'nombre' => 'Moneda de prueba',
            'estado' => true,
        ]);
        $participationType = TiposParticipacion::create([
            'nombre' => 'Participación API '.Str::upper(Str::random(5)),
            'estado' => true,
        ]);
        AcuerdosOrden::create([
            'orden_trabajo_id' => $order->id,
            'tipo_participacion_id' => $participationType->id,
            'porcentaje_cliente' => 60,
            'porcentaje_empresa' => 40,
            'moneda_id' => $currency->id,
            'estado' => true,
        ]);
        Liquidacione::create([
            'orden_trabajo_id' => $order->id,
            'fecha' => today(),
            'gramos_recuperados' => 10,
            'gramos_cliente' => 6,
            'gramos_empresa' => 4,
            'porcentaje_cliente' => 60,
            'porcentaje_empresa' => 40,
            'observaciones' => 'Lote de prueba API',
        ]);

        $cashRegister = Cajas::create([
            'nombre' => 'Caja detalle acuerdos '.Str::upper(Str::random(6)),
            'estado' => true,
        ]);
        $user = User::create([
            'name' => 'Usuario detalle acuerdos',
            'email' => 'mobile-agreements-'.Str::lower(Str::random(8)).'@test.local',
            'password' => Hash::make('password'),
            'caja_id' => $cashRegister->id,
        ]);
        $user->syncPermissions($permissions);
        $token = $user->createToken('KMP order agreements read')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.agreements.0.participation_type', $participationType->nombre)
            ->assertJsonPath('data.agreements.0.modality', 'porcentaje')
            ->assertJsonPath('data.liquidations.0.recovered_grams', 10)
            ->assertJsonPath('data.liquidations.0.observations', 'Lote de prueba API')
            ->assertJsonPath('data.financial_summary.moneda', Moneda::base()->codigo)
            ->assertJsonPath('data.financial_summary.oro_empresa_gramos', 4)
            ->assertJsonStructure(['data' => ['financial_summary' => [
                'ingreso_real_conocido_nio', 'costo_total_nio', 'resultado_real_nio',
                'resultado_proyectado_nio', 'datos_incompletos',
            ]]]);
    }

    public function test_api_movil_crea_actualiza_y_elimina_acuerdos_de_la_orden(): void
    {
        $permissions = ['acuerdos_orden.view', 'acuerdos_orden.create', 'acuerdos_orden.edit', 'acuerdos_orden.delete'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $this->user->givePermissionTo($permissions);

        $order = OrdenesTrabajo::query()->whereNotIn('estado', [0, 3])->first();
        $currency = Moneda::query()->where('estado', true)->where('es_moneda_base', true)->first();
        if (! $order || ! $currency) {
            $this->markTestSkipped('Hace falta una orden abierta y una moneda base activa para probar acuerdos móviles.');
        }
        $type = TiposParticipacion::create(['nombre' => 'Tipo API móvil '.Str::upper(Str::random(6)), 'estado' => true]);
        $url = '/api/v1/orders/'.$order->id.'/agreements';

        $created = $this->withToken($this->token)->postJson($url, [
            'participation_type_id' => $type->id,
            'client_percentage' => 65,
            'company_percentage' => 35,
            'currency_id' => $currency->id,
            'active' => true,
            'observations' => 'Acuerdo desde móvil',
        ])->assertCreated()->assertJsonPath('data.participation_type_id', $type->id)
            ->assertJsonPath('data.modality', 'porcentaje');
        $agreementId = $created->json('data.id');

        $this->withToken($this->token)->putJson($url.'/'.$agreementId, [
            'participation_type_id' => $type->id,
            'service_fee' => 125.5,
            'currency_id' => $currency->id,
            'active' => false,
        ])->assertOk()->assertJsonPath('data.modality', 'tarifa')->assertJsonPath('data.active', false);

        $this->withToken($this->token)->deleteJson($url.'/'.$agreementId)->assertOk();
        $this->assertSoftDeleted('acuerdos_orden', ['id' => $agreementId]);

        $localId = '-'.random_int(10000, 99999);
        $key = 'offline-agreement-'.Str::lower(Str::random(10));
        $operations = [
            [
                'local_id' => $localId,
                'operation_key' => $key.'-create',
                'type' => 'agreement.create',
                'payload' => [
                    'order_id' => $order->id,
                    'participation_type_id' => $type->id,
                    'client_percentage' => 60,
                    'company_percentage' => 40,
                    'currency_id' => $currency->id,
                    'active' => true,
                ],
            ],
            [
                'local_id' => $localId,
                'operation_key' => $key.'-update',
                'type' => 'agreement.update',
                'payload' => [
                    'order_id' => $order->id,
                    'agreement_id' => $localId,
                    'participation_type_id' => $type->id,
                    'service_fee' => 75,
                    'currency_id' => $currency->id,
                    'active' => true,
                ],
            ],
            [
                'local_id' => $localId,
                'operation_key' => $key.'-delete',
                'type' => 'agreement.delete',
                'payload' => ['order_id' => $order->id, 'agreement_id' => $localId],
            ],
        ];
        $upload = $this->withToken($this->token)->postJson('/api/v1/offline/upload', ['operations' => $operations])
            ->assertOk()->assertJsonPath('data.uploaded', 3);
        $offlineAgreementId = $upload->json('data.id_map.'.$localId);
        $this->assertNotNull($offlineAgreementId);
        $this->assertSoftDeleted('acuerdos_orden', ['id' => $offlineAgreementId]);
    }

    public function test_api_movil_liquida_lotes_y_sincroniza_creacion_edicion_y_eliminacion_offline(): void
    {
        $permissions = ['liquidaciones.view', 'liquidaciones.create', 'liquidaciones.edit', 'liquidaciones.delete'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $this->user->givePermissionTo($permissions);
        $order = OrdenesTrabajo::query()->where('estado', '<>', 0)->first();
        $currency = Moneda::query()->where('estado', true)->where('es_moneda_base', true)->first();
        if (! $order || ! $currency) {
            $this->markTestSkipped('Hace falta una orden y una moneda base activas para probar liquidaciones móviles.');
        }
        $type = TiposParticipacion::create(['nombre' => 'Tipo de liquidación móvil '.Str::upper(Str::random(6)), 'estado' => true]);
        $agreement = AcuerdosOrden::create([
            'orden_trabajo_id' => $order->id,
            'tipo_participacion_id' => $type->id,
            'porcentaje_cliente' => 65,
            'porcentaje_empresa' => 35,
            'moneda_id' => $currency->id,
            'estado' => true,
        ]);
        $orderUrl = '/api/v1/orders/'.$order->id;

        $this->withToken($this->token)->getJson($orderUrl.'/liquidation-options')
            ->assertOk()->assertJsonPath('data.agreements.0.id', $agreement->id);
        $this->withToken($this->token)->getJson($orderUrl)
            ->assertOk()->assertJsonPath('data.liquidation_agreements.0.id', $agreement->id);
        $created = $this->withToken($this->token)->postJson($orderUrl.'/liquidations', [
            'date' => '2026-10-10',
            'recovered_grams' => 1.2345,
            'agreement_id' => $agreement->id,
            'observations' => 'Lote móvil online',
        ])->assertCreated()->assertJsonPath('data.client_grams', 0.8024)->assertJsonPath('data.company_grams', 0.4321);
        $liquidationId = $created->json('data.id');

        $this->withToken($this->token)->putJson($orderUrl.'/liquidations/'.$liquidationId, [
            'date' => '2026-10-11', 'recovered_grams' => 2, 'observations' => 'Lote corregido',
        ])->assertOk()->assertJsonPath('data.client_grams', 1.3)->assertJsonPath('data.company_grams', 0.7);

        $this->withToken($this->token)->deleteJson($orderUrl.'/liquidations/'.$liquidationId)->assertOk();
        $this->assertSoftDeleted('liquidaciones', ['id' => $liquidationId]);

        $localId = '-'.random_int(10000, 99999);
        $operationPrefix = 'offline-liquidation-'.Str::lower(Str::random(10));
        $operations = [
            [
                'local_id' => $localId,
                'operation_key' => $operationPrefix.'-create',
                'type' => 'liquidation.create',
                'payload' => [
                    'order_id' => $order->id, 'date' => '2026-10-12', 'recovered_grams' => 1.2345,
                    'agreement_id' => $agreement->id, 'observations' => 'Lote móvil offline',
                ],
            ],
            [
                'local_id' => $localId,
                'operation_key' => $operationPrefix.'-update',
                'type' => 'liquidation.update',
                'payload' => [
                    'order_id' => $order->id, 'liquidation_id' => $localId, 'date' => '2026-10-13',
                    'recovered_grams' => 2, 'observations' => 'Lote offline corregido',
                ],
            ],
            [
                'local_id' => $localId,
                'operation_key' => $operationPrefix.'-delete',
                'type' => 'liquidation.delete',
                'payload' => ['order_id' => $order->id, 'liquidation_id' => $localId],
            ],
        ];
        $uploaded = $this->withToken($this->token)->postJson('/api/v1/offline/upload', ['operations' => $operations])
            ->assertOk()->assertJsonPath('data.uploaded', 3);
        $offlineLiquidationId = $uploaded->json('data.id_map.'.$localId);
        $this->assertNotNull($offlineLiquidationId);
        $this->assertSoftDeleted('liquidaciones', ['id' => $offlineLiquidationId]);
    }

    public function test_api_movil_registra_ingresos_y_sincroniza_creacion_edicion_y_eliminacion_offline(): void
    {
        $permissions = ['ingresos.view', 'ingresos.create', 'ingresos.edit', 'ingresos.delete'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $this->user->givePermissionTo($permissions);
        $order = OrdenesTrabajo::query()->whereNotIn('estado', [0, 3])->first();
        $currency = Moneda::query()->where('estado', true)->where('es_moneda_base', true)->first();
        $unit = UnidadMedida::query()->where('estado', true)->first();
        if (! $order || ! $currency || ! $unit) {
            $this->markTestSkipped('Hace falta una orden abierta, moneda base y unidad activa para probar ingresos móviles.');
        }
        $type = TiposIngreso::create(['nombre' => 'Ingreso API móvil '.Str::upper(Str::random(6)), 'estado' => true]);
        $url = '/api/v1/orders/'.$order->id;
        $this->withToken($this->token)->getJson($url.'/income-options')
            ->assertOk()->assertJsonFragment(['id' => $type->id, 'name' => $type->nombre]);

        $created = $this->withToken($this->token)->postJson($url.'/incomes', [
            'type_id' => $type->id, 'date' => '2026-10-12', 'description' => 'Servicio móvil',
            'quantity' => 2, 'unit_id' => $unit->id, 'unit_price' => 125.50,
            'currency_id' => $currency->id, 'observations' => 'Registro online',
        ])->assertCreated()->assertJsonPath('data.total', 251)->assertJsonPath('data.total_nio', 251);
        $incomeId = $created->json('data.id');
        $this->withToken($this->token)->getJson($url)
            ->assertOk()->assertJsonFragment(['type_id' => $type->id, 'unit_id' => $unit->id]);
        $this->withToken($this->token)->putJson($url.'/incomes/'.$incomeId, [
            'type_id' => $type->id, 'date' => '2026-10-13', 'quantity' => 3,
            'unit_id' => $unit->id, 'unit_price' => 100, 'currency_id' => $currency->id,
        ])->assertOk()->assertJsonPath('data.total', 300);
        $this->withToken($this->token)->deleteJson($url.'/incomes/'.$incomeId)->assertOk();
        $this->assertSoftDeleted('ingresos', ['id' => $incomeId]);

        $localId = '-'.random_int(10000, 99999);
        $key = 'offline-income-'.Str::lower(Str::random(10));
        $operations = [
            [
                'local_id' => $localId, 'operation_key' => $key.'-create', 'type' => 'income.create',
                'payload' => [
                    'order_id' => $order->id, 'type_id' => $type->id, 'date' => '2026-10-14',
                    'quantity' => 1.5, 'unit_id' => $unit->id, 'unit_price' => 80, 'currency_id' => $currency->id,
                ],
            ],
            [
                'local_id' => $localId, 'operation_key' => $key.'-update', 'type' => 'income.update',
                'payload' => [
                    'order_id' => $order->id, 'income_id' => $localId, 'type_id' => $type->id,
                    'date' => '2026-10-15', 'quantity' => 2, 'unit_id' => $unit->id,
                    'unit_price' => 90, 'currency_id' => $currency->id,
                ],
            ],
            [
                'local_id' => $localId, 'operation_key' => $key.'-delete', 'type' => 'income.delete',
                'payload' => ['order_id' => $order->id, 'income_id' => $localId],
            ],
        ];
        $uploaded = $this->withToken($this->token)->postJson('/api/v1/offline/upload', ['operations' => $operations])
            ->assertOk()->assertJsonPath('data.uploaded', 3);
        $offlineIncomeId = $uploaded->json('data.id_map.'.$localId);
        $this->assertNotNull($offlineIncomeId);
        $this->assertSoftDeleted('ingresos', ['id' => $offlineIncomeId]);
    }
}

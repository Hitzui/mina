<?php

namespace Tests\Feature\Api\V1;

use App\Models\AcuerdosOrden;
use App\Models\Cajas;
use App\Models\Cliente;
use App\Models\Liquidacione;
use App\Models\Moneda;
use App\Models\OrdenesTrabajo;
use App\Models\TiposParticipacion;
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

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = [
            'ordenes_trabajo.view', 'clientes.view', 'movimientos_caja.view', 'productos.view',
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
        $permissions = ['ordenes_trabajo.view', 'acuerdos_orden.view', 'liquidaciones.view'];
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
            ->assertJsonPath('data.liquidations.0.observations', 'Lote de prueba API');
    }
}

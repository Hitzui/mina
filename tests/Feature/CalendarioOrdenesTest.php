<?php

namespace Tests\Feature;

use App\Models\OrdenesTrabajo;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Calendario de ordenes de trabajo.
 *
 * Venia del theme con la lista de eventos vacia y la fecha clavada en el
 * dia 7 del mes: no mostraba ninguna orden. Aqui se comprueba que el
 * calendario trae las ordenes del tramo que se le pide y que al pulsar
 * una se abre el resumen con el enlace a su ficha.
 */
class CalendarioOrdenesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'cal-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

    private function crearOrden(string $fecha, array $extra = []): OrdenesTrabajo
    {
        $original = OrdenesTrabajo::firstOrFail();

        $datos = [
            'codigo' => 'OT-CAL-' . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
            'cliente_id' => $original->cliente_id,
            'fecha' => $fecha,
            'descripcion' => 'Orden de prueba del calendario',
            'peso_mineral' => 12.5,
            'unidad_peso' => 'toneladas',
            'estado' => OrdenesTrabajo::ESTADO_PENDIENTE,
        ];

        foreach ($extra as $k => $v) {
            $datos[$k] = $v;
        }

        return OrdenesTrabajo::create($datos);
    }

    // ==================================================================
    // Los estados, que se centralizan
    // ==================================================================

    public function test_los_cuatro_estados_tienen_texto_y_color(): void
    {
        $this->assertCount(4, OrdenesTrabajo::ESTADOS);

        foreach (OrdenesTrabajo::ESTADOS as $numero => $datos) {
            $this->assertArrayHasKey('texto', $datos, "El estado $numero no tiene texto");
            $this->assertArrayHasKey('color', $datos, "El estado $numero no tiene clase");
            $this->assertArrayHasKey('hex', $datos, "El estado $numero no tiene color");
        }
    }

    public function test_cada_estado_da_su_texto_y_su_color(): void
    {
        $esperados = [
            1 => ['Pendiente', '#4361ee'],
            2 => ['En proceso', '#e2a03f'],
            3 => ['Finalizada', '#00ab55'],
            4 => ['Cancelada', '#e7515a'],
        ];

        foreach ($esperados as $numero => [$texto, $hex]) {
            $orden = new OrdenesTrabajo();
            $orden->estado = $numero;

            $this->assertSame($texto, $orden->estadoTexto(), "estado $numero");
            $this->assertSame($hex, $orden->estadoColor(), "estado $numero");
        }
    }

    public function test_un_estado_desconocido_no_rompe_la_pantalla(): void
    {
        $orden = new OrdenesTrabajo();
        $orden->estado = 99;

        $this->assertSame('Desconocido', $orden->estadoTexto());
        $this->assertSame('bg-secondary', $orden->estadoClase());
        $this->assertNotEmpty($orden->estadoColor());
    }

    public function test_la_etiqueta_del_estado_trae_la_clase_y_el_texto(): void
    {
        $orden = new OrdenesTrabajo();
        $orden->estado = 2;

        $etiqueta = $orden->estadoEtiqueta();

        $this->assertStringContainsString('bg-warning', $etiqueta);
        $this->assertStringContainsString('En proceso', $etiqueta);
    }

    // ==================================================================
    // Los eventos
    // ==================================================================

    public function test_los_eventos_traen_las_ordenes_del_tramo(): void
    {
        $septiembre = $this->crearOrden('2026-09-10');
        $this->crearOrden('2026-09-20');
        $octubre = $this->crearOrden('2026-10-05');

        $r = $this->getJson('/procesos/ordenes-trabajo/eventos?start=2026-09-01&end=2026-09-30')
            ->assertOk()
            ->assertJsonStructure([
                ['id', 'title', 'start', 'allDay', 'backgroundColor', 'extendedProps'],
            ]);

        $ids = array_column($r->json(), 'id');

        $this->assertContains($septiembre->id, $ids);
        $this->assertNotContains(
            $octubre->id,
            $ids,
            'Una orden de octubre no debe aparecer en el mes de septiembre'
        );

        // Fechas en el formato que FullCalendar entiende
        $evento = collect($r->json())->firstWhere('id', $septiembre->id);

        $this->assertSame('2026-09-10', $evento['start']);
        $this->assertTrue($evento['allDay'], 'La orden tiene una sola fecha, no un intervalo');
    }

    public function test_varias_ordenes_del_mismo_dia_salen_todas_ese_dia(): void
    {
        $this->crearOrden('2026-11-03');
        $this->crearOrden('2026-11-03');
        $this->crearOrden('2026-11-03');

        $r = $this->getJson('/procesos/ordenes-trabajo/eventos?start=2026-11-01&end=2026-11-30')
            ->assertOk();

        $this->assertCount(3, $r->json(), 'Tres ordenes del mismo dia tienen que salir las tres');

        $this->assertSame(
            ['2026-11-03', '2026-11-03', '2026-11-03'],
            array_column($r->json(), 'start'),
            'Las tres tienen que caer en la misma fecha'
        );
    }

    public function test_el_evento_trae_lo_que_necesita_el_resumen(): void
    {
        $orden = $this->crearOrden('2026-09-21', [
            'descripcion' => 'Molino de bolas, turno noche',
            'estado' => OrdenesTrabajo::ESTADO_EN_PROCESO,
        ]);

        $r = $this->getJson('/procesos/ordenes-trabajo/eventos?start=2026-09-01&end=2026-09-30')
            ->assertOk();

        $evento = collect($r->json())->firstWhere('id', $orden->id);
        $p = $evento['extendedProps'];

        $this->assertSame($orden->codigo, $p['codigo']);
        $this->assertSame('En proceso', $p['estado']);
        $this->assertSame('21/09/2026', $p['fecha']);
        $this->assertSame('12.50 toneladas', $p['peso']);
        $this->assertSame('Molino de bolas, turno noche', $p['descripcion']);
        $this->assertIsInt($p['procesos']);

        // El enlace a la ficha: el boton "ir a ver" no se arma en el js
        $this->assertSame(
            route('procesos.ordenes_trabajo.show', $orden),
            $p['url']
        );
    }

    public function test_el_color_del_evento_sale_del_estado(): void
    {
        $this->crearOrden('2026-09-11', ['estado' => OrdenesTrabajo::ESTADO_FINALIZADA]);
        $this->crearOrden('2026-09-12', ['estado' => OrdenesTrabajo::ESTADO_CANCELADA]);

        $r = $this->getJson('/procesos/ordenes-trabajo/eventos?start=2026-09-01&end=2026-09-30')
            ->assertOk();

        $porFecha = [];

        foreach ($r->json() as $evento) {
            $porFecha[$evento['start']] = $evento['backgroundColor'];
        }

        $this->assertSame(
            '#00ab55',
            $porFecha['2026-09-11'] ?? null,
            'Una orden finalizada se pinta de verde'
        );

        $this->assertSame(
            '#e7515a',
            $porFecha['2026-09-12'] ?? null,
            'Una orden cancelada se pinta de rojo'
        );
    }

    public function test_una_orden_borrada_no_aparece_en_el_calendario(): void
    {
        $visible = $this->crearOrden('2026-09-15');
        $borrada = $this->crearOrden('2026-09-16');
        $borrada->delete();

        $r = $this->getJson('/procesos/ordenes-trabajo/eventos?start=2026-09-01&end=2026-09-30')
            ->assertOk();

        $ids = array_column($r->json(), 'id');

        $this->assertContains($visible->id, $ids);
        $this->assertNotContains(
            $borrada->id,
            $ids,
            'Una orden borrada logicamente no tiene que verse en el calendario'
        );
    }

    public function test_el_tramo_se_acepta_con_horas(): void
    {
        $this->crearOrden('2026-12-31');

        // FullCalendar manda las fechas con hora, no solo el dia
        $r = $this->getJson(
            '/procesos/ordenes-trabajo/eventos?start=2026-12-01T00:00:00'
            . '&end=2026-12-31T23:59:59'
        )->assertOk();

        $this->assertCount(1, $r->json());
    }

    public function test_pide_el_tramo_que_le_hace_falta(): void
    {
        $this->getJson('/procesos/ordenes-trabajo/eventos')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start', 'end']);
    }

    public function test_rechaza_un_tramo_al_reves(): void
    {
        $this->getJson('/procesos/ordenes-trabajo/eventos?start=2026-09-30&end=2026-09-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end']);
    }

    // ==================================================================
    // La pantalla
    // ==================================================================

    public function test_la_pantalla_del_calendario_carga(): void
    {
        $html = $this->get('/procesos/ordenes-trabajo/calendario')->assertOk()->getContent();

        $this->assertStringContainsString('id="calendar"', $html);
        $this->assertStringContainsString('fullcalendar.global.js', $html);
    }

    public function test_la_pantalla_le_pasa_la_url_de_los_eventos(): void
    {
        $html = $this->get('/procesos/ordenes-trabajo/calendario')->assertOk()->getContent();

        $this->assertStringContainsString(
            'data-eventos-url="' . route('procesos.ordenes_trabajo.eventos') . '"',
            $html,
            'El calendario necesita saber de donde pedir los eventos'
        );
    }

    public function test_la_pantalla_trae_el_modal_del_resumen(): void
    {
        $html = $this->get('/procesos/ordenes-trabajo/calendario')->assertOk()->getContent();

        $this->assertStringContainsString('id="modalOrdenCalendario"', $html);

        // Los campos que el resumen muestra
        foreach (['calCodigo', 'calCliente', 'calFecha', 'calEstado', 'calPeso', 'calProcesos'] as $campo) {
            $this->assertStringContainsString($campo, $html, "Falta el campo $campo del resumen");
        }

        // Y el boton de ir a la ficha
        $this->assertStringContainsString('id="calIrAVer"', $html);
        $this->assertStringContainsString('Ir a ver la orden', $html);
    }

    public function test_el_calendario_no_arranca_en_un_dia_fijo(): void
    {
        $js = file_get_contents(public_path('js/ordenes/calendario.js'));

        /*
         * El ejemplo del theme venia con la fecha clavada en el dia 7 del
         * mes en curso, asi que al abrir el calendario se veía un mes
         * cualquiera y no el de hoy.
         *
         * Se busca la opcion (initialDate seguido de dos puntos), no la
         * palabra suelta: la palabra aparece en el comentario que explica
         * esto mismo, y daria un falso positivo.
         */
        $this->assertDoesNotMatchRegularExpression(
            '/initialDate\s*:/',
            $js,
            'El calendario debe arrancar en el mes de hoy, no en una fecha fija'
        );

        $this->assertStringContainsString('dayGridMonth', $js);
    }

    public function test_el_calendario_pide_los_eventos_al_servidor(): void
    {
        $js = file_get_contents(public_path('js/ordenes/calendario.js'));

        /*
         * Los eventos vienen por url: si vinieran metidos en la pagina, al
         * cambiar de mes el calendario se veria vacio, porque en el HTML
         * solo estaria el mes con el que se cargo.
         */
        $this->assertStringContainsString('events:', $js);
        $this->assertStringContainsString('eventClick', $js);

        // La url la lee del atributo del elemento, no esta escrita en el js
        $this->assertStringContainsString('eventosUrl', $js);

        $this->assertStringContainsString(
            'data-eventos-url=',
            $this->get('/procesos/ordenes-trabajo/calendario')->assertOk()->getContent()
        );
    }

    public function test_el_calendario_usa_el_locale_es(): void
    {
        $js = file_get_contents(public_path('js/ordenes/calendario.js'));

        $this->assertStringContainsString("locale: 'es'", $js);
    }

    public function test_la_url_de_la_orden_no_esta_escrita_en_el_js(): void
    {
        /*
         * El enlace a la ficha lo manda el servidor dentro del evento. Si
         * el javascript construyera la url, cambiar el nombre de la ruta
         * obligaria a cambiarla en dos sitios, y es el segundo el que se
         * olvida.
         */
        $js = file_get_contents(public_path('js/ordenes/calendario.js'));

        $this->assertStringNotContainsString(
            'ordenes-trabajo/',
            $js,
            'La url de la orden debe venir del servidor, no escribirse en el js'
        );
    }

    // ==================================================================
    // Permisos
    // ==================================================================

    public function test_hacen_falta_permisos_para_ver_el_calendario(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->usuarioSinPermisosDeOrdenes());

        $this->get('/procesos/ordenes-trabajo/calendario')->assertForbidden();
        $this->getJson('/procesos/ordenes-trabajo/eventos?start=2026-09-01&end=2026-09-30')
            ->assertForbidden();
    }

    private function usuarioSinPermisosDeOrdenes(): User
    {
        $usuario = User::create([
            'name' => 'Sin permisos',
            'email' => 'cal-sin-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);

        // El rol operador no tiene ordenes_trabajo.delete, pero si el resto
        // de permisos de ordenes; se quita el de ver para comprobar el
        // caso de un usuario sin nada
        $usuario->syncRoles([]);

        return $usuario;
    }

    // ==================================================================
    // La ruta no se come con el resource
    // ==================================================================

    public function test_la_url_de_eventos_no_se_omite_como_una_orden(): void
    {
        $this->getJson('/procesos/ordenes-trabajo/eventos?start=2026-09-01&end=2026-09-30')
            ->assertOk();

        /*
         * Si el resource se comiera la palabra "eventos" como si fuera el
         * codigo de una orden, esto daria 404. Sin parametros no se puede
         * ni validar, asi que se espera el error de validacion.
         */
        $this->getJson('/procesos/ordenes-trabajo/eventos')
            ->assertStatus(422);
    }
}

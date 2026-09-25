<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\OrdenesTrabajo;
use App\Models\TiposPagoEmpleado;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Select2 convierte los <select> nativos en combos con buscador.
 *
 * Se activa con la clase "select2" y se engancha al jQuery que ya carga
 * el layout. Este test comprueba que el markup y los assets esten
 * donde deben; el comportamiento en si lo verifica el navegador.
 */
class Select2EnFormulariosTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 's2-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

    public function test_los_assets_de_select2_existen_en_el_manifest(): void
    {
        $manifest = json_decode(
            file_get_contents(public_path('build/manifest.json')),
            true
        );

        $this->assertArrayHasKey(
            'resources/assets/js/select2/select2-init.js',
            $manifest,
            'El JS de Select2 no esta en el manifest de Vite'
        );

        $this->assertArrayHasKey(
            'resources/scss/light/plugins/select2/custom-select2.scss',
            $manifest,
            'El CSS de Select2 no esta en el manifest de Vite'
        );

        // Y los archivos existen de verdad en public/build
        $this->assertFileExists(
            public_path('build/' . $manifest['resources/assets/js/select2/select2-init.js']['file'])
        );
        $this->assertFileExists(
            public_path('build/' . $manifest['resources/scss/light/plugins/select2/custom-select2.scss']['file'])
        );
    }

    public function test_select2_esta_registrado_una_sola_vez(): void
    {
        // Se lee el codigo sin comentarios: el bloque de documentacion
        // menciona justamente lo que se comprueba aqui.
        $crudo = file_get_contents(
            resource_path('assets/js/select2/select2-init.js')
        );

        $codigo = preg_replace('#/\*.*?\*/#s', '', $crudo);
        $codigo = preg_replace('#//[^\n]*#', '', $codigo);

        // Se engancha al jQuery del layout, no importa uno propio:
        // dos copias de jQuery romperian los manejadores de la pagina.
        $this->assertStringContainsString('window.jQuery', $codigo);
        $this->assertStringNotContainsString('jquery', $codigo);

        // Y se destruye antes de reinicializar
        $this->assertStringContainsString("select2('destroy')", $codigo);

        // Se expone para poder activarlo sobre contenido traido por AJAX
        $this->assertStringContainsString('window.iniciarSelect2', $codigo);

        // Mensajes en espanol
        $this->assertStringContainsString('Sin resultados', $crudo);
    }

    public function test_las_paginas_cargan_select2(): void
    {
        $empleado = Empleado::firstOrFail();

        $paginas = [
            'tipos de pago - crear' => '/configuracion/tipos-pago-empleado/create',
            'empleados - crear' => '/admin/empleados/create',
            'empleados - editar' => '/admin/empleados/' . $empleado->id . '/edit',
        ];

        foreach ($paginas as $nombre => $url) {
            $r = $this->get($url);

            $r->assertOk();
            $r->assertSee('select2-init-', false, "Falta el JS de Select2 en: $nombre");
            $r->assertSee('custom-select2-', false, "Falta el CSS de Select2 en: $nombre");
        }
    }

    public function test_los_combos_de_los_formularios_marcan_select2(): void
    {
        $empleado = Empleado::firstOrFail();

        // Modalidad de empleado
        $this->get('/admin/empleados/' . $empleado->id . '/edit')
            ->assertOk()
            ->assertSee('form-select select2', false)
            ->assertSee('data-select2-opciones', false);

        // Etapa del proceso
        $orden = OrdenesTrabajo::firstOrFail();

        $this->get('/procesos/ordenes-trabajo/' . $orden->id . '/procesos/create')
            ->assertOk()
            ->assertSee('form-select select2', false)
            ->assertSee('data-select2-opciones', false);
    }

    /**
     * El formulario de tarifas vive en un modal que se llena por AJAX, asi
     * que la pagina que lo hospeda no trae los selects: hay que pedirlos.
     */
    public function test_el_modal_de_tarifas_trae_los_combos_con_select2(): void
    {
        $empleado = Empleado::firstOrFail();

        $r = $this->get('/admin/empleados/' . $empleado->id . '/pagos/create');

        $r->assertOk();
        $r->assertSee('form-select select2', false);
        $r->assertSee('data-select2-opciones', false);

        // Y la pagina que hospeda el modal trae los assets
        $this->get('/admin/empleados/' . $empleado->id)
            ->assertOk()
            ->assertSee('select2-init-', false)
            ->assertSee('custom-select2-', false);
    }

    /**
     * El JS del modal debe activar Select2 sobre el contenido inyectado:
     * si no, los combos se quedan nativos aunque la vista los marque.
     */
    public function test_el_modal_activa_select2_al_inyectar_el_formulario(): void
    {
        $js = file_get_contents(public_path('js/empleados/pagos/form.js'));

        $this->assertStringContainsString(
            'window.iniciarSelect2',
            $js,
            'El modal no reinicializa Select2 sobre el formulario inyectado'
        );
    }

    public function test_el_select_de_metodo_calculo_usa_select2_y_el_estado_no(): void
    {
        $tipo = TiposPagoEmpleado::firstOrFail();

        $html = (string) view('configuracion.tipos_pago_empleado._form', [
            'tipoPagoEmpleado' => $tipo,
            'metodosCalculo' => TiposPagoEmpleado::metodosCalculo(),
            'esEdicion' => false,
            // El middleware de sesion no corre en un view() suelto,
            // pero la directiva @error necesita el almacen de errores.
            'errors' => new ViewErrorBag,
        ])->render();

        // El metodo de calculo es un combo
        $this->assertStringContainsString('name="metodo_calculo"', $html);

        $etiqueta = $this->etiquetaCon($html, 'name="metodo_calculo"');
        $this->assertStringContainsString(
            'select2',
            $etiqueta,
            'El selector de metodo_calculo no esta marcado para Select2'
        );

        // El estado es un interruptor, no un combo: Select2 no aplica.
        // Se revisan todas las etiquetas que lo nombran, sin importar
        // en que orden vengan los atributos.
        foreach ($this->etiquetasCon($html, 'name="estado"') as $etiqueta) {
            $this->assertStringNotContainsString(
                'select2',
                $etiqueta,
                'El campo de estado no deberia llevar Select2'
            );
        }
    }

    /**
     * Devuelve la etiqueta HTML que contiene el atributo dado.
     */
    private function etiquetaCon(string $html, string $atributo): string
    {
        preg_match('/<[a-z]+\b[^>]*' . preg_quote($atributo, '/') . '[^>]*>/i', $html, $m);

        $this->assertNotEmpty(
            $m,
            "No se encontro ninguna etiqueta con $atributo"
        );

        return $m[0];
    }

    /**
     * @return string[]
     */
    private function etiquetasCon(string $html, string $atributo): array
    {
        preg_match_all('/<[a-z]+\b[^>]*' . preg_quote($atributo, '/') . '[^>]*>/i', $html, $m);

        return $m[0];
    }
}

<?php

namespace Tests\Feature;

use App\Models\Etapa;
use App\Models\OrdenesTrabajo;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrdenTrabajoPdfTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin PDF OT',
            'email' => 'orden-pdf-'.uniqid().'@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

    public function test_descarga_pdf_de_orden_sin_procesos_asociados_y_muestra_el_catalogo_del_sistema(): void
    {
        $orden = $this->crearOrden();
        $etapas = Etapa::query()->orderBy('orden')->orderBy('nombre')->get();
        $this->assertSame(0, $orden->procesos_ordenes()->count());

        $respuesta = $this->get(route('procesos.ordenes_trabajo.pdf', $orden));

        $respuesta->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=orden-trabajo-'.$orden->codigo.'.pdf');
        $this->assertStringStartsWith('%PDF-', $respuesta->getContent());

        $html = view('procesos.ordenes_trabajo.pdf', [
            'ordenTrabajo' => $orden->load('cliente'),
            'etapas' => $etapas,
            'renglonesEnBlanco' => $etapas->isEmpty() ? 8 : 5,
        ])->render();
        $this->assertStringContainsString('Procesos disponibles en el sistema', $html);
        foreach ($etapas as $etapa) {
            $this->assertStringContainsString($etapa->nombre, $html);
        }
        $this->assertStringContainsString('Firma del cliente', $html);
        $this->assertSame($etapas->count() + ($etapas->isEmpty() ? 8 : 5), substr_count($html, '<td class="numero">'));
    }

    public function test_el_pdf_lista_las_etapas_del_catalogo_y_deja_renglones_adicionales(): void
    {
        $orden = $this->crearOrden();
        $etapa = Etapa::query()->where('estado', true)->firstOrFail();

        $respuesta = $this->get(route('procesos.ordenes_trabajo.pdf', $orden));

        $respuesta->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $respuesta->getContent());

        $etapas = Etapa::query()->orderBy('orden')->orderBy('nombre')->get();
        $html = view('procesos.ordenes_trabajo.pdf', [
            'ordenTrabajo' => $orden->fresh()->load('cliente'),
            'etapas' => $etapas,
            'renglonesEnBlanco' => 5,
        ])->render();
        $this->assertStringContainsString($etapa->nombre, $html);
        $this->assertSame($etapas->count() + 5, substr_count($html, '<td class="check">□</td>'));
    }

    public function test_el_controlador_envia_al_pdf_el_catalogo_general_de_procesos(): void
    {
        $orden = $this->crearOrden();
        $etapa = Etapa::create([
            'nombre' => 'Proceso PDF '.strtoupper(Str::random(6)),
            'descripcion' => 'Etapa configurada para el catálogo PDF',
            'orden' => 99999,
            'estado' => true,
        ]);

        $pdf = Mockery::mock(PdfDocument::class);
        $pdf->shouldReceive('setPaper')->once()->with('letter', 'portrait')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(response('fake-pdf'));

        PdfFacade::shouldReceive('loadView')
            ->once()
            ->with('procesos.ordenes_trabajo.pdf', Mockery::on(function (array $datos) use ($etapa) {
                return $datos['etapas']->contains(fn (Etapa $fila) => $fila->is($etapa))
                    && $datos['renglonesEnBlanco'] === 5;
            }))
            ->andReturn($pdf);

        $this->get(route('procesos.ordenes_trabajo.pdf', $orden))
            ->assertOk()
            ->assertSee('fake-pdf');
    }

    private function crearOrden(): OrdenesTrabajo
    {
        $base = OrdenesTrabajo::query()->firstOrFail();

        return OrdenesTrabajo::create([
            'codigo' => 'OT-PDF-'.strtoupper(Str::random(8)),
            'cliente_id' => $base->cliente_id,
            'fecha' => today(),
            'descripcion' => 'Orden para autorizar trabajos',
            'peso_mineral' => 6.375,
            'unidad_peso' => 'kg',
            'unidad_peso_id' => $base->unidad_peso_id,
            'estado' => OrdenesTrabajo::ESTADO_PENDIENTE,
        ]);
    }
}

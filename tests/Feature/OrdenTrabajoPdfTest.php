<?php

namespace Tests\Feature;

use App\Models\Etapa;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
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

    public function test_descarga_pdf_de_orden_con_renglones_para_procesos_aun_no_registrados(): void
    {
        $orden = $this->crearOrden();

        $respuesta = $this->get(route('procesos.ordenes_trabajo.pdf', $orden));

        $respuesta->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=orden-trabajo-'.$orden->codigo.'.pdf');
        $this->assertStringStartsWith('%PDF-', $respuesta->getContent());

        $html = view('procesos.ordenes_trabajo.pdf', [
            'ordenTrabajo' => $orden->load('cliente'),
            'procesos' => collect(),
            'renglonesEnBlanco' => 8,
        ])->render();
        $this->assertStringContainsString('Procesos acordados', $html);
        $this->assertStringContainsString('Firma del cliente', $html);
        $this->assertSame(8, substr_count($html, '<td class="numero">'));
    }

    public function test_el_pdf_incluye_los_procesos_ya_registrados(): void
    {
        $orden = $this->crearOrden();
        $etapa = Etapa::query()->where('estado', true)->firstOrFail();
        ProcesosOrden::create([
            'orden_trabajo_id' => $orden->id,
            'etapa_id' => $etapa->id,
            'codigo' => 'PR-'.strtoupper(Str::random(8)),
            'estado' => ProcesosOrden::ESTADO_PENDIENTE,
            'observaciones' => 'Proceso validado con el cliente',
        ]);

        $respuesta = $this->get(route('procesos.ordenes_trabajo.pdf', $orden));

        $respuesta->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $respuesta->getContent());

        $procesos = $orden->fresh()->load('procesos_ordenes.etapa')->procesos_ordenes;
        $html = view('procesos.ordenes_trabajo.pdf', [
            'ordenTrabajo' => $orden->fresh()->load('cliente'),
            'procesos' => $procesos,
            'renglonesEnBlanco' => 5,
        ])->render();
        $this->assertStringContainsString($etapa->nombre, $html);
        $this->assertStringContainsString('Proceso validado con el cliente', $html);
        $this->assertSame(6, substr_count($html, '<td class="check">□</td>'));
    }

    public function test_el_controlador_envia_al_pdf_los_procesos_de_la_orden(): void
    {
        $orden = $this->crearOrden();
        $etapa = Etapa::query()->where('estado', true)->firstOrFail();
        $proceso = ProcesosOrden::create([
            'orden_trabajo_id' => $orden->id,
            'etapa_id' => $etapa->id,
            'codigo' => 'PR-'.strtoupper(Str::random(8)),
            'estado' => ProcesosOrden::ESTADO_PENDIENTE,
        ]);

        $pdf = Mockery::mock(PdfDocument::class);
        $pdf->shouldReceive('setPaper')->once()->with('letter', 'portrait')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(response('fake-pdf'));

        PdfFacade::shouldReceive('loadView')
            ->once()
            ->with('procesos.ordenes_trabajo.pdf', Mockery::on(function (array $datos) use ($proceso) {
                return $datos['procesos']->contains(fn (ProcesosOrden $fila) => $fila->is($proceso))
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

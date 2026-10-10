<?php

namespace App\Http\Controllers\Procesos;

use App\DataTables\CotizacionesDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\AcuerdosOrden;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Moneda;
use App\Models\OrdenesTrabajo;
use App\Models\TiposParticipacion;
use App\Models\UnidadMedida;
use App\Services\CodigoOrdenTrabajo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

class CotizacionController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('cotizaciones', ['index', 'show']);
        $this->middleware('permission:cotizaciones.edit')->only(['update', 'enviar', 'aceptar', 'rechazar']);
    }

    public function index(CotizacionesDataTable $dataTable)
    {
        return $dataTable->render('comercial.cotizaciones.index', [
            'title' => 'Cotizaciones',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('home')],
                ['label' => 'Gestión Comercial'],
                ['label' => 'Cotizaciones'],
            ],
        ]);
    }

    public function create()
    {
        return view('comercial.cotizaciones.create', [
            'title' => 'Nueva cotización',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('home')],
                ['label' => 'Cotizaciones', 'url' => route('comercial.cotizaciones.index')],
                ['label' => 'Nueva cotización'],
            ],
            'cotizacion' => new Cotizacion([
                'fecha' => today(),
                'fecha_vencimiento' => today()->addDays(30),
            ]),
            ...$this->datosFormulario(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $datos['codigo'] = $this->generarCodigo();
        $datos['estado'] = Cotizacion::ESTADO_BORRADOR;
        $cotizacion = Cotizacion::create($datos);

        Alert::toast('Cotización guardada como borrador.')->success()->flash();

        return redirect()->route('comercial.cotizaciones.show', $cotizacion);
    }

    public function show(Cotizacion $cotizacion)
    {
        $cotizacion->load(['cliente', 'unidadMedida', 'tipoParticipacion', 'moneda', 'ordenTrabajo']);

        return view('comercial.cotizaciones.show', [
            'title' => 'Cotización '.$cotizacion->codigo,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('home')],
                ['label' => 'Cotizaciones', 'url' => route('comercial.cotizaciones.index')],
                ['label' => $cotizacion->codigo],
            ],
            'cotizacion' => $cotizacion,
        ]);
    }

    public function edit(Cotizacion $cotizacion)
    {
        if ($cotizacion->estado !== Cotizacion::ESTADO_BORRADOR) {
            Alert::toast('Solo se pueden editar cotizaciones en borrador.')->error()->flash();

            return redirect()->route('comercial.cotizaciones.show', $cotizacion);
        }

        return view('comercial.cotizaciones.edit', [
            'title' => 'Editar cotización '.$cotizacion->codigo,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('home')],
                ['label' => 'Cotizaciones', 'url' => route('comercial.cotizaciones.index')],
                ['label' => $cotizacion->codigo, 'url' => route('comercial.cotizaciones.show', $cotizacion)],
                ['label' => 'Editar'],
            ],
            'cotizacion' => $cotizacion,
            ...$this->datosFormulario(),
        ]);
    }

    public function update(Request $request, Cotizacion $cotizacion)
    {
        $this->exigirBorrador($cotizacion);
        $cotizacion->update($this->validar($request));

        Alert::toast('Cotización actualizada.')->success()->flash();

        return redirect()->route('comercial.cotizaciones.show', $cotizacion);
    }

    public function destroy(Cotizacion $cotizacion)
    {
        $this->exigirBorrador($cotizacion);
        abort_unless(! $cotizacion->estaVencida(), 404, 'Las cotizaciones vencidas se conservan en el historial.');
        $cotizacion->delete();

        Alert::toast('Cotización eliminada.')->success()->flash();

        return redirect()->route('comercial.cotizaciones.index');
    }

    public function enviar(Cotizacion $cotizacion)
    {
        $this->exigirEstado($cotizacion, Cotizacion::ESTADO_BORRADOR);
        if ($cotizacion->fecha_vencimiento->isBefore(today())) {
            throw ValidationException::withMessages([
                'fecha_vencimiento' => 'La fecha de vigencia ya pasó. Edite la cotización antes de enviarla.',
            ]);
        }

        $cotizacion->update(['estado' => Cotizacion::ESTADO_ENVIADA]);
        Alert::toast('Cotización marcada como enviada.')->success()->flash();

        return redirect()->route('comercial.cotizaciones.show', $cotizacion);
    }

    public function rechazar(Cotizacion $cotizacion)
    {
        $this->exigirEstado($cotizacion, Cotizacion::ESTADO_ENVIADA);
        if ($cotizacion->estaVencida()) {
            Alert::toast('La cotización vencida no puede marcarse como rechazada.')->error()->flash();

            return redirect()->route('comercial.cotizaciones.show', $cotizacion);
        }

        $cotizacion->update(['estado' => Cotizacion::ESTADO_RECHAZADA]);
        Alert::toast('Cotización marcada como rechazada.')->success()->flash();

        return redirect()->route('comercial.cotizaciones.show', $cotizacion);
    }

    public function aceptar(Cotizacion $cotizacion, CodigoOrdenTrabajo $codigoOrden)
    {
        $ordenTrabajo = DB::transaction(function () use ($cotizacion, $codigoOrden) {
            $cotizacion = Cotizacion::query()->lockForUpdate()->findOrFail($cotizacion->id);
            $this->exigirEstado($cotizacion, Cotizacion::ESTADO_ENVIADA);

            if ($cotizacion->estaVencida()) {
                throw ValidationException::withMessages([
                    'cotizacion' => 'La cotización venció y ya no se puede aceptar.',
                ]);
            }

            $orden = OrdenesTrabajo::create([
                'codigo' => $codigoOrden->generar(),
                'cliente_id' => $cotizacion->cliente_id,
                'fecha' => today(),
                'descripcion' => $cotizacion->descripcion,
                'peso_mineral' => $cotizacion->peso_mineral,
                'unidad_peso' => $cotizacion->unidad_peso,
                'unidad_peso_id' => $cotizacion->unidad_peso_id,
                'estado' => OrdenesTrabajo::ESTADO_PENDIENTE,
            ]);

            AcuerdosOrden::create([
                'orden_trabajo_id' => $orden->id,
                'tipo_participacion_id' => $cotizacion->tipo_participacion_id,
                'porcentaje_cliente' => $cotizacion->porcentaje_cliente,
                'porcentaje_empresa' => $cotizacion->porcentaje_empresa,
                'tarifa_servicio' => $cotizacion->tarifa_servicio,
                'moneda_id' => $cotizacion->moneda_id,
                'observaciones' => $cotizacion->observaciones,
                'estado' => true,
            ]);

            $cotizacion->update([
                'estado' => Cotizacion::ESTADO_ACEPTADA,
                'orden_trabajo_id' => $orden->id,
            ]);

            return $orden;
        });

        Alert::toast('Cotización aceptada y orden de trabajo creada.')->success()->flash();

        return redirect()->route('procesos.ordenes_trabajo.show', $ordenTrabajo);
    }

    private function datosFormulario(): array
    {
        return [
            'clientes' => Cliente::query()->where('estado', true)->orderBy('nombre')->get(),
            'unidadesMedida' => UnidadMedida::query()->where('estado', true)->orderBy('nombre')->get(),
            'tiposParticipacion' => TiposParticipacion::paraRegistrar()->orderBy('nombre')->get(),
            'monedas' => Moneda::query()->where('estado', true)->orderBy('codigo')->get(),
            'monedaBase' => Moneda::base(),
        ];
    }

    private function validar(Request $request): array
    {
        $modalidad = $request->input('modalidad');
        if ($modalidad === 'porcentajes') {
            $request->merge(['moneda_id' => Moneda::base()?->id]);
        }

        $datos = $request->validate([
            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'id')->where('estado', true)->whereNull('deleted_at')],
            'fecha' => ['required', 'date'],
            'fecha_vencimiento' => ['required', 'date', 'after_or_equal:fecha'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'peso_mineral' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'decimal:0,3'],
            'unidad_peso_id' => ['required', 'integer', Rule::exists('unidades_medida', 'id')->where('estado', true)],
            'tipo_participacion_id' => ['required', 'integer', Rule::exists('tipos_participacion', 'id')->where('estado', true)->whereNull('deleted_at')],
            'modalidad' => ['required', Rule::in(['porcentajes', 'tarifa'])],
            'porcentaje_cliente' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'porcentaje_empresa' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tarifa_servicio' => ['nullable', 'numeric', 'gt:0', 'max:999999999999.99'],
            'moneda_id' => ['required', 'integer', Rule::exists('monedas', 'id')->where('estado', true)->whereNull('deleted_at')],
            'observaciones' => ['nullable', 'string', 'max:5000'],
        ]);

        $tienePorcentajes = $modalidad === 'porcentajes';
        if ($tienePorcentajes) {
            if (! isset($datos['porcentaje_cliente'], $datos['porcentaje_empresa'])
                || abs(round((float) $datos['porcentaje_cliente'] + (float) $datos['porcentaje_empresa'], 4) - 100) > 0.0001) {
                throw ValidationException::withMessages([
                    'porcentaje_empresa' => 'Los porcentajes de cliente y empresa deben sumar exactamente 100%.',
                ]);
            }
            $datos['tarifa_servicio'] = null;
        } else {
            if (empty($datos['tarifa_servicio'])) {
                throw ValidationException::withMessages([
                    'tarifa_servicio' => 'Indique el importe total fijo de la tarifa.',
                ]);
            }
            $datos['porcentaje_cliente'] = null;
            $datos['porcentaje_empresa'] = null;
        }

        $unidad = UnidadMedida::findOrFail($datos['unidad_peso_id']);
        $datos['unidad_peso'] = $unidad->nombre;
        unset($datos['modalidad']);

        return $datos;
    }

    private function exigirBorrador(Cotizacion $cotizacion): void
    {
        $this->exigirEstado($cotizacion, Cotizacion::ESTADO_BORRADOR);
    }

    private function exigirEstado(Cotizacion $cotizacion, string $estado): void
    {
        abort_unless($cotizacion->estado === $estado, 404, 'La cotización ya no se encuentra en ese estado.');
    }

    private function generarCodigo(): string
    {
        $prefijo = 'COT-'.now()->year.'-';
        $ultimo = Cotizacion::withTrashed()->where('codigo', 'like', $prefijo.'%')->orderByDesc('id')->value('codigo');
        $numero = $ultimo ? ((int) substr($ultimo, strrpos($ultimo, '-') + 1)) + 1 : 1;

        return $prefijo.str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    }
}

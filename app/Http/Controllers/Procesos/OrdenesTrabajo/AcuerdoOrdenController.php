<?php

namespace App\Http\Controllers\Procesos\OrdenesTrabajo;

use App\DataTables\AcuerdosOrdenDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\AcuerdosOrden;
use App\Models\Moneda;
use App\Models\OrdenesTrabajo;
use App\Models\TiposParticipacion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AcuerdoOrdenController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('acuerdos_orden', ['index', 'show']);
    }

    public function index(OrdenesTrabajo $ordenTrabajo, AcuerdosOrdenDataTable $dataTable)
    {
        return $dataTable->setOrdenTrabajoId($ordenTrabajo->id)->ajax();
    }

    public function store(Request $request, OrdenesTrabajo $ordenTrabajo)
    {
        $datos = $this->validar($request);
        $this->validarCatalogosActivos($datos);
        $datos[AcuerdosOrden::ORDEN_TRABAJO_ID] = $ordenTrabajo->id;

        $acuerdo = AcuerdosOrden::create($datos);

        return response()->json(['ok' => true, 'id' => $acuerdo->id]);
    }

    public function show(OrdenesTrabajo $ordenTrabajo, AcuerdosOrden $acuerdoOrden)
    {
        $this->validarPertenencia($ordenTrabajo, $acuerdoOrden);
        $acuerdoOrden->load(['tipo_participacion', 'moneda']);

        return response()->json([
            'id' => $acuerdoOrden->id,
            'tipo_participacion_id' => $acuerdoOrden->tipo_participacion_id,
            'tipo_participacion' => $acuerdoOrden->tipo_participacion?->nombre,
            'porcentaje_cliente' => $acuerdoOrden->porcentaje_cliente,
            'porcentaje_empresa' => $acuerdoOrden->porcentaje_empresa,
            'tarifa_servicio' => $acuerdoOrden->tarifa_servicio,
            'moneda_id' => $acuerdoOrden->moneda_id,
            'moneda' => $acuerdoOrden->moneda?->codigo,
            'observaciones' => $acuerdoOrden->observaciones,
            'estado' => (bool) $acuerdoOrden->estado,
        ]);
    }

    public function update(Request $request, OrdenesTrabajo $ordenTrabajo, AcuerdosOrden $acuerdoOrden)
    {
        $this->validarPertenencia($ordenTrabajo, $acuerdoOrden);
        $datos = $this->validar($request, $acuerdoOrden);
        $this->validarCatalogosActivos($datos, $acuerdoOrden);
        $acuerdoOrden->update($datos);

        return response()->json(['ok' => true, 'id' => $acuerdoOrden->id]);
    }

    public function destroy(OrdenesTrabajo $ordenTrabajo, AcuerdosOrden $acuerdoOrden)
    {
        $this->validarPertenencia($ordenTrabajo, $acuerdoOrden);
        $acuerdoOrden->delete();

        return response()->json(['ok' => true]);
    }

    private function validar(Request $request, ?AcuerdosOrden $acuerdo = null): array
    {
        $usaPorcentajes = ($request->input('porcentaje_cliente') !== null && $request->input('porcentaje_cliente') !== '')
            || ($request->input('porcentaje_empresa') !== null && $request->input('porcentaje_empresa') !== '');
        $usaTarifa = $request->input('tarifa_servicio') !== null && $request->input('tarifa_servicio') !== '';
        if ($usaPorcentajes && ! $usaTarifa) {
            $request->merge([
                'moneda_id' => $acuerdo?->moneda_id ?? Moneda::base()?->id,
            ]);
        }

        $datos = $request->validate([
            'tipo_participacion_id' => ['required', 'integer', Rule::exists('tipos_participacion', 'id')->whereNull('deleted_at')],
            'porcentaje_cliente' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'porcentaje_empresa' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tarifa_servicio' => ['nullable', 'numeric', 'gt:0'],
            'moneda_id' => ['required', 'integer', Rule::exists('monedas', 'id')->whereNull('deleted_at')],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'estado' => ['required', 'in:0,1'],
        ], [
            'tipo_participacion_id.required' => 'Seleccione el tipo de participación.',
            'tipo_participacion_id.exists' => 'El tipo de participación seleccionado ya no existe.',
            'porcentaje_cliente.numeric' => 'El porcentaje del cliente debe ser numérico.',
            'porcentaje_cliente.min' => 'El porcentaje del cliente no puede ser negativo.',
            'porcentaje_cliente.max' => 'El porcentaje del cliente no puede superar 100.',
            'porcentaje_empresa.numeric' => 'El porcentaje de la empresa debe ser numérico.',
            'porcentaje_empresa.min' => 'El porcentaje de la empresa no puede ser negativo.',
            'porcentaje_empresa.max' => 'El porcentaje de la empresa no puede superar 100.',
            'tarifa_servicio.gt' => 'La tarifa de servicio debe ser mayor que cero.',
            'moneda_id.required' => 'Seleccione la moneda del acuerdo.',
            'moneda_id.exists' => 'La moneda seleccionada ya no existe.',
            'estado.required' => 'Indique si el acuerdo está activo.',
            'estado.in' => 'El estado debe ser activo o inactivo.',
        ]);

        $cliente = $datos['porcentaje_cliente'] ?? null;
        $empresa = $datos['porcentaje_empresa'] ?? null;
        $tarifa = $datos['tarifa_servicio'] ?? null;
        $tienePorcentajes = $cliente !== null || $empresa !== null;
        $tieneTarifa = $tarifa !== null;

        if ($tienePorcentajes && ($cliente === null || $empresa === null)) {
            throw ValidationException::withMessages([
                'porcentaje_cliente' => 'Complete ambos porcentajes para definir la participación.',
            ]);
        }

        if ($tienePorcentajes === $tieneTarifa) {
            throw ValidationException::withMessages([
                'modalidad' => 'Defina una sola modalidad: porcentajes de participación o tarifa de servicio.',
            ]);
        }

        if ($tienePorcentajes && abs(round((float) $cliente + (float) $empresa, 4) - 100) > 0.0001) {
            throw ValidationException::withMessages([
                'porcentaje_empresa' => 'Los porcentajes del cliente y de la empresa deben sumar exactamente 100%.',
            ]);
        }

        $datos['porcentaje_cliente'] = $tienePorcentajes ? $cliente : null;
        $datos['porcentaje_empresa'] = $tienePorcentajes ? $empresa : null;
        $datos['tarifa_servicio'] = $tieneTarifa ? $tarifa : null;
        $datos['observaciones'] = filled($datos['observaciones'] ?? null) ? $datos['observaciones'] : null;
        $datos['estado'] = (int) $datos['estado'];

        return $datos;
    }

    private function validarCatalogosActivos(array $datos, ?AcuerdosOrden $acuerdo = null): void
    {
        $tipo = TiposParticipacion::withTrashed()->find($datos['tipo_participacion_id']);
        if (! $tipo || ($tipo->deleted_at !== null)
            || (! $tipo->estado && (int) $tipo->id !== (int) ($acuerdo?->tipo_participacion_id))) {
            throw ValidationException::withMessages([
                'tipo_participacion_id' => 'El tipo está inactivo y solo se puede conservar al corregir un acuerdo que ya lo usa.',
            ]);
        }

        $moneda = Moneda::withTrashed()->find($datos['moneda_id']);
        if (! $moneda || ($moneda->deleted_at !== null)
            || (! $moneda->estado && (int) $moneda->id !== (int) ($acuerdo?->moneda_id))) {
            throw ValidationException::withMessages([
                'moneda_id' => 'La moneda está inactiva y solo se puede conservar al corregir un acuerdo que ya la usa.',
            ]);
        }
    }

    private function validarPertenencia(OrdenesTrabajo $ordenTrabajo, AcuerdosOrden $acuerdoOrden): void
    {
        abort_unless((int) $acuerdoOrden->orden_trabajo_id === (int) $ordenTrabajo->id, 404);
    }
}

<?php

namespace App\Http\Controllers\Procesos\OrdenesTrabajo;

use App\DataTables\LiquidacionesDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\AcuerdosOrden;
use App\Models\Liquidacione;
use App\Models\OrdenesTrabajo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LiquidacionOrdenController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('liquidaciones', ['index', 'show']);
    }

    public function index(OrdenesTrabajo $ordenTrabajo, LiquidacionesDataTable $dataTable)
    {
        return $dataTable->setOrdenTrabajoId($ordenTrabajo->id)->ajax();
    }

    public function store(Request $request, OrdenesTrabajo $ordenTrabajo)
    {
        if ((int) $ordenTrabajo->estado === OrdenesTrabajo::ESTADO_CANCELADA) {
            throw ValidationException::withMessages([
                'orden' => 'No se puede liquidar oro de una orden cancelada.',
            ]);
        }

        $datos = $this->validarLote($request);
        $acuerdo = $this->acuerdoPorcentual($ordenTrabajo, (int) $datos['acuerdo_id']);

        $liquidacion = new Liquidacione([
            Liquidacione::ORDEN_TRABAJO_ID => $ordenTrabajo->id,
            Liquidacione::FECHA => $datos['fecha'],
            Liquidacione::OBSERVACIONES => filled($datos['observaciones'] ?? null) ? $datos['observaciones'] : null,
        ]);
        $liquidacion->calcularReparto(
            (float) $datos['gramos_recuperados'],
            (float) $acuerdo->porcentaje_cliente,
            (float) $acuerdo->porcentaje_empresa
        )->save();

        return response()->json(['ok' => true, 'id' => $liquidacion->id]);
    }

    public function show(OrdenesTrabajo $ordenTrabajo, Liquidacione $liquidacion)
    {
        $this->validarPertenencia($ordenTrabajo, $liquidacion);

        return response()->json([
            'id' => $liquidacion->id,
            'fecha' => $liquidacion->fecha?->format('Y-m-d'),
            'gramos_recuperados' => $liquidacion->gramos_recuperados,
            'gramos_cliente' => $liquidacion->gramos_cliente,
            'gramos_empresa' => $liquidacion->gramos_empresa,
            'porcentaje_cliente' => $liquidacion->porcentaje_cliente,
            'porcentaje_empresa' => $liquidacion->porcentaje_empresa,
            'observaciones' => $liquidacion->observaciones,
        ]);
    }

    public function update(Request $request, OrdenesTrabajo $ordenTrabajo, Liquidacione $liquidacion)
    {
        $this->validarPertenencia($ordenTrabajo, $liquidacion);
        $datos = $this->validarLote($request, false);

        $liquidacion->fecha = $datos['fecha'];
        $liquidacion->observaciones = filled($datos['observaciones'] ?? null) ? $datos['observaciones'] : null;
        if (abs(round((float) $liquidacion->porcentaje_cliente + (float) $liquidacion->porcentaje_empresa, 4) - 100) > 0.0001) {
            throw ValidationException::withMessages([
                'porcentajes' => 'Esta liquidación no conserva porcentajes válidos. No se modificó su reparto histórico.',
            ]);
        }

        $liquidacion->calcularReparto(
            (float) $datos['gramos_recuperados'],
            (float) $liquidacion->porcentaje_cliente,
            (float) $liquidacion->porcentaje_empresa
        )->save();

        return response()->json(['ok' => true, 'id' => $liquidacion->id]);
    }

    public function destroy(OrdenesTrabajo $ordenTrabajo, Liquidacione $liquidacion)
    {
        $this->validarPertenencia($ordenTrabajo, $liquidacion);
        $liquidacion->delete();

        return response()->json(['ok' => true]);
    }

    private function validarLote(Request $request, bool $conAcuerdo = true): array
    {
        $reglas = [
            'fecha' => ['required', 'date'],
            'gramos_recuperados' => ['required', 'numeric', 'gt:0', 'max:99999999.9999'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
        ];
        if ($conAcuerdo) {
            $reglas['acuerdo_id'] = ['required', 'integer', Rule::exists('acuerdos_orden', 'id')->whereNull('deleted_at')];
        }

        $datos = $request->validate($reglas, [
            'fecha.required' => 'Indique la fecha de la liquidación.',
            'gramos_recuperados.required' => 'Indique los gramos del lote que se liquida.',
            'gramos_recuperados.gt' => 'Los gramos liquidados deben ser mayores que cero.',
            'gramos_recuperados.max' => 'Los gramos superan el máximo que permite el sistema.',
            'acuerdo_id.required' => 'Seleccione el acuerdo porcentual que se aplica a este lote.',
            'acuerdo_id.exists' => 'El acuerdo seleccionado ya no está disponible.',
        ]);

        return $datos;
    }

    private function acuerdoPorcentual(OrdenesTrabajo $ordenTrabajo, int $acuerdoId): AcuerdosOrden
    {
        $acuerdo = AcuerdosOrden::query()
            ->deOrden($ordenTrabajo->id)
            ->whereKey($acuerdoId)
            ->where(AcuerdosOrden::ESTADO, true)
            ->whereNotNull(AcuerdosOrden::PORCENTAJE_CLIENTE)
            ->whereNotNull(AcuerdosOrden::PORCENTAJE_EMPRESA)
            ->whereNull(AcuerdosOrden::TARIFA_SERVICIO)
            ->with('tipo_participacion')
            ->first();

        if (! $acuerdo) {
            throw ValidationException::withMessages([
                'acuerdo_id' => 'Elija un acuerdo porcentual activo de esta orden. Las tarifas de servicio no distribuyen gramos.',
            ]);
        }

        if (abs(round((float) $acuerdo->porcentaje_cliente + (float) $acuerdo->porcentaje_empresa, 4) - 100) > 0.0001) {
            throw ValidationException::withMessages([
                'acuerdo_id' => 'El acuerdo no suma 100%. Corrija el acuerdo antes de liquidar con él.',
            ]);
        }

        return $acuerdo;
    }

    private function validarPertenencia(OrdenesTrabajo $ordenTrabajo, Liquidacione $liquidacion): void
    {
        abort_unless((int) $liquidacion->orden_trabajo_id === (int) $ordenTrabajo->id, 404);
    }
}

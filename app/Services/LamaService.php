<?php

namespace App\Services;

use App\Models\{AcuerdosOrden, Ingreso, LamaLote, Moneda, OrdenesTrabajo, Recuperaciones, UnidadMedida};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Procedencia y lotes completos: no mueve existencias de químicos ni inventa ingresos. */
class LamaService
{
    public static function condiciones(Request $request, ?AcuerdosOrden $actual = null): array
    {
        $datos = $request->validate([
            'lama_destino' => ['sometimes', Rule::in(['sin_definir', 'empresa', 'cliente'])],
            'lama_porcentaje_empresa' => ['nullable', 'numeric', 'between:0,100', 'decimal:0,4'],
        ]);
        $destino = $datos['lama_destino'] ?? $actual?->lama_destino ?? 'sin_definir';
        $porcentaje = array_key_exists('lama_porcentaje_empresa', $datos)
            ? $datos['lama_porcentaje_empresa'] : $actual?->lama_porcentaje_empresa;
        if ($destino !== 'sin_definir' && $porcentaje === null) {
            self::error('lama_porcentaje_empresa', 'Indique qué porcentaje del oro de la lama corresponde a la empresa (0 a 100). El resto corresponde al cliente.');
        }
        return ['lama_destino' => $destino, 'lama_porcentaje_empresa' => $destino === 'sin_definir' ? null : $porcentaje];
    }

    public function registrar(OrdenesTrabajo $origen, array $datos): LamaLote
    {
        return DB::transaction(function () use ($origen, $datos) {
            $origen = OrdenesTrabajo::lockForUpdate()->findOrFail($origen->id);
            if ($origen->estado == OrdenesTrabajo::ESTADO_CANCELADA || $origen->lama_lote_id) {
                self::error('orden', 'Registre el lote desde la orden original del servicio, que no debe estar cancelada.');
            }
            $acuerdo = AcuerdosOrden::where('orden_trabajo_id', $origen->id)->lockForUpdate()->findOrFail($datos['acuerdo_id']);
            if (!$acuerdo->estado || $acuerdo->lama_destino === 'sin_definir' || $acuerdo->lama_porcentaje_empresa === null) {
                self::error('acuerdo_id', 'Primero complete y active el acuerdo: quién conserva la lama y qué porcentaje del oro corresponde a la empresa.');
            }
            if ($datos['fecha'] < $origen->fecha->toDateString()) self::error('fecha', 'La fecha del lote no puede ser anterior a la orden original.');
            $unidad = UnidadMedida::where('estado', true)->findOrFail($datos['unidad_id']);
            return LamaLote::create([
                ...$datos, 'orden_origen_id' => $origen->id, 'unidad' => $unidad->nombre,
                'destino' => $acuerdo->lama_destino, 'porcentaje_empresa' => $acuerdo->lama_porcentaje_empresa,
            ]);
        });
    }

    public static function reglasLote(): array
    {
        return [
            'acuerdo_id' => ['required', 'integer'], 'fecha' => ['required', 'date'],
            'cantidad_estimada' => ['required', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,3'],
            'unidad_id' => ['required', 'integer', 'exists:unidades_medida,id,estado,1'],
            'ubicacion' => ['required', 'string', 'max:200'], 'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function iniciar(LamaLote $lote, string $fecha): OrdenesTrabajo
    {
        return DB::transaction(function () use ($lote, $fecha) {
            // Mismo orden de bloqueo que registrar: OT de origen, después lote/acuerdo.
            $origen = OrdenesTrabajo::lockForUpdate()->findOrFail($lote->orden_origen_id);
            $lote = LamaLote::lockForUpdate()->findOrFail($lote->id);
            if ($origen->estado == OrdenesTrabajo::ESTADO_CANCELADA) self::error('lote', 'La orden de origen está cancelada.');
            if ($lote->destino !== 'empresa') self::error('lote', 'Este material lo conserva el cliente; no está disponible en la empresa para iniciar un tratamiento.');
            if ($lote->tratamiento()->withTrashed()->exists()) self::error('lote', 'Este lote completo ya tiene una OT de tratamiento. Abra esa orden; no vuelva a utilizar el lote.');
            if ($fecha < $lote->fecha->toDateString()) self::error('fecha', 'El tratamiento no puede ser anterior al registro del lote.');
            $acuerdo = $lote->acuerdo()->withTrashed()->firstOrFail();
            $orden = OrdenesTrabajo::create([
                'codigo' => 'OT-'.$lote->codigo, 'fecha' => $fecha,
                'tipo_trabajo' => 'tratamiento_lama', 'lama_lote_id' => $lote->id,
                'cliente_id' => $lote->porcentaje_empresa < 100 ? $origen->cliente_id : null,
                'descripcion' => 'Tratamiento del lote completo '.$lote->codigo.'; procedencia '.$origen->codigo.'. Cantidad estimada.',
                'peso_mineral' => $lote->cantidad_estimada, 'unidad_peso_id' => $lote->unidad_id,
                'unidad_peso' => $lote->unidad, 'estado' => OrdenesTrabajo::ESTADO_PENDIENTE,
            ]);
            AcuerdosOrden::create([
                'orden_trabajo_id' => $orden->id, 'tipo_participacion_id' => $acuerdo->tipo_participacion_id,
                'porcentaje_empresa' => $lote->porcentaje_empresa, 'porcentaje_cliente' => 100 - $lote->porcentaje_empresa,
                'moneda_id' => Moneda::base()?->id ?? $acuerdo->moneda_id, 'estado' => true,
                'observaciones' => 'Reparto heredado de '.$lote->codigo.'. No es una nueva tarifa de molienda.',
            ]);
            return $orden;
        });
    }

    public function corregir(LamaLote $lote, array $datos): void
    {
        DB::transaction(function () use ($lote, $datos) {
            $lote = LamaLote::lockForUpdate()->findOrFail($lote->id);
            // No cambia procedencia, acuerdo ni reparto histórico; para eso se anula y se registra otro lote sin tratamiento.
            $lote->update(collect($datos)->only(['cantidad_estimada', 'ubicacion', 'observaciones'])->all());
        });
    }

    public function anular(LamaLote $lote): void
    {
        DB::transaction(fn () => LamaLote::lockForUpdate()->findOrFail($lote->id)->delete());
    }

    public function datos(OrdenesTrabajo $orden, $usuario): array
    {
        $lotes = $orden->lama_lote_id
            ? LamaLote::with(['origen.cliente', 'tratamiento'])->whereKey($orden->lama_lote_id)->get()
            : LamaLote::with(['origen.cliente', 'tratamiento'])->where('orden_origen_id', $orden->id)->orderByDesc('id')->get();
        $finanzas = $usuario->can('ingresos.view') && $usuario->can('recuperaciones.view') && $usuario->can('valoraciones_oro.view')
            && $usuario->can('movimientos_costo.view') && $usuario->can('trabajos_empleado.view')
            && $usuario->can('proceso_equipo.view') && $usuario->can('movimientos_inventario.view');
        $verOro = $usuario->can('recuperaciones.view');
        $acuerdos = $orden->lama_lote_id ? collect() : AcuerdosOrden::deOrden($orden->id)->where('estado', true)
            ->where('lama_destino', '!=', 'sin_definir')->get();
        return [
            'order_id' => $orden->id, 'order_code' => $orden->codigo, 'is_treatment' => (bool) $orden->lama_lote_id,
            'can_register' => $usuario->can('ordenes_trabajo.edit') && !$orden->lama_lote_id && $orden->estado != 0,
            'can_start' => $usuario->can('ordenes_trabajo.create') && $usuario->can('ordenes_trabajo.edit') && $orden->estado != 0,
            'can_edit' => $usuario->can('ordenes_trabajo.edit'),
            'can_agree' => $usuario->can('acuerdos_orden.create') || $usuario->can('acuerdos_orden.edit'),
            'can_recover' => $usuario->can('recuperaciones.create') && !$orden->estaCerrada() && (bool) $orden->lama_lote_id,
            'can_value' => ($usuario->can('valoraciones_oro.create') || $usuario->can('valoraciones_oro.edit')) && $orden->estado != 0 && (bool) $orden->lama_lote_id,
            'can_see_gold' => $verOro, 'can_see_finances' => $finanzas,
            'base_currency_id' => Moneda::base()?->id,
            'currencies' => ($usuario->can('valoraciones_oro.create') || $usuario->can('valoraciones_oro.edit')) ? Moneda::where('estado', true)->orderBy('codigo')->get()->map(fn ($m) => ['id' => $m->id, 'name' => $m->codigo])->values() : [],
            'agreements' => $acuerdos->map(fn ($a) => ['id' => $a->id, 'label' => '#'.$a->id.' · Conserva: '.$a->lama_destino.' · Oro empresa '.$a->lama_porcentaje_empresa.'% / cliente '.(100 - $a->lama_porcentaje_empresa).'%'])->values(),
            'units' => UnidadMedida::where('estado', true)->orderBy('nombre')->get()->map(fn ($u) => ['id' => $u->id, 'name' => $u->nombre])->values(),
            'lots' => $lotes->map(function ($lote) use ($finanzas, $verOro, $usuario) {
                $tratamiento = $lote->tratamiento;
                $oro = $verOro && $tratamiento ? Recuperaciones::with('valoracion')->where('orden_trabajo_id', $tratamiento->id)->get() : collect();
                return [
                    'id' => $lote->id, 'code' => $lote->codigo, 'source_id' => $lote->orden_origen_id,
                    'source_code' => $lote->origen->codigo, 'date' => $lote->fecha->toDateString(),
                    'estimated_quantity' => $lote->cantidad_estimada, 'unit' => $lote->unidad, 'location' => $lote->ubicacion,
                    'holder' => $lote->destino, 'company_percentage' => $lote->porcentaje_empresa,
                    'client_percentage' => round(100 - $lote->porcentaje_empresa, 4), 'observations' => $lote->observaciones,
                    'treatment_id' => $tratamiento?->id, 'treatment_code' => $tratamiento?->codigo,
                    'status' => $tratamiento ? 'Tratamiento '.$tratamiento->estadoTexto() : ($lote->destino === 'empresa' ? 'Disponible completo' : 'En poder del cliente'),
                    'available_quantity' => $tratamiento || $lote->destino !== 'empresa' ? 0 : $lote->cantidad_estimada,
                    'recovered_grams' => $verOro ? round((float) $oro->sum('gramos'), 4) : null,
                    'company_grams' => $verOro ? round((float) $oro->sum('gramos') * $lote->porcentaje_empresa / 100, 4) : null,
                    'recoveries' => $oro->map(fn ($r) => ['id' => $r->id, 'date' => $r->fecha->toDateString(), 'grams' => (float) $r->gramos,
                        'purity' => $r->pureza === null ? null : (float) $r->pureza, 'valued' => $r->valoracion !== null,
                        'needs_valuation' => !$r->valoracion || $r->valoracion->valor_nio === null,
                        'can_value' => $usuario->can($r->valoracion ? 'valoraciones_oro.edit' : 'valoraciones_oro.create')])->values(),
                    'result' => $finanzas && $tratamiento ? $this->resultado($tratamiento, $lote) : null,
                ];
            })->values(),
            'financial_summary' => $finanzas && !$orden->lama_lote_id && $lotes->isNotEmpty() ? $this->resumenOrigen($orden, $lotes) : null,
        ];
    }

    private function costo(OrdenesTrabajo $orden): array
    {
        $d = app(DistribucionGastosGenerales::class)->paraOrden($orden);
        $equiposPendientes = \App\Models\ProcesoEquipo::whereIn('proceso_orden_id', $d['procesos']->modelKeys())
            ->get()->contains(fn ($uso) => $uso->depreciacion_aplicada !== null
                && ($uso->horas_efectivas === null || (($uso->recursos_estimados ?? []) !== [] && !$uso->consumos_confirmados)));
        return ['amount' => round($d['costo_directo_total_nio'] + $d['gastos_generales_nio'], 2),
            'complete' => !$equiposPendientes && $d['registros_directos_sin_equivalente'] + $d['gastos_generales_sin_equivalente'] === 0];
    }

    private function resultado(OrdenesTrabajo $orden, LamaLote $lote): array
    {
        $oro = Recuperaciones::with('valoracion')->where('orden_trabajo_id', $orden->id)->get();
        $pendientes = $oro->filter(fn ($r) => !$r->valoracion || $r->valoracion->valor_nio === null)->count();
        $valor = round((float) $oro->sum(fn ($r) => $r->valoracion?->valor_nio ?? 0) * $lote->porcentaje_empresa / 100, 2);
        $costo = $this->costo($orden);
        $completo = $oro->isNotEmpty() && $pendientes === 0 && $costo['complete'] && $orden->estado != 0;
        return ['company_gold_value_nio' => $valor, 'treatment_cost_nio' => $costo['amount'], 'pending_valuations' => $pendientes,
            'complete' => $completo, 'reference_result_nio' => $completo ? round($valor - $costo['amount'], 2) : null];
    }

    private function resumenOrigen(OrdenesTrabajo $orden, $lotes): array
    {
        $ingresos = Ingreso::deOrden($orden->id)->get();
        $costo = $this->costo($orden);
        $tratamientos = $lotes->filter(fn ($l) => $l->tratamiento !== null);
        $resultados = $tratamientos->map(fn ($l) => $this->resultado($l->tratamiento, $l));
        $valor = round((float) $resultados->sum('company_gold_value_nio'), 2);
        $costosTratamiento = round((float) $resultados->sum('treatment_cost_nio'), 2);
        $pendientes = $lotes->where('destino', 'empresa')->filter(fn ($l) => !$l->tratamiento)->count();
        $completo = $lotes->isNotEmpty() && $pendientes === 0 && $resultados->every(fn ($r) => $r['complete'])
            && $costo['complete'] && $ingresos->whereNull('total_nio')->isEmpty() && $orden->estado != 0;
        return ['service_income_nio' => round((float) $ingresos->sum('total_nio'), 2), 'service_cost_nio' => $costo['amount'],
            'company_gold_value_nio' => $valor, 'treatment_cost_nio' => $costosTratamiento, 'pending_lots' => $pendientes,
            'complete' => $completo, 'reference_result_nio' => $completo ? round((float) $ingresos->sum('total_nio') + $valor - $costo['amount'] - $costosTratamiento, 2) : null];
    }

    private static function error(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}

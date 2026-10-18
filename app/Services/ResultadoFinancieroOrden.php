<?php

namespace App\Services;

use App\Models\AcuerdosOrden;
use App\Models\Ingreso;
use App\Models\Liquidacione;
use App\Models\Moneda;
use App\Models\OrdenesTrabajo;
use App\Models\PreciosOro;
use App\Models\ProcesoEquipo;
use App\Models\TiposCambio;
use Illuminate\Support\Facades\DB;

/** Resultado económico conocido y referencia contractual de una OT, en NIO. */
class ResultadoFinancieroOrden
{
    public function __construct(private readonly DistribucionGastosGenerales $distribuidor)
    {
    }

    public function paraOrden(OrdenesTrabajo $orden): array
    {
        $base = Moneda::base();
        $distribucion = $this->distribuidor->paraOrden($orden);
        $costo = round($distribucion['costo_directo_total_nio'] + $distribucion['gastos_generales_nio'], 2);

        $equiposPendientes = ProcesoEquipo::query()
            ->whereIn('proceso_orden_id', $distribucion['procesos']->modelKeys())
            ->get()
            ->contains(fn (ProcesoEquipo $uso) => $uso->depreciacion_aplicada !== null
                && ($uso->horas_efectivas === null
                    || (($uso->recursos_estimados ?? []) !== [] && ! $uso->consumos_confirmados)));

        $costosPendientes = $distribucion['registros_directos_sin_equivalente']
            + $distribucion['gastos_generales_sin_equivalente'];

        $ingresos = Ingreso::deOrden($orden->id)->get();
        $ingresosRegistrados = round((float) $ingresos->sum('total_nio'), 2);
        $ingresosPendientes = $ingresos->whereNull('total_nio')->count();

        $tarifaPactada = 0.0;
        $tarifasPendientes = 0;
        $acuerdosTarifa = AcuerdosOrden::query()->deOrden($orden->id)
            ->where(AcuerdosOrden::ESTADO, true)
            ->whereNotNull(AcuerdosOrden::TARIFA_SERVICIO)
            ->with('moneda')->get();

        foreach ($acuerdosTarifa as $acuerdo) {
            $moneda = $acuerdo->moneda;
            $cambio = $moneda?->es_moneda_base
                ? 1.0
                : ($moneda ? TiposCambio::vigentePara((int) $moneda->id, $orden->fecha->toDateString()) : null);

            if ($cambio === null) {
                $tarifasPendientes++;
                continue;
            }

            $tarifaPactada += (float) $acuerdo->tarifa_servicio * $cambio;
        }
        $tarifaPactada = round($tarifaPactada, 2);

        $liquidaciones = Liquidacione::query()->deOrden($orden->id)->orderBy('fecha')->orderBy('id')->get();
        $valorOroEmpresa = 0.0;
        $oroPendiente = 0;
        $gramosEmpresa = 0.0;

        foreach ($liquidaciones as $liquidacion) {
            $gramos = (float) $liquidacion->gramos_empresa;
            $gramosEmpresa += $gramos;
            if ($gramos <= 0) {
                continue;
            }

            $valor = $this->valorGramosEnNio($gramos, $liquidacion->fecha->toDateString(), $base);
            if ($valor === null) {
                $oroPendiente++;
                continue;
            }

            $valorOroEmpresa += $valor;
        }
        $valorOroEmpresa = round($valorOroEmpresa, 2);
        $gramosEmpresa = round($gramosEmpresa, 4);

        $ingresoEconomicoConocido = round($ingresosRegistrados + $valorOroEmpresa, 2);
        $ingresosProyectados = round($tarifaPactada + $valorOroEmpresa, 2);
        $hayIngresoReal = $ingresos->isNotEmpty() || $gramosEmpresa > 0;
        $hayReferenciaPactada = $acuerdosTarifa->isNotEmpty() || $gramosEmpresa > 0;

        $datosIncompletos = $costosPendientes > 0 || $equiposPendientes
            || $ingresosPendientes > 0 || $tarifasPendientes > 0 || $oroPendiente > 0;

        return [
            'moneda' => $base?->codigo ?? 'NIO',
            'modalidad' => $acuerdosTarifa->isNotEmpty() && $liquidaciones->isNotEmpty()
                ? 'tarifa_y_participacion'
                : ($acuerdosTarifa->isNotEmpty() ? 'tarifa' : ($liquidaciones->isNotEmpty() ? 'participacion' : 'sin_referencia')),
            'ingresos_registrados_nio' => $ingresosRegistrados,
            'ingresos_registrados_cantidad' => $ingresos->count(),
            'oro_empresa_gramos' => $gramosEmpresa,
            'oro_empresa_nio' => $valorOroEmpresa,
            'oro_pendiente_valoracion' => $oroPendiente,
            'tarifa_pactada_nio' => $tarifaPactada,
            'tarifas_pendientes_conversion' => $tarifasPendientes,
            'ingreso_real_conocido_nio' => $ingresoEconomicoConocido,
            'ingreso_proyectado_nio' => $ingresosProyectados,
            'costo_total_nio' => $costo,
            'resultado_real_nio' => $hayIngresoReal ? round($ingresoEconomicoConocido - $costo, 2) : null,
            'resultado_proyectado_nio' => $hayReferenciaPactada ? round($ingresosProyectados - $costo, 2) : null,
            'costos_pendientes_equivalente' => $costosPendientes,
            'equipos_pendientes' => $equiposPendientes,
            'ingresos_pendientes_equivalente' => $ingresosPendientes,
            'completo' => ! $datosIncompletos,
            'datos_incompletos' => $datosIncompletos,
        ];
    }

    private function valorGramosEnNio(float $gramos, string $fecha, ?Moneda $base): ?float
    {
        if ($base === null) {
            return null;
        }

        $monedasConPrecio = DB::table('precios_oro')
            ->whereNull('deleted_at')
            ->whereDate('fecha', '<=', $fecha)
            ->where('precio', '>', 0)
            ->distinct()
            ->pluck('moneda_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        usort($monedasConPrecio, fn (int $a, int $b) => ($a === (int) $base->id ? -1 : 0) <=> ($b === (int) $base->id ? -1 : 0) ?: ($a <=> $b));

        foreach ($monedasConPrecio as $monedaId) {
            $precio = PreciosOro::precioDelGramo($fecha, $monedaId);
            if ($precio === null) {
                continue;
            }

            $tipoCambio = $monedaId === (int) $base->id
                ? 1.0
                : TiposCambio::vigentePara($monedaId, $fecha);
            if ($tipoCambio === null) {
                continue;
            }

            return round($gramos * $precio['gramo'] * $tipoCambio, 2);
        }

        return null;
    }
}

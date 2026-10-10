<?php

namespace App\Services;

use App\Models\MovimientosCosto;
use App\Models\MovimientosInventario;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesoEquipo;
use App\Models\ProcesosOrden;
use App\Models\TrabajosEmpleado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Calcula, sin guardar movimientos nuevos, la parte de gastos generales que
 * corresponde a cada proceso según sus costos directos consolidados en NIO.
 */
class DistribucionGastosGenerales
{
    /**
     * @return array{
     *     por_proceso: array<int, array{costo_directo_nio: float, proporcion: float, gasto_general_asignado_nio: float, costo_total_con_generales_nio: float}>,
     *     procesos: Collection<int, ProcesosOrden>,
     *     costo_directo_total_nio: float,
     *     gastos_generales_nio: float,
     *     gastos_generales_distribuidos_nio: float,
     *     gastos_generales_pendientes_nio: float,
     *     registros_directos_sin_equivalente: int,
     *     gastos_generales_sin_equivalente: int,
     *     base_directa_completa: bool,
     *     motivo_pendiente: ?string
     * }
     */
    public function paraOrden(OrdenesTrabajo $orden): array
    {
        $procesos = ProcesosOrden::query()
            ->where(ProcesosOrden::ORDEN_TRABAJO_ID, $orden->id)
            ->orderBy(ProcesosOrden::ID)
            ->get([ProcesosOrden::ID, ProcesosOrden::ORDEN_TRABAJO_ID]);

        $ids = $procesos->modelKeys();
        $baseDirecta = array_fill_keys($ids, 0.0);

        if ($ids !== []) {
            $this->sumasPorProceso(
                TrabajosEmpleado::withTrashed()->whereIn(TrabajosEmpleado::PROCESO_ORDEN_ID, $ids),
                TrabajosEmpleado::PROCESO_ORDEN_ID,
                TrabajosEmpleado::TOTAL_NIO,
                $baseDirecta
            );

            $this->sumasPorProceso(
                ProcesoEquipo::query()->whereIn(ProcesoEquipo::PROCESO_ORDEN_ID, $ids),
                ProcesoEquipo::PROCESO_ORDEN_ID,
                ProcesoEquipo::DEPRECIACION_TOTAL,
                $baseDirecta
            );

            $this->sumasPorProceso(
                MovimientosCosto::query()->whereIn(MovimientosCosto::PROCESO_ORDEN_ID, $ids),
                MovimientosCosto::PROCESO_ORDEN_ID,
                'COALESCE(costo_total_nio, cantidad * costo_unitario_nio, 0)',
                $baseDirecta
            );

            $this->sumasPorProceso(
                MovimientosInventario::query()
                    ->whereIn(MovimientosInventario::PROCESO_ORDEN_ID, $ids)
                    ->where(MovimientosInventario::TIPO, MovimientosInventario::TIPO_SALIDA),
                MovimientosInventario::PROCESO_ORDEN_ID,
                'COALESCE(costo_total_nio, cantidad * costo_unitario_nio, 0)',
                $baseDirecta
            );
        }

        $registrosDirectosSinEquivalente = $ids === [] ? 0 : $this->contarDirectosSinEquivalente($ids);

        // Para las filas antiguas sin total guardado se usa el unitario NIO guardado.
        $gastosGenerales = MovimientosCosto::query()
            ->generalesDe($orden->id)
            ->sum(DB::raw('COALESCE(costo_total_nio, cantidad * costo_unitario_nio, 0)'));

        $gastosGeneralesSinEquivalente = MovimientosCosto::query()
            ->generalesDe($orden->id)
            ->whereNull(MovimientosCosto::COSTO_TOTAL_NIO)
            ->whereNull(MovimientosCosto::COSTO_UNITARIO_NIO)
            ->whereHas('moneda', fn ($query) => $query->where('es_moneda_base', false))
            ->count();

        $costoDirectoTotal = round(array_sum($baseDirecta), 2);
        $gastosGenerales = round((float) $gastosGenerales, 2);
        $asignado = array_fill_keys($ids, 0.0);
        $motivoPendiente = null;

        if ($gastosGenerales > 0 && $registrosDirectosSinEquivalente > 0) {
            $motivoPendiente = 'Hay costos directos en moneda extranjera sin equivalente NIO; complete sus tipos de cambio históricos.';
        } elseif ($gastosGenerales > 0 && $costoDirectoTotal <= 0) {
            $motivoPendiente = 'La suma de los costos directos de los procesos es cero.';
        } elseif ($gastosGenerales > 0) {
            foreach ($baseDirecta as $procesoId => $costoDirecto) {
                $asignado[$procesoId] = round($gastosGenerales * $costoDirecto / $costoDirectoTotal, 2);
            }

            $residuo = round($gastosGenerales - array_sum($asignado), 2);
            if ($residuo !== 0.0 && $asignado !== []) {
                $idsOrdenados = array_keys($baseDirecta);
                usort($idsOrdenados, fn (int $a, int $b) => ($baseDirecta[$b] <=> $baseDirecta[$a]) ?: ($a <=> $b));
                $procesoMayorBase = $idsOrdenados[0];

                $asignado[$procesoMayorBase] = round($asignado[$procesoMayorBase] + $residuo, 2);
            }
        }

        $porProceso = [];
        foreach ($baseDirecta as $procesoId => $costoDirecto) {
            $gastoAsignado = $asignado[$procesoId];
            $porProceso[$procesoId] = [
                'costo_directo_nio' => round($costoDirecto, 2),
                'proporcion' => $costoDirecto > 0 && $costoDirectoTotal > 0
                    ? round($costoDirecto / $costoDirectoTotal, 8)
                    : 0.0,
                'gasto_general_asignado_nio' => $gastoAsignado,
                'costo_total_con_generales_nio' => round($costoDirecto + $gastoAsignado, 2),
            ];
        }

        $totalDistribuido = round(array_sum($asignado), 2);

        return [
            'por_proceso' => $porProceso,
            'procesos' => $procesos,
            'costo_directo_total_nio' => $costoDirectoTotal,
            'gastos_generales_nio' => $gastosGenerales,
            'gastos_generales_distribuidos_nio' => $totalDistribuido,
            'gastos_generales_pendientes_nio' => round($gastosGenerales - $totalDistribuido, 2),
            'registros_directos_sin_equivalente' => $registrosDirectosSinEquivalente,
            'gastos_generales_sin_equivalente' => $gastosGeneralesSinEquivalente,
            'base_directa_completa' => $registrosDirectosSinEquivalente === 0,
            'motivo_pendiente' => $motivoPendiente,
        ];
    }

    /**
     * @param  Builder  $query
     * @param  array<int, float>  $sumas
     */
    private function sumasPorProceso($query, string $columnaProceso, string $expresion, array &$sumas): void
    {
        $filas = $query
            ->selectRaw($columnaProceso.' AS proceso_id, SUM('.$expresion.') AS importe')
            ->groupBy($columnaProceso)
            ->get();

        foreach ($filas as $fila) {
            $id = (int) $fila->proceso_id;
            $sumas[$id] = ($sumas[$id] ?? 0.0) + (float) $fila->importe;
        }
    }

    /** @param array<int, int> $ids */
    private function contarDirectosSinEquivalente(array $ids): int
    {
        $monedaExtranjera = fn ($query) => $query->where('es_moneda_base', false);

        return TrabajosEmpleado::withTrashed()
            ->whereIn(TrabajosEmpleado::PROCESO_ORDEN_ID, $ids)
            ->whereNull(TrabajosEmpleado::TOTAL_NIO)
            ->whereHas('moneda', $monedaExtranjera)
            ->count()
            + MovimientosCosto::query()
                ->whereIn(MovimientosCosto::PROCESO_ORDEN_ID, $ids)
                ->whereNull(MovimientosCosto::COSTO_TOTAL_NIO)
                ->whereNull(MovimientosCosto::COSTO_UNITARIO_NIO)
                ->whereHas('moneda', $monedaExtranjera)
                ->count()
            + MovimientosInventario::query()
                ->whereIn(MovimientosInventario::PROCESO_ORDEN_ID, $ids)
                ->where(MovimientosInventario::TIPO, MovimientosInventario::TIPO_SALIDA)
                ->whereNull(MovimientosInventario::COSTO_TOTAL_NIO)
                ->whereNull(MovimientosInventario::COSTO_UNITARIO_NIO)
                ->whereHas('moneda', $monedaExtranjera)
                ->count();
    }
}

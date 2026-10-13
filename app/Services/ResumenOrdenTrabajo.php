<?php

namespace App\Services;

use App\Models\Ingreso;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesoEquipo;
use App\Models\ProcesosOrden;

/** Datos consolidados para la vista de seguimiento de una orden. */
class ResumenOrdenTrabajo
{
    public function __construct(private readonly DistribucionGastosGenerales $distribuidor)
    {
    }

    public function paraOrden(OrdenesTrabajo $orden): array
    {
        $distribucion = $this->distribuidor->paraOrden($orden);
        $procesos = ProcesosOrden::query()->with('etapa')
            ->where(ProcesosOrden::ORDEN_TRABAJO_ID, $orden->id)
            ->orderBy(ProcesosOrden::ID)->get();
        $equipos = ProcesoEquipo::query()->with('equipo')
            ->whereIn(ProcesoEquipo::PROCESO_ORDEN_ID, $procesos->modelKeys())
            ->orderBy(ProcesoEquipo::FECHA_INICIO)->get()
            ->groupBy(ProcesoEquipo::PROCESO_ORDEN_ID);
        $porProceso = $distribucion['por_proceso'];
        $ingresos = Ingreso::deOrden($orden->id)->get();

        return [
            'orden' => $orden,
            'procesos' => $procesos->map(function (ProcesosOrden $proceso) use ($porProceso, $equipos) {
                $costos = $porProceso[$proceso->id] ?? [
                    'costo_directo_nio' => 0.0,
                    'gasto_general_asignado_nio' => 0.0,
                    'costo_total_con_generales_nio' => 0.0,
                ];

                return [
                    'proceso' => $proceso,
                    'costo_directo_nio' => $costos['costo_directo_nio'],
                    'gasto_general_asignado_nio' => $costos['gasto_general_asignado_nio'],
                    'costo_total_nio' => $costos['costo_total_con_generales_nio'],
                    'equipos' => $equipos->get($proceso->id, collect()),
                ];
            }),
            'costos' => [
                'directos_nio' => $distribucion['costo_directo_total_nio'],
                'generales_nio' => $distribucion['gastos_generales_nio'],
                'generales_distribuidos_nio' => $distribucion['gastos_generales_distribuidos_nio'],
                'generales_pendientes_nio' => $distribucion['gastos_generales_pendientes_nio'],
                'total_conocido_nio' => round(
                    $distribucion['costo_directo_total_nio'] + $distribucion['gastos_generales_nio'],
                    2
                ),
                'directos_sin_equivalente' => $distribucion['registros_directos_sin_equivalente'],
                'generales_sin_equivalente' => $distribucion['gastos_generales_sin_equivalente'],
                'base_directa_completa' => $distribucion['base_directa_completa'],
                'motivo_pendiente' => $distribucion['motivo_pendiente'],
            ],
            'ingresos' => [
                'total_nio' => round((float) $ingresos->sum('total_nio'), 2),
                'cantidad' => $ingresos->count(),
                'sin_equivalente' => $ingresos->whereNull('total_nio')->count(),
            ],
            'estado_procesos' => $procesos->countBy(fn (ProcesosOrden $proceso) => $proceso->estadoTexto()),
        ];
    }
}

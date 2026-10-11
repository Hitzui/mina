<?php

namespace App\Services;

use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use Illuminate\Support\Facades\DB;

class FinalizarOrdenTrabajo
{
    /**
     * @return array{success: bool, message: string}
     */
    public function ejecutar(OrdenesTrabajo $orden): array
    {
        return DB::transaction(function () use ($orden): array {
            $orden = OrdenesTrabajo::query()->lockForUpdate()->findOrFail($orden->id);

            if ((int) $orden->estado === OrdenesTrabajo::ESTADO_FINALIZADA) {
                return ['success' => true, 'message' => 'La orden de trabajo ya está finalizada.'];
            }

            if ((int) $orden->estado === OrdenesTrabajo::ESTADO_CANCELADA) {
                return ['success' => false, 'message' => 'No se puede finalizar una orden que está cancelada.'];
            }

            $procesos = $orden->procesos_ordenes()->lockForUpdate()->get();

            if ($procesos->isEmpty()) {
                return ['success' => false, 'message' => 'No se puede finalizar la orden: primero debe registrar al menos un proceso.'];
            }

            $procesosPendientes = $procesos->filter(
                fn (ProcesosOrden $proceso) => $proceso->estadoCalculado() !== ProcesosOrden::ESTADO_FINALIZADO
            );

            if ($procesosPendientes->isNotEmpty()) {
                $codigos = $procesosPendientes
                    ->map(fn (ProcesosOrden $proceso) => $proceso->codigo.' ('.$proceso->estadoTexto().')')
                    ->join(', ');
                $cantidad = $procesosPendientes->count();
                $plural = $cantidad === 1 ? 'proceso no está finalizado' : 'procesos no están finalizados';

                return [
                    'success' => false,
                    'message' => "No se puede finalizar la orden: {$cantidad} {$plural}. {$codigos}.",
                ];
            }

            $orden->update(['estado' => OrdenesTrabajo::ESTADO_FINALIZADA]);

            return ['success' => true, 'message' => 'Orden de trabajo finalizada correctamente.'];
        });
    }
}

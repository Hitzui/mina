<?php

namespace App\Http\Controllers\Procesos;

use App\Http\Controllers\Controller;
use App\Models\OrdenesTrabajo;
use Barryvdh\DomPDF\Facade\Pdf;

class OrdenTrabajoPdfController extends Controller
{
    public function __invoke(OrdenesTrabajo $ordenTrabajo)
    {
        $ordenTrabajo->load(['cliente', 'procesos_ordenes.etapa']);

        $procesos = $ordenTrabajo->procesos_ordenes
            ->sortBy(fn ($proceso) => sprintf(
                '%010d-%010d',
                $proceso->etapa?->orden ?? PHP_INT_MAX,
                $proceso->id
            ))
            ->values();

        return Pdf::loadView('procesos.ordenes_trabajo.pdf', [
            'ordenTrabajo' => $ordenTrabajo,
            'procesos' => $procesos,
            'renglonesEnBlanco' => $procesos->isEmpty() ? 8 : 0,
        ])
            ->setPaper('letter', 'portrait')
            ->download('orden-trabajo-'.$ordenTrabajo->codigo.'.pdf');
    }
}

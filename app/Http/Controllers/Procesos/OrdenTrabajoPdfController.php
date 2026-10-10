<?php

namespace App\Http\Controllers\Procesos;

use App\Http\Controllers\Controller;
use App\Models\Etapa;
use App\Models\OrdenesTrabajo;
use Barryvdh\DomPDF\Facade\Pdf;

class OrdenTrabajoPdfController extends Controller
{
    public function __invoke(OrdenesTrabajo $ordenTrabajo)
    {
        $ordenTrabajo->load('cliente');

        $etapas = Etapa::query()
            ->orderBy(Etapa::ORDEN)
            ->orderBy(Etapa::NOMBRE)
            ->get()
            ->values();

        return Pdf::loadView('procesos.ordenes_trabajo.pdf', [
            'ordenTrabajo' => $ordenTrabajo,
            'etapas' => $etapas,
            'renglonesEnBlanco' => $etapas->isEmpty() ? 8 : 5,
        ])
            ->setPaper('letter', 'portrait')
            ->download('orden-trabajo-'.$ordenTrabajo->codigo.'.pdf');
    }
}

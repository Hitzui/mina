<?php

namespace App\Services;

use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;

class CodigoProcesoOrden
{
    public function generar(OrdenesTrabajo $ordenTrabajo): string
    {
        $ultimo = ProcesosOrden::withTrashed()
            ->where('orden_trabajo_id', $ordenTrabajo->id)
            ->orderByDesc('id')
            ->first();

        $numero = 1;
        if ($ultimo) {
            $partes = explode('-', $ultimo->codigo);
            $numero = ((int) end($partes)) + 1;
        }

        return sprintf('P-%03d', $numero);
    }
}

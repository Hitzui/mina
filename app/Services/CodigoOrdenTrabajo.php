<?php

namespace App\Services;

use App\Models\OrdenesTrabajo;

class CodigoOrdenTrabajo
{
    public function generar(): string
    {
        $anio = now()->year;

        $ultimo = OrdenesTrabajo::withTrashed()
            ->where('codigo', 'like', "OT-{$anio}-%")
            ->orderByDesc('id')
            ->first();

        $numero = 1;
        if ($ultimo) {
            $partes = explode('-', $ultimo->codigo);
            $numero = ((int) end($partes)) + 1;
        }

        return sprintf('OT-%d-%04d', $anio, $numero);
    }
}

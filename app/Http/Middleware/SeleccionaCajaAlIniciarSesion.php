<?php

namespace App\Http\Middleware;

use App\Models\Cajas;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class SeleccionaCajaAlIniciarSesion
{
    public function handle(Request $request, Closure $next): Response
    {
        $cajaId = null;

        if ($request->routeIs('login.store')) {
            $datos = $request->validate([
                'caja_id' => [
                    'required',
                    'integer',
                    Rule::exists('cajas', 'id')->where(fn($query) => $query->where('estado', true)),
                ],
            ], [
                'caja_id.required' => 'Seleccione la caja que va a utilizar.',
                'caja_id.exists' => 'La caja seleccionada no está activa. Elija otra caja.',
            ]);

            $cajaId = (int) $datos['caja_id'];
        }

        if ($request->routeIs('two-factor.login.store')) {
            $cajaId = $request->session()->get('caja_pendiente_dos_factores_id');
        }

        $response = $next($request);

        if ($request->routeIs('login.store') && $cajaId !== null && ! Auth::check()
            && $response->isRedirect(route('two-factor.login'))) {
            $caja = Cajas::query()->find($cajaId);

            if ($caja && $caja->estado) {
                $request->session()->put('caja_pendiente_dos_factores_id', $caja->id);
                $request->session()->put('caja_pendiente_dos_factores_nombre', $caja->nombre);
            }
        }

        if ($cajaId !== null && Auth::check()) {
            $caja = Cajas::query()->find($cajaId);

            if ($caja && $caja->estado) {
                $request->session()->put('caja_seleccionada_id', $caja->id);
                $request->session()->put('caja_seleccionada_nombre', $caja->nombre);
                $request->session()->forget([
                    'caja_pendiente_dos_factores_id',
                    'caja_pendiente_dos_factores_nombre',
                ]);
            }
        }

        return $response;
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SeleccionaCajaAlIniciarSesion
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->routeIs('login.store', 'two-factor.login.store') || ! Auth::check()) {
            return $response;
        }

        $usuario = Auth::user();
        $caja = $usuario->caja;

        if (! $caja || ! $caja->estado) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Su usuario no tiene una caja activa asignada. Contacte a administración.',
            ]);
        }

        $request->session()->put('caja_seleccionada_id', $caja->id);
        $request->session()->put('caja_seleccionada_nombre', $caja->nombre);
        $request->session()->forget([
            'caja_pendiente_dos_factores_id',
            'caja_pendiente_dos_factores_nombre',
        ]);

        return $response;
    }
}

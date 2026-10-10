<?php

namespace App\Http\Controllers\Procesos;

use App\DataTables\CierresCajaDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Cajas;
use App\Models\CierreCaja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CierreCajaController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('cierres_caja');
    }

    public function index(CierresCajaDataTable $dataTable)
    {
        return $dataTable->render('procesos.cierres_caja.index', [
            'title' => 'Cierres de caja',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('home')],
                ['label' => 'Caja e Ingresos'],
                ['label' => 'Cierres de caja'],
            ],
        ]);
    }

    public function create(Request $request)
    {
        $filtros = $request->validate([
            'caja_id' => ['nullable', 'integer', 'exists:cajas,id'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
        ]);

        $preview = collect();
        if (! empty($filtros['caja_id']) && ! empty($filtros['fecha_desde']) && ! empty($filtros['fecha_hasta'])) {
            $preview = $this->resumenCobros($filtros['caja_id'], $filtros['fecha_desde'], $filtros['fecha_hasta']);
        }

        return view('procesos.cierres_caja.create', [
            'title' => 'Nuevo cierre de caja',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('home')],
                ['label' => 'Cierres de caja', 'url' => route('procesos.cierres_caja.index')],
                ['label' => 'Nuevo'],
            ],
            'cajas' => Cajas::query()->orderBy('nombre')->get(),
            'filtros' => $filtros,
            'preview' => $preview,
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'caja_id' => ['required', 'integer', 'exists:cajas,id'],
            'fecha_desde' => ['required', 'date'],
            'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'conteos' => ['required', 'array'],
            'conteos.*' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        $cierre = DB::transaction(function () use ($datos) {
            // Serializa los cierres simultáneos de una caja antes de comprobar solapamientos.
            Cajas::query()->whereKey($datos['caja_id'])->lockForUpdate()->firstOrFail();

            $traslape = CierreCaja::query()
                ->where('caja_id', $datos['caja_id'])
                ->whereDate('fecha_desde', '<=', $datos['fecha_hasta'])
                ->whereDate('fecha_hasta', '>=', $datos['fecha_desde'])
                ->exists();

            if ($traslape) {
                throw ValidationException::withMessages([
                    'fecha_desde' => 'Este período se cruza con otro cierre de la misma caja.',
                ]);
            }

            $resumen = $this->resumenCobros($datos['caja_id'], $datos['fecha_desde'], $datos['fecha_hasta']);
            $clavesEsperadas = $resumen->map(fn($fila) => $fila->metodo_pago_id . '|' . $fila->moneda_id)->all();
            if ($clavesEsperadas === [] || array_diff($clavesEsperadas, array_keys($datos['conteos'])) !== []) {
                throw ValidationException::withMessages([
                    'conteos' => 'Vuelva a calcular el período para cargar todos los métodos de pago y monedas.',
                ]);
            }

            $registro = CierreCaja::create([
                'codigo' => 'CC-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
                'caja_id' => $datos['caja_id'],
                'fecha_desde' => $datos['fecha_desde'],
                'fecha_hasta' => $datos['fecha_hasta'],
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            foreach ($resumen as $fila) {
                $clave = $fila->metodo_pago_id . '|' . $fila->moneda_id;
                $esperado = round((float) $fila->monto_esperado, 2);
                $contado = round((float) $datos['conteos'][$clave], 2);

                $registro->detalles()->create([
                    'metodo_pago_id' => $fila->metodo_pago_id,
                    'moneda_id' => $fila->moneda_id,
                    'monto_esperado' => $esperado,
                    'monto_contado' => $contado,
                    'diferencia' => round($contado - $esperado, 2),
                ]);
            }

            return $registro;
        });

        return redirect()->route('procesos.cierres_caja.show', $cierre)
            ->with('success', 'Cierre de caja registrado correctamente.');
    }

    public function show(CierreCaja $cierreCaja)
    {
        $cierreCaja->load(['caja', 'detalles.metodoPago', 'detalles.moneda']);

        return view('procesos.cierres_caja.show', [
            'title' => 'Cierre ' . $cierreCaja->codigo,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('home')],
                ['label' => 'Cierres de caja', 'url' => route('procesos.cierres_caja.index')],
                ['label' => $cierreCaja->codigo],
            ],
            'cierre' => $cierreCaja,
        ]);
    }

    private function resumenCobros(int|string $cajaId, string $desde, string $hasta)
    {
        return DB::table('cobros')
            ->join('detalle_cobros', function ($join) {
                $join->on('detalle_cobros.cobro_id', '=', 'cobros.id')
                    ->whereNull('detalle_cobros.deleted_at');
            })
            ->join('metodos_pago', 'metodos_pago.id', '=', 'cobros.metodo_pago_id')
            ->join('monedas', 'monedas.id', '=', 'cobros.moneda_id')
            ->whereNull('cobros.deleted_at')
            ->where('cobros.estado', 3)
            ->where('cobros.caja_id', $cajaId)
            ->whereBetween('cobros.fecha', [$desde, $hasta])
            ->groupBy('cobros.metodo_pago_id', 'cobros.moneda_id', 'metodos_pago.nombre', 'monedas.codigo')
            ->selectRaw('cobros.metodo_pago_id, cobros.moneda_id, metodos_pago.nombre AS metodo, monedas.codigo AS moneda, ROUND(SUM(detalle_cobros.monto), 2) AS monto_esperado')
            ->orderBy('metodos_pago.nombre')
            ->orderBy('monedas.codigo')
            ->get();
    }
}

<?php

namespace App\Http\Controllers\Procesos;

use App\DataTables\MovimientosCajaDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Cajas;
use App\Models\DetalleCierreCaja;
use App\Models\MetodosPagos;
use App\Models\Moneda;
use App\Models\MovimientoCaja;
use App\Services\MovimientoCajaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MovimientoCajaController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('movimientos_caja');
    }

    public function index(MovimientosCajaDataTable $dataTable)
    {
        $saldos = MovimientoCaja::query()
            ->join('cajas', 'cajas.id', '=', 'movimientos_caja.caja_id')
            ->join('monedas', 'monedas.id', '=', 'movimientos_caja.moneda_id')
            ->groupBy('cajas.id', 'cajas.nombre', 'monedas.id', 'monedas.codigo')
            ->selectRaw('cajas.nombre AS caja, monedas.codigo AS moneda, ROUND(SUM(CASE WHEN movimientos_caja.tipo = ? THEN movimientos_caja.monto ELSE -movimientos_caja.monto END), 2) AS saldo', [MovimientoCaja::ENTRADA])
            ->orderBy('cajas.nombre')->orderBy('monedas.codigo')->get();

        return $dataTable->render('procesos.movimientos_caja.index', [
            'title' => 'Movimientos de caja',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('home')],
                ['label' => 'Caja e Ingresos'],
                ['label' => 'Movimientos de caja'],
            ],
            'saldos' => $saldos,
        ]);
    }

    public function create()
    {
        return view('procesos.movimientos_caja.create', [
            'title' => 'Registrar movimiento de caja',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('home')],
                ['label' => 'Movimientos de caja', 'url' => route('procesos.movimientos_caja.index')],
                ['label' => 'Nuevo movimiento'],
            ],
            'cajas' => Cajas::query()->orderBy('nombre')->get(),
            'metodos' => MetodosPagos::query()->where('estado', true)->orderBy('nombre')->get(),
            'monedas' => Moneda::query()->where('estado', true)->orderBy('codigo')->get(),
        ]);
    }

    public function store(Request $request, MovimientoCajaService $movimientos)
    {
        $datos = $request->validate([
            'accion' => ['required', 'in:entrada,salida,saldo_inicial,transferencia'],
            'fecha' => ['required', 'date'],
            'caja_id' => ['required_unless:accion,transferencia', 'nullable', 'integer', 'exists:cajas,id'],
            'caja_origen_id' => ['required_if:accion,transferencia', 'nullable', 'integer', 'exists:cajas,id'],
            'caja_destino_id' => ['required_if:accion,transferencia', 'nullable', 'different:caja_origen_id', 'integer', 'exists:cajas,id'],
            'metodo_pago_id' => ['required', 'integer', 'exists:metodos_pago,id'],
            'moneda_id' => ['required', 'integer', 'exists:monedas,id'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'descripcion' => ['required_unless:accion,transferencia', 'nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($datos, $movimientos) {
            $metodoActivo = MetodosPagos::query()->whereKey($datos['metodo_pago_id'])
                ->where('estado', true)->exists();
            $monedaActiva = Moneda::query()->whereKey($datos['moneda_id'])
                ->where('estado', true)->exists();

            if (! $metodoActivo || ! $monedaActiva) {
                throw ValidationException::withMessages([
                    'metodo_pago_id' => 'Seleccione un método de pago y una moneda activos.',
                ]);
            }

            if ($datos['accion'] === 'transferencia') {
                $idsCajas = [$datos['caja_origen_id'], $datos['caja_destino_id']];
                sort($idsCajas);
                $cajas = Cajas::query()->whereIn('id', $idsCajas)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $origen = $cajas->get($datos['caja_origen_id']);
                $destino = $cajas->get($datos['caja_destino_id']);

                if (! $origen || ! $destino || ! $origen->estado || ! $destino->estado) {
                    throw ValidationException::withMessages(['caja_origen_id' => 'Las dos cajas deben estar activas.']);
                }

                $movimientos->transferencia([
                    ...$datos,
                    'caja_origen_nombre' => $origen->nombre,
                    'caja_destino_nombre' => $destino->nombre,
                ]);

                return;
            }

            $caja = Cajas::query()->whereKey($datos['caja_id'])->lockForUpdate()->firstOrFail();
            if (! $caja->estado) {
                throw ValidationException::withMessages(['caja_id' => 'Seleccione una caja activa.']);
            }

            if ($datos['accion'] === 'saldo_inicial') {
                $existe = MovimientoCaja::query()
                    ->where('caja_id', $caja->id)
                    ->where('metodo_pago_id', $datos['metodo_pago_id'])
                    ->where('moneda_id', $datos['moneda_id'])
                    ->where('origen', MovimientoCaja::ORIGEN_SALDO_INICIAL)
                    ->exists();

                if ($existe) {
                    throw ValidationException::withMessages([
                        'accion' => 'Ya existe un saldo inicial para esta caja, método de pago y moneda.',
                    ]);
                }
            }

            $movimientos->registrar([
                'caja_id' => $caja->id,
                'fecha' => $datos['fecha'],
                'tipo' => $datos['accion'] === 'salida' ? MovimientoCaja::SALIDA : MovimientoCaja::ENTRADA,
                'metodo_pago_id' => $datos['metodo_pago_id'],
                'moneda_id' => $datos['moneda_id'],
                'monto' => round((float) $datos['monto'], 2),
                'descripcion' => $datos['descripcion'],
            ], $datos['accion'] === 'saldo_inicial'
                ? MovimientoCaja::ORIGEN_SALDO_INICIAL
                : MovimientoCaja::ORIGEN_MANUAL);
        });

        return redirect()->route('procesos.movimientos_caja.index')
            ->with('success', 'Movimiento registrado correctamente.');
    }
}

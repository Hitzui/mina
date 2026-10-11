<?php

namespace App\Http\Controllers\Procesos;

use App\DataTables\MovimientosCajaDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Cajas;
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
        $datosFormulario = $this->datosFormulario();
        $saldos = MovimientoCaja::query()
            ->join('cajas', 'cajas.id', '=', 'movimientos_caja.caja_id')
            ->join('monedas', 'monedas.id', '=', 'movimientos_caja.moneda_id')
            ->where('movimientos_caja.caja_id', auth()->user()?->caja_id ?? 0)
            ->groupBy('cajas.id', 'cajas.nombre', 'monedas.id', 'monedas.codigo')
            ->selectRaw('cajas.nombre AS caja, monedas.codigo AS moneda')
            ->selectRaw('ROUND(SUM(CASE WHEN movimientos_caja.origen = ? THEN movimientos_caja.monto ELSE 0 END), 2) AS saldo_inicial', [MovimientoCaja::ORIGEN_SALDO_INICIAL])
            ->selectRaw('ROUND(SUM(CASE WHEN movimientos_caja.tipo = ? AND movimientos_caja.origen <> ? THEN movimientos_caja.monto ELSE 0 END), 2) AS entradas', [MovimientoCaja::ENTRADA, MovimientoCaja::ORIGEN_SALDO_INICIAL])
            ->selectRaw('ROUND(SUM(CASE WHEN movimientos_caja.tipo = ? THEN movimientos_caja.monto ELSE 0 END), 2) AS salidas', [MovimientoCaja::SALIDA])
            ->selectRaw('ROUND(SUM(CASE WHEN movimientos_caja.tipo = ? THEN movimientos_caja.monto ELSE -movimientos_caja.monto END), 2) AS saldo_final', [MovimientoCaja::ENTRADA])
            ->orderBy('cajas.nombre')->orderBy('monedas.codigo')->get();

        return $dataTable->render('procesos.movimientos_caja.index', [
            'title' => 'Movimientos de caja',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('home')],
                ['label' => 'Caja e Ingresos'],
                ['label' => 'Movimientos de caja'],
            ],
            'saldos' => $saldos,
            ...$datosFormulario,
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
            ...$this->datosFormulario(),
        ]);
    }

    public function store(Request $request, MovimientoCajaService $movimientos)
    {
        $cajaAsignadaId = auth()->user()?->caja_id;
        $request->merge([
            'caja_id' => $cajaAsignadaId,
            'caja_origen_id' => $cajaAsignadaId,
        ]);

        $datos = $request->validate([
            'accion' => ['required', 'in:entrada,salida,saldo_inicial,transferencia,traslado_metodo'],
            'fecha' => ['required', 'date'],
            'caja_id' => ['required_unless:accion,transferencia', 'nullable', 'integer', 'exists:cajas,id'],
            'caja_origen_id' => ['required_if:accion,transferencia', 'nullable', 'integer', 'exists:cajas,id'],
            'caja_destino_id' => ['required_if:accion,transferencia', 'nullable', 'different:caja_origen_id', 'integer', 'exists:cajas,id'],
            'metodo_pago_id' => ['required', 'integer', 'exists:metodos_pago,id'],
            'metodo_destino_id' => ['required_if:accion,traslado_metodo', 'nullable', 'different:metodo_pago_id', 'integer', 'exists:metodos_pago,id'],
            'moneda_id' => ['required', 'integer', 'exists:monedas,id'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'descripcion' => ['required_unless:accion,transferencia,traslado_metodo', 'nullable', 'string', 'max:255'],
        ]);

        $datos['caja_id'] = $cajaAsignadaId;
        $datos['caja_origen_id'] = $cajaAsignadaId;

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

            if ($datos['accion'] === 'traslado_metodo') {
                $metodoDestinoActivo = MetodosPagos::query()
                    ->whereKey($datos['metodo_destino_id'])
                    ->where('estado', true)
                    ->exists();

                if (! $metodoDestinoActivo) {
                    throw ValidationException::withMessages([
                        'metodo_destino_id' => 'Seleccione un método de pago de destino activo.',
                    ]);
                }
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

                $this->validarSaldoDisponible($datos, $origen->nombre);

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

            if (in_array($datos['accion'], ['salida', 'traslado_metodo'], true)) {
                $this->validarSaldoDisponible($datos, $caja->nombre);
            }

            if ($datos['accion'] === 'traslado_metodo') {
                $movimientos->trasladoEntreMetodos([
                    ...$datos,
                    'caja_id' => $caja->id,
                    'caja_nombre' => $caja->nombre,
                ]);

                return;
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

    /**
     * Los egresos consumen el saldo de la combinación exacta caja/método/moneda.
     * Se llama con la caja bloqueada dentro de la transacción para impedir que
     * dos retiros simultáneos gasten el mismo saldo disponible.
     */
    private function validarSaldoDisponible(array $datos, string $nombreCaja): void
    {
        $saldo = (float) MovimientoCaja::query()
            ->where('caja_id', $datos['caja_origen_id'] ?? $datos['caja_id'])
            ->where('metodo_pago_id', $datos['metodo_pago_id'])
            ->where('moneda_id', $datos['moneda_id'])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN tipo = ? THEN monto ELSE -monto END), 0) AS saldo',
                [MovimientoCaja::ENTRADA]
            )
            ->value('saldo');
        $monto = round((float) $datos['monto'], 2);

        if ($monto > round($saldo, 2)) {
            $metodo = MetodosPagos::find($datos['metodo_pago_id'])?->nombre ?? 'método seleccionado';
            $moneda = Moneda::find($datos['moneda_id'])?->codigo ?? 'moneda seleccionada';

            throw ValidationException::withMessages([
                'monto' => sprintf(
                    'El movimiento supera el saldo disponible de %s %s en %s para %s (%s).',
                    $moneda,
                    number_format(max(0, $saldo), 2, '.', ','),
                    $nombreCaja,
                    $metodo,
                    $moneda
                ),
            ]);
        }
    }

    /** Opciones y saldo por método/moneda para el modal de la caja asignada. */
    private function datosFormulario(): array
    {
        $cajaId = (int) (auth()->user()?->caja_id ?? 0);
        $caja = Cajas::query()->find($cajaId);

        $saldosDisponibles = MovimientoCaja::query()
            ->where('caja_id', $cajaId)
            ->select('metodo_pago_id', 'moneda_id')
            ->selectRaw(
                'ROUND(SUM(CASE WHEN tipo = ? THEN monto ELSE -monto END), 2) AS saldo',
                [MovimientoCaja::ENTRADA]
            )
            ->groupBy('metodo_pago_id', 'moneda_id')
            ->get()
            ->mapWithKeys(fn ($fila) => [
                $fila->metodo_pago_id.':'.$fila->moneda_id => (float) $fila->saldo,
            ]);

        return [
            'caja' => $caja,
            'cajasDestino' => Cajas::query()
                ->where('estado', true)
                ->where('id', '<>', $cajaId)
                ->orderBy('nombre')
                ->get(),
            'metodos' => MetodosPagos::query()->where('estado', true)->orderBy('nombre')->get(),
            'monedas' => Moneda::query()->where('estado', true)->orderBy('codigo')->get(),
            'saldosDisponibles' => $saldosDisponibles,
        ];
    }
}

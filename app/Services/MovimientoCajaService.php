<?php

namespace App\Services;

use App\Models\Cobro;
use App\Models\Cajas;
use App\Models\MovimientoCaja;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MovimientoCajaService
{
    /** Mantiene el libro inmutable al cambiar o retirar un cobro finalizado. */
    public function sincronizarCobro(Cobro $cobro): void
    {
        $cajaIds = MovimientoCaja::query()
            ->where('origen', MovimientoCaja::ORIGEN_COBRO)
            ->where('cobro_id', $cobro->id)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('movimientos_caja as reversas')
                    ->whereColumn('reversas.reversa_de_id', 'movimientos_caja.id');
            })
            ->pluck('caja_id')
            ->push($cobro->caja_id)
            ->unique()
            ->sort()
            ->values();

        // Usa el mismo candado que un cierre para que una entrada de cobro no
        // aparezca después de que se haya guardado la captura del saldo.
        Cajas::query()->whereIn('id', $cajaIds)->orderBy('id')->lockForUpdate()->get();

        $anteriores = MovimientoCaja::query()
            ->where('origen', MovimientoCaja::ORIGEN_COBRO)
            ->where('cobro_id', $cobro->id)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('movimientos_caja as reversas')
                    ->whereColumn('reversas.reversa_de_id', 'movimientos_caja.id');
            })
            ->lockForUpdate()
            ->get();

        foreach ($anteriores as $anterior) {
            $this->revertir($anterior, 'Ajuste del cobro ' . $cobro->codigo);
        }

        if (! $cobro->exists || ! $cobro->esFinalizado() || $cobro->trashed()) {
            return;
        }

        $monto = round((float) $cobro->detalles()->sum('monto'), 2);
        if ($monto <= 0) {
            return;
        }

        MovimientoCaja::create([
            'codigo' => $this->codigo(),
            'caja_id' => $cobro->caja_id,
            'fecha' => $cobro->fecha,
            'tipo' => MovimientoCaja::ENTRADA,
            'origen' => MovimientoCaja::ORIGEN_COBRO,
            'metodo_pago_id' => $cobro->metodo_pago_id,
            'moneda_id' => $cobro->moneda_id,
            'monto' => $monto,
            'descripcion' => 'Cobro ' . $cobro->codigo,
            'referencia' => $cobro->codigo,
            'cobro_id' => $cobro->id,
            'registrado_por' => Auth::id(),
        ]);
    }

    public function revertir(MovimientoCaja $movimiento, string $descripcion): MovimientoCaja
    {
        return MovimientoCaja::create([
            'codigo' => $this->codigo(),
            'caja_id' => $movimiento->caja_id,
            'fecha' => today(),
            'tipo' => $movimiento->tipo === MovimientoCaja::ENTRADA
                ? MovimientoCaja::SALIDA
                : MovimientoCaja::ENTRADA,
            'origen' => MovimientoCaja::ORIGEN_REVERSA,
            'metodo_pago_id' => $movimiento->metodo_pago_id,
            'moneda_id' => $movimiento->moneda_id,
            'monto' => $movimiento->monto,
            'descripcion' => $descripcion,
            'referencia' => $movimiento->codigo,
            'cobro_id' => $movimiento->cobro_id,
            'transferencia_id' => $movimiento->transferencia_id,
            'reversa_de_id' => $movimiento->id,
            'registrado_por' => Auth::id(),
        ]);
    }

    public function codigo(): string
    {
        do {
            $codigo = 'MOV-' . now()->format('ymdHis') . '-' . Str::upper(Str::random(5));
        } while (MovimientoCaja::where('codigo', $codigo)->exists());

        return $codigo;
    }

    public function registrar(array $datos, string $origen = MovimientoCaja::ORIGEN_MANUAL): MovimientoCaja
    {
        return MovimientoCaja::create([
            ...$datos,
            'codigo' => $this->codigo(),
            'origen' => $origen,
            'registrado_por' => Auth::id(),
        ]);
    }

    public function transferencia(array $datos): array
    {
        return DB::transaction(function () use ($datos) {
            $grupo = $this->codigo();
            $base = [
                'fecha' => $datos['fecha'],
                'origen' => MovimientoCaja::ORIGEN_TRANSFERENCIA,
                'metodo_pago_id' => $datos['metodo_pago_id'],
                'moneda_id' => $datos['moneda_id'],
                'monto' => $datos['monto'],
                'referencia' => $grupo,
                'registrado_por' => Auth::id(),
            ];

            $salida = MovimientoCaja::create([
                ...$base,
                'codigo' => $this->codigo(),
                'caja_id' => $datos['caja_origen_id'],
                'tipo' => MovimientoCaja::SALIDA,
                'descripcion' => 'Transferencia a caja ' . $datos['caja_destino_nombre'],
            ]);

            $entrada = MovimientoCaja::create([
                ...$base,
                'codigo' => $this->codigo(),
                'caja_id' => $datos['caja_destino_id'],
                'tipo' => MovimientoCaja::ENTRADA,
                'descripcion' => 'Transferencia desde caja ' . $datos['caja_origen_nombre'],
                'transferencia_id' => $salida->id,
            ]);

            $salida->update(['transferencia_id' => $entrada->id]);

            return [$salida, $entrada];
        });
    }
}

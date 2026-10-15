<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\EquipoConsumo;
use App\Models\EquipoInsumo;
use App\Models\MovimientosInventario;
use App\Models\ProcesoEquipo;
use App\Models\TarifaEnergia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Horas y consumos de equipos: una sola fuente para web, API y totales. */
class UsoEquipoService
{
    public static function reglasHoras(): array
    {
        return [
            'horas_efectivas' => ['nullable', 'numeric', 'min:0', 'max:999999', 'decimal:0,4'],
            'horometro_inicial' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'horometro_final' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'consumos_confirmados' => ['sometimes', 'boolean'],
        ];
    }

    public function guardar(ProcesoEquipo $uso, array $datos, bool $guardar = true): ProcesoEquipo
    {
        return DB::transaction(function () use ($uso, $datos, $guardar) {
            if ($uso->exists) $uso = ProcesoEquipo::query()->lockForUpdate()->findOrFail($uso->id);
            $uso->fill($datos);
            $equipo = Equipo::query()->lockForUpdate()->findOrFail($uso->equipo_id);
            $uso->setRelation('equipo', $equipo);
            if ($uso->exists && $uso->isDirty('equipo_id') && ($uso->consumos()->withTrashed()->exists() || $uso->entregas()->exists())) {
                $this->error('equipo_id', 'Este uso ya tiene consumos. Mantenga el equipo para conservar su trazabilidad.');
            }
            $uso->validarPeriodo();
            if ($guardar) $uso->validarSinSolapamiento();
            if ($uso->exists && $uso->isDirty(['fecha_inicio', 'fecha_fin']) && $uso->consumos()->where('tipo', 'consumo')
                ->where(function ($q) use ($uso) {
                    $q->whereDate('fecha', '<', $uso->fecha_inicio->toDateString());
                    if ($uso->fecha_fin) $q->orWhereDate('fecha', '>', $uso->fecha_fin->toDateString());
                })->exists()) {
                $this->error('fecha_fin', 'El periodo debe incluir las fechas de los consumos registrados.');
            }
            // Corregir observaciones o disponibilidad no modifica importes históricos.
            if ($uso->exists && !$uso->isDirty(['equipo_id', 'horas_efectivas', 'horometro_inicial', 'horometro_final'])) {
                if ($guardar) $uso->save();
                return $uso;
            }
            $tarifa = $uso->exists && $uso->depreciacion_aplicada !== null && !$uso->isDirty('equipo_id')
                ? $uso->tarifa_kwh_nio : TarifaEnergia::actual()?->tarifa_kwh_nio;
            if ($tarifa === null && $uso->potencia_kw_usada > 0 && !$uso->getRawOriginal('horas_efectivas')) {
                $tarifa = TarifaEnergia::actual()?->tarifa_kwh_nio;
                $uso->tarifa_kwh_nio = $tarifa;
            }
            $usaElectricidad = $uso->depreciacion_aplicada !== null && !$uso->isDirty('equipo_id')
                ? $uso->potencia_kw_usada > 0 : $equipo->usaEnergia();
            if ($usaElectricidad && $tarifa === null && $guardar && $uso->horas_efectivas > 0) {
                $this->error('equipo_id', 'Configure la tarifa general de electricidad en Administración de equipos.');
            }
            $uso->calcularCostos($tarifa === null ? null : (float) $tarifa);
            if ($guardar) $uso->save();

            return $uso;
        });
    }

    public function registrarInsumo(ProcesoEquipo $uso, array $datos): void
    {
        DB::transaction(function () use ($uso, $datos) {
            $uso = ProcesoEquipo::query()->lockForUpdate()->findOrFail($uso->id);
            $equipo = Equipo::query()->lockForUpdate()->findOrFail($uso->equipo_id);
            $tipo = $datos['tipo'];
            $cantidad = round((float) $datos['cantidad'], 3);
            if ($cantidad <= 0) $this->error('cantidad', 'Indique una cantidad mayor que cero.');

            if ($tipo === 'entrega') {
                if (!$equipo->recursos()->where('producto_id', $datos['producto_id'])->exists()) {
                    $this->error('producto_id', 'Configure este producto entre los recursos del equipo.');
                }
                if (!\App\Models\Producto::whereKey($datos['producto_id'])->where('estado', true)->exists()) {
                    $this->error('producto_id', 'El producto está inactivo o no está disponible en almacén.');
                }
                $movimiento = app(InventarioService::class)->registrar([
                    'producto_id' => $datos['producto_id'], 'tipo' => MovimientosInventario::TIPO_SALIDA,
                    'fecha' => $datos['fecha'], 'cantidad' => $cantidad,
                    'referencia' => 'Entrega a equipo '.$equipo->codigo,
                    'observaciones' => 'En custodia del equipo; se imputa al proceso al confirmar su consumo.',
                ]);
                EquipoInsumo::create([
                    'equipo_id' => $equipo->id, 'proceso_equipo_id' => $uso->id, 'producto_id' => $movimiento->producto_id,
                    'movimiento_id' => $movimiento->id, 'fecha' => $datos['fecha'],
                    'cantidad' => $cantidad, 'disponible' => $cantidad,
                    'costo_unitario_nio' => $movimiento->costo_unitario_nio,
                ]);
            } else {
                $lote = EquipoInsumo::query()->where('equipo_id', $equipo->id)
                    ->lockForUpdate()->findOrFail($datos['equipo_insumo_id']);
                if ($cantidad > $lote->disponible + 0.00001) {
                    $this->error('cantidad', 'La cantidad supera el saldo entregado a este equipo.');
                }
                if ($datos['fecha'] < $lote->fecha->toDateString()) {
                    $this->error('fecha', 'El consumo o devolución no puede ser anterior a la entrega.');
                }
                if ($tipo === 'consumo' && ($datos['fecha'] < $uso->fecha_inicio->toDateString()
                    || ($uso->fecha_fin && $datos['fecha'] > $uso->fecha_fin->toDateString()))) {
                    $this->error('fecha', 'El consumo debe pertenecer al periodo de uso del equipo.');
                }
                $movimiento = null;
                if ($tipo === 'devolucion') {
                    $movimiento = app(InventarioService::class)->registrar([
                        'producto_id' => $lote->producto_id, 'tipo' => MovimientosInventario::TIPO_ENTRADA,
                        'fecha' => $datos['fecha'], 'cantidad' => $cantidad,
                        'costo_unitario' => $lote->costo_unitario_nio,
                        'referencia' => 'Devolución de equipo '.$equipo->codigo,
                    ]);
                }
                EquipoConsumo::create([
                    'equipo_insumo_id' => $lote->id, 'proceso_equipo_id' => $uso->id,
                    'movimiento_id' => $movimiento?->id, 'tipo' => $tipo, 'fecha' => $datos['fecha'],
                    'cantidad' => $cantidad, 'costo_total_nio' => round($cantidad * $lote->costo_unitario_nio, 2),
                ]);
                $lote->disponible = round($lote->disponible - $cantidad, 4);
                $lote->save();
                if ($tipo === 'consumo') {
                    $uso->consumos_confirmados = false;
                    $uso->save();
                }
            }
        });
    }

    public function anularConsumo(ProcesoEquipo $uso, int $consumoId): void
    {
        DB::transaction(function () use ($uso, $consumoId) {
            $uso = ProcesoEquipo::query()->lockForUpdate()->findOrFail($uso->id);
            Equipo::query()->lockForUpdate()->findOrFail($uso->equipo_id);
            $consumo = $uso->consumos()->where('tipo', 'consumo')->lockForUpdate()->findOrFail($consumoId);
            $lote = EquipoInsumo::query()->lockForUpdate()->findOrFail($consumo->equipo_insumo_id);
            $lote->disponible = round($lote->disponible + $consumo->cantidad, 4);
            $lote->save();
            $consumo->delete();
            $uso->consumos_confirmados = false;
            $uso->save();
        });
    }

    private function error(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}

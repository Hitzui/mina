<?php

namespace App\Services;

use App\Models\{EquipoReparacion, OrdenesTrabajo, ProcesoEquipo, ProcesosOrden, User};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeguimientoOrdenService
{
    public function guardar(OrdenesTrabajo $orden, array $datos, ?EquipoReparacion $reparacion = null): EquipoReparacion
    {
        return DB::transaction(function () use ($orden, $datos, $reparacion) {
            $orden = OrdenesTrabajo::lockForUpdate()->findOrFail($orden->id);
            $uso = ProcesoEquipo::whereHas('procesoOrden', fn ($q) => $q->where('orden_trabajo_id', $orden->id))
                ->lockForUpdate()->findOrFail($reparacion?->proceso_equipo_id ?? $datos['assignment_id']);
            if ($reparacion) {
                $reparacion = EquipoReparacion::lockForUpdate()->findOrFail($reparacion->id);
                $this->version($reparacion, $datos['expected_version']);
            } else {
                if ($orden->estaCerrada() || $uso->procesoOrden->estaCerrado()) $this->error('assignment_id', 'La OT o el proceso está cerrado. Reábralo para registrar reparaciones nuevas.');
                $reparacion = new EquipoReparacion(['proceso_equipo_id' => $uso->id, 'created_by' => auth()->id()]);
            }
            $inicio = Carbon::parse($datos['started_at']);
            $fin = empty($datos['ended_at']) ? null : Carbon::parse($datos['ended_at']);
            if ($inicio->isFuture() || $fin?->isFuture()) $this->error('started_at', 'La reparación registra tiempo real; no puede tener fechas futuras.');
            if ($inicio->lt($uso->fecha_inicio) || ($uso->fecha_fin && $inicio->gte($uso->fecha_fin))) {
                $this->error('started_at', 'La reparación debe comenzar dentro del periodo de asignación del equipo.');
            }
            if ($fin && $fin->lte($inicio)) $this->error('ended_at', 'El fin debe ser posterior al inicio de la reparación.');
            $solape = EquipoReparacion::where('proceso_equipo_id', $uso->id)
                ->when($reparacion->exists, fn ($q) => $q->whereKeyNot($reparacion->id))
                ->when($fin, fn ($q) => $q->where('inicio', '<', $fin))
                ->where(fn ($q) => $q->whereNull('fin')->orWhere('fin', '>', $inicio))->exists();
            if ($solape) $this->error('started_at', 'Ese intervalo se solapa con otra reparación del mismo uso. Cierre o corrija la reparación existente.');
            $reparacion->fill(['inicio' => $inicio, 'fin' => $fin, 'motivo' => $datos['reason']]);
            // Los clientes anteriores pueden no enviar estos campos; no borrar datos existentes.
            if (array_key_exists('repair_location', $datos)) $reparacion->modalidad = $datos['repair_location'];
            if (array_key_exists('work_description', $datos)) $reparacion->trabajo_realizado = $datos['work_description'];
            if ($reparacion->exists) $reparacion->version++;
            $reparacion->save();

            return $reparacion;
        });
    }

    public function anular(EquipoReparacion $reparacion, int $version): void
    {
        DB::transaction(function () use ($reparacion, $version) {
            ProcesoEquipo::lockForUpdate()->findOrFail($reparacion->proceso_equipo_id);
            $reparacion = EquipoReparacion::lockForUpdate()->findOrFail($reparacion->id);
            $this->version($reparacion, $version);
            if ($reparacion->costos()->exists()) $this->error('repair_id', 'La reparación tiene costos vinculados. Corríjalos o anúlelos desde Costos antes de anular el intervalo.');
            $reparacion->version++;
            $reparacion->save();
            $reparacion->delete();
        });
    }

    public function ampliar(ProcesoEquipo $uso, array $datos): ProcesoEquipo
    {
        return DB::transaction(function () use ($uso, $datos) {
            $uso = ProcesoEquipo::lockForUpdate()->findOrFail($uso->id);
            if ($uso->procesoOrden->orden_trabajo->estaCerrada() || $uso->procesoOrden->estaCerrado()) $this->error('ended_at', 'La OT o el proceso está cerrado. Reábralo para ampliar la asignación.');
            $esperado = empty($datos['expected_end']) ? null : Carbon::parse($datos['expected_end'])->format('Y-m-d H:i:s');
            if ($esperado !== $uso->fecha_fin?->format('Y-m-d H:i:s')) $this->error('expected_end', 'El plazo cambió en otro dispositivo. Actualice los datos antes de ampliarlo.');
            $fin = Carbon::parse($datos['ended_at']);
            if ($fin->lte($uso->fecha_fin ?? $uso->fecha_inicio)) $this->error('ended_at', 'Indique un fin posterior al plazo actual.');

            return app(UsoEquipoService::class)->guardar($uso, ['fecha_fin' => $fin]);
        });
    }

    private function version(EquipoReparacion $reparacion, int $version): void
    {
        if ($reparacion->version !== $version) $this->error('expected_version', 'Esta reparación cambió en otro dispositivo. Actualice los datos y revise la diferencia antes de corregirla.');
    }

    /** Unión de intervalos: dos equipos dañados a la vez no duplican tiempo calendario. */
    public function unir(array $intervalos): array
    {
        usort($intervalos, fn ($a, $b) => $a[0] <=> $b[0]);
        $unidos = [];
        foreach ($intervalos as [$a, $b]) {
            if ($b <= $a) continue;
            $i = count($unidos) - 1;
            if ($i >= 0 && $a <= $unidos[$i][1]) $unidos[$i][1] = max($b, $unidos[$i][1]);
            else $unidos[] = [$a, $b];
        }
        return $unidos;
    }

    public function datos(OrdenesTrabajo $orden, User $usuario): array
    {
        $ahora = now();
        $procesos = $orden->procesos_ordenes()->with('etapa')->orderBy('id')->get();
        $vigentes = $procesos->reject(fn ($p) => $p->estaCancelado());
        $terminados = $vigentes->filter(fn ($p) => $p->estadoCalculado() === ProcesosOrden::ESTADO_FINALIZADO)->count();
        $verEquipos = $usuario->can('proceso_equipo.view');
        $usos = $verEquipos ? ProcesoEquipo::with(['equipo', 'procesoOrden', 'reparaciones.costos.moneda'])
            ->whereIn('proceso_orden_id', $procesos->pluck('id'))->orderBy('fecha_inicio')->get() : collect();
        $filas = []; $reparaciones = []; $intervalos = [];
        foreach ($usos as $uso) {
            $inicio = $uso->fecha_inicio->timestamp;
            $fin = max($inicio + 1, ($uso->fecha_fin ?? $ahora)->timestamp);
            $tramos = [];
            foreach ($uso->reparaciones as $r) {
                $a = max($inicio, $r->inicio->timestamp);
                $b = min($fin, ($r->fin ?? $ahora)->timestamp, $ahora->timestamp);
                if ($b > $a) { $tramos[] = [$a, $b]; $intervalos[] = [$a, $b]; }
                $reparaciones[] = [
                    'id' => $r->id, 'assignment_id' => $uso->id, 'version' => $r->version,
                    'started_at' => $r->inicio->format('Y-m-d\TH:i:s'), 'ended_at' => $r->fin?->format('Y-m-d\TH:i:s'),
                    'repair_location' => $r->modalidad, 'work_description' => $r->trabajo_realizado,
                    'total_hours' => round(max(0, ($r->fin ?? $ahora)->timestamp - $r->inicio->timestamp) / 3600, 4),
                    'reason' => $r->motivo, 'equipment' => $uso->equipo?->nombre ?? 'Equipo histórico', 'process' => $uso->procesoOrden?->codigo ?? 'Proceso histórico',
                    'can_cost' => !$orden->estaCerrada() && !$uso->procesoOrden->estaCerrado() && $usuario->can('movimientos_costo.create'),
                    'hours_in_assignment' => round(max(0, $b - $a) / 3600, 4),
                    'costs' => $usuario->can('movimientos_costo.view') ? $r->costos->map(fn ($c) => [
                        'id' => $c->id, 'description' => $c->descripcion, 'amount' => (float) $c->costo_total,
                        'currency' => $c->moneda?->codigo, 'amount_nio' => $c->costo_total_nio,
                    ])->values()->all() : [],
                ];
            }
            $tramos = $this->unir($tramos);
            $filas[] = [
                'id' => $uso->id, 'process_id' => $uso->proceso_orden_id, 'process' => $uso->procesoOrden?->codigo,
                'equipment' => $uso->equipo?->nombre ?? 'Equipo histórico', 'code' => $uso->equipo?->codigo ?? '—',
                'can_register' => !$orden->estaCerrada() && !$uso->procesoOrden->estaCerrado() && $usuario->can('proceso_equipo.edit'),
                'start' => $uso->fecha_inicio->format('Y-m-d\TH:i:s'), 'end' => $uso->fecha_fin?->format('Y-m-d\TH:i:s'),
                'hours_in_repair' => round(array_sum(array_map(fn ($t) => ($t[1] - $t[0]) / 3600, $tramos)), 4),
                ...$this->barra($inicio, $fin, $ahora->timestamp, $tramos),
            ];
        }
        $intervalos = $this->unir($intervalos);
        $inicioGlobal = $usos->min(fn ($u) => $u->fecha_inicio->timestamp);
        $finGlobal = $usos->max(fn ($u) => ($u->fecha_fin ?? $ahora)->timestamp);

        return [
            'order_id' => $orden->id, 'order_code' => $orden->codigo, 'generated_at' => $ahora->toISOString(), 'timezone' => config('app.timezone'),
            'total_processes' => $vigentes->count(), 'completed_processes' => $terminados,
            'cancelled_processes' => $procesos->count() - $vigentes->count(),
            'percent' => $vigentes->isEmpty() ? null : round($terminados * 100 / $vigentes->count(), 1),
            'processes' => $usuario->can('procesos_orden.view') ? $procesos->map(fn ($p) => ['id' => $p->id, 'code' => $p->codigo, 'name' => $p->etapa?->nombre, 'status' => $p->estadoTexto()])->all() : [],
            'can_register' => $verEquipos && $usuario->can('proceso_equipo.edit') && !$orden->estaCerrada(),
            'can_edit' => $verEquipos && $usuario->can('proceso_equipo.edit'),
            'can_cost' => $verEquipos && $usuario->can('movimientos_costo.create') && !$orden->estaCerrada(),
            'assignments' => $filas, 'repairs' => $reparaciones,
            'timeline' => $inicioGlobal === null ? null : [
                'start' => Carbon::createFromTimestamp($inicioGlobal, config('app.timezone'))->format('Y-m-d\TH:i:s'),
                'end' => Carbon::createFromTimestamp(max($inicioGlobal + 1, $finGlobal), config('app.timezone'))->format('Y-m-d\TH:i:s'),
                'hours_in_repair' => round(array_sum(array_map(fn ($t) => ($t[1] - $t[0]) / 3600, $intervalos)), 4),
                ...$this->barra($inicioGlobal, max($inicioGlobal + 1, $finGlobal), $ahora->timestamp, $intervalos),
            ],
        ];
    }

    private function barra(int $inicio, int $fin, int $ahora, array $tramos): array
    {
        $porcentaje = fn ($fecha) => round(max(0, min(100, ($fecha - $inicio) * 100 / ($fin - $inicio))), 5);
        return ['elapsed_percent' => $porcentaje($ahora), 'segments' => array_map(fn ($t) => [
            'from' => $porcentaje($t[0]), 'to' => $porcentaje($t[1]),
        ], $tramos)];
    }

    private function error(string $campo, string $texto): never
    {
        throw ValidationException::withMessages([$campo => $texto]);
    }
}

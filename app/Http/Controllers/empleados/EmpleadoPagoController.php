<?php

namespace App\Http\Controllers\empleados;

use App\DataTables\EmpleadosPagosDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\EmpleadosPago;
use App\Models\Moneda;
use App\Models\TiposPagoEmpleado;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

class EmpleadoPagoController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('empleados.pagos');
    }

    /**
     * Mostrar el DataTable de pagos/tarifas del empleado.
     */
    public function index(
        Empleado $empleado,
        EmpleadosPagosDataTable $dataTable
    ) {
        $dataTable->setEmpleadoId($empleado->id);

        return $dataTable->ajax();
    }


    /**
     * Mostrar formulario para registrar una nueva tarifa.
     */
    public function create(Empleado $empleado)
    {
        $tiposPago = TiposPagoEmpleado::query()
            ->where('estado', true)
            ->orderBy('nombre')
            ->get();

        $monedas = Moneda::query()
            ->where('estado', true)
            ->orderByDesc('es_moneda_base')
            ->orderBy('codigo')
            ->get();

        return view('admin.empleados.pagos._form', [
            'empleado' => $empleado,
            'tiposPago' => $tiposPago,
            'monedas' => $monedas,
            'submitText' => 'Guardar tarifa',
        ]);
    }


    /**
     * Registrar una nueva tarifa.
     *
     * Las tarifas se manejan como períodos históricos.
     *
     * Si existe una tarifa vigente del mismo tipo de pago,
     * se cierra el período anterior un día antes de la nueva
     * fecha de inicio.
     */
    public function store(
        Request $request,
        Empleado $empleado
    ) {
        $validated = $this->validarDatos(
            $request,
            $empleado
        );

        DB::transaction(function () use (
            $validated,
            $empleado
        ) {

            $fechaInicio = Carbon::parse(
                $validated['fecha_inicio']
            );

            /*
             * Buscar una tarifa vigente del mismo tipo
             * de pago que comience antes de la nueva tarifa.
             */
            $tarifaVigente = EmpleadosPago::query()
                ->where('empleado_id', $empleado->id)
                ->where(
                    'tipo_pago_id',
                    $validated['tipo_pago_id']
                )
                ->where('estado', true)
                ->whereDate(
                    'fecha_inicio',
                    '<=',
                    $fechaInicio->format('Y-m-d')
                )
                ->where(function ($query) use ($fechaInicio) {
                    $query->whereNull('fecha_fin')
                        ->orWhereDate(
                            'fecha_fin',
                            '>=',
                            $fechaInicio->format('Y-m-d')
                        );
                })
                ->orderByDesc('fecha_inicio')
                ->first();

            /*
             * Si existe una tarifa vigente anterior,
             * cerramos su período.
             */
            if ($tarifaVigente) {

                $fechaFinAnterior = $fechaInicio->copy()
                    ->subDay();

                /*
                 * Si la fecha de inicio de la nueva tarifa
                 * es anterior o igual a la tarifa existente,
                 * no se puede cerrar correctamente el período.
                 */
                if (
                    $fechaFinAnterior->lt(
                        Carbon::parse(
                            $tarifaVigente->fecha_inicio
                        )
                    )
                ) {
                    throw ValidationException::withMessages([
                        'fecha_inicio' =>
                            'La fecha de inicio de la nueva tarifa debe ser posterior a la tarifa vigente.',
                    ]);
                }
            }

            /*
             * Verificar que el nuevo período no se solape con
             * otro registro distinto de la tarifa vigente que
             * acabamos de cerrar arriba.
             */
            $this->validarSolapamiento(
                $empleado,
                $validated['tipo_pago_id'],
                $validated['fecha_inicio'],
                $validated['fecha_fin'] ?? null,
                $tarifaVigente?->id
            );

            /*
             * Cerrar la tarifa vigente y crear la nueva.
             */
            if ($tarifaVigente) {
                $tarifaVigente->update([
                    'fecha_fin' => $fechaFinAnterior->format('Y-m-d'),
                    'estado' => false,
                ]);
            }

            /*
             * Crear la nueva tarifa.
             */
            EmpleadosPago::create([
                'empleado_id' => $empleado->id,
                'tipo_pago_id' => $validated['tipo_pago_id'],
                'tarifa' => $validated['tarifa'],
                'moneda_id' => $validated['moneda_id'],
                'fecha_inicio' => $validated['fecha_inicio'],
                'fecha_fin' => $validated['fecha_fin'] ?? null,
                'estado' => $validated['estado'],
                'observaciones' => $validated['observaciones'] ?? null,
            ]);
        });

        Alert::toast(
            'Tarifa registrada correctamente.',
            'success'
        )->flash();

        return redirect()->route(
            'admin.empleados.show',
            $empleado
        );
    }


    /**
     * Mostrar una tarifa específica.
     */
    public function show(
        Empleado $empleado,
        EmpleadosPago $empleadoPago
    ) {
        $this->validarPertenencia(
            $empleado,
            $empleadoPago
        );

        $empleadoPago->load([
            'tipo_pago',
            'moneda',
        ]);

        return response()->json([
            'id' => $empleadoPago->id,

            'empleado' => [
                'id' => $empleado->id,
                'nombre' => $empleado->nombre,
                'codigo' => $empleado->codigo,
            ],

            'tipo_pago' =>
                $empleadoPago->tipo_pago?->nombre,

            'tarifa' =>
                number_format(
                    $empleadoPago->tarifa,
                    2
                ),

            'moneda' =>
                $empleadoPago->moneda?->codigo,

            'fecha_inicio' =>
                $empleadoPago->fecha_inicio?->format('d/m/Y'),

            'fecha_fin' =>
                $empleadoPago->fecha_fin?->format('d/m/Y'),

            'estado' =>
                (bool) $empleadoPago->estado,

            'observaciones' =>
                $empleadoPago->observaciones,

            'created_at' =>
                $empleadoPago->created_at?->format('d/m/Y H:i'),

            'updated_at' =>
                $empleadoPago->updated_at?->format('d/m/Y H:i'),
        ]);
    }


    /**
     * Mostrar formulario para editar una tarifa.
     */
    public function edit(Empleado $empleado, EmpleadosPago $empleadoPago)
    {
        $this->validarPertenencia($empleado, $empleadoPago);

        $tiposPago = TiposPagoEmpleado::query()
            ->where(function ($query) use ($empleadoPago) {
                $query->where('estado', true)
                    ->orWhere('id', $empleadoPago->tipo_pago_id);
            })
            ->orderBy('nombre')
            ->get();

        $monedas = Moneda::query()
            ->where(function ($query) use ($empleadoPago) {
                $query->where('estado', true)
                    ->orWhere('id', $empleadoPago->moneda_id);
            })
            ->orderByDesc('es_moneda_base')
            ->orderBy('codigo')
            ->get();

        return view('admin.empleados.pagos._form', [
            'empleado' => $empleado,
            'empleadoPago' => $empleadoPago,
            'tiposPago' => $tiposPago,
            'monedas' => $monedas,
            'submitText' => 'Actualizar tarifa',
        ]);
    }


    /**
     * Actualizar una tarifa existente.
     *
     * La edición se considera una corrección del registro
     * histórico existente. Los cambios de una tarifa futura
     * deben registrarse preferentemente mediante "Nueva tarifa".
     */
    public function update(
        Request $request,
        Empleado $empleado,
        EmpleadosPago $empleadoPago
    ) {
        $this->validarPertenencia(
            $empleado,
            $empleadoPago
        );

        $validated = $this->validarDatos(
            $request,
            $empleado,
            $empleadoPago
        );

        DB::transaction(function () use (
            $validated,
            $empleadoPago,
            $empleado
        ) {

            /*
             * Verificar que el nuevo período no se
             * solape con otro registro del mismo tipo.
             */
            $this->validarSolapamiento(
                $empleado,
                $validated['tipo_pago_id'],
                $validated['fecha_inicio'],
                $validated['fecha_fin'] ?? null,
                $empleadoPago->id
            );

            $empleadoPago->update([
                'tipo_pago_id' => $validated['tipo_pago_id'],
                'tarifa' => $validated['tarifa'],
                'moneda_id' => $validated['moneda_id'],
                'fecha_inicio' => $validated['fecha_inicio'],
                'fecha_fin' => $validated['fecha_fin'] ?? null,
                'estado' => $validated['estado'],
                'observaciones' => $validated['observaciones'] ?? null,
            ]);
        });

        Alert::toast(
            'Tarifa actualizada correctamente.',
            'success'
        )->flash();

        return redirect()->route(
            'admin.empleados.show',
            $empleado
        );
    }


    /**
     * Eliminar lógicamente una tarifa.
     */
    public function destroy(
        Empleado $empleado,
        EmpleadosPago $empleadoPago
    ) {
        $this->validarPertenencia(
            $empleado,
            $empleadoPago
        );

        $empleadoPago->delete();

        Alert::toast(
            'Tarifa eliminada correctamente.',
            'success'
        )->flash();

        return redirect()->route(
            'admin.empleados.show',
            $empleado
        );
    }


    /**
     * Validar los datos recibidos del formulario.
     */
    private function validarDatos(
        Request $request,
        Empleado $empleado,
        ?EmpleadosPago $empleadoPago = null
    ): array {

        $tipoPagoRule = Rule::exists(
            'tipos_pago_empleado',
            'id'
        );

        $monedaRule = Rule::exists(
            'monedas',
            'id'
        );

        /*
         * En creación solamente se aceptan catálogos activos.
         *
         * En edición también permitimos conservar el catálogo
         * actualmente utilizado por el registro histórico.
         */
        if (!$empleadoPago) {

            $tipoPagoRule->where(
                'estado',
                true
            );

            $monedaRule->where(
                'estado',
                true
            );
        } else {

            $tipoPagoRule->where(function ($query) use ($empleadoPago) {
                $query->where('estado', true)
                    ->orWhere(
                        'id',
                        $empleadoPago->tipo_pago_id
                    );
            });

            $monedaRule->where(function ($query) use ($empleadoPago) {
                $query->where('estado', true)
                    ->orWhere(
                        'id',
                        $empleadoPago->moneda_id
                    );
            });
        }

        $validated = $request->validate([
            'tipo_pago_id' => [
                'required',
                'integer',
                $tipoPagoRule,
            ],

            'tarifa' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'moneda_id' => [
                'required',
                'integer',
                $monedaRule,
            ],

            'fecha_inicio' => [
                'required',
                'date',
            ],

            'fecha_fin' => [
                'nullable',
                'date',
                'after_or_equal:fecha_inicio',
            ],

            'estado' => [
                'required',
                'boolean',
            ],

            'observaciones' => [
                'nullable',
                'string',
            ],
        ]);

        /*
         * La validación de solapamiento NO se hace aquí: depende de qué
         * registro se va a cerrar automáticamente, y eso solo se conoce
         * dentro de la transacción de store(). La ejecutan store() y
         * update() una sola vez, excluyendo el registro que corresponda.
         */
        return $validated;
    }


    /**
     * Verificar que la tarifa pertenezca al empleado indicado
     * en la URL.
     */
    private function validarPertenencia(
        Empleado $empleado,
        EmpleadosPago $empleadoPago
    ): void {

        if (
            (int) $empleadoPago->empleado_id !==
            (int) $empleado->id
        ) {
            abort(404);
        }
    }


    /**
     * Validar que no existan períodos superpuestos
     * para el mismo empleado y tipo de pago.
     *
     * Ejemplo:
     *
     * 01/01/2026 - 31/01/2026
     * 01/02/2026 - Vigente
     *
     * Es válido.
     *
     * Pero:
     *
     * 01/01/2026 - 31/01/2026
     * 15/01/2026 - Vigente
     *
     * No es válido.
     */
    private function validarSolapamiento(
        Empleado $empleado,
        int $tipoPagoId,
        string $fechaInicio,
        ?string $fechaFin = null,
        ?int $exceptoId = null
    ): void {

        $inicio = Carbon::parse(
            $fechaInicio
        )->startOfDay();

        $fin = $fechaFin
            ? Carbon::parse($fechaFin)->endOfDay()
            : null;

        $query = EmpleadosPago::query()
            ->where('empleado_id', $empleado->id)
            ->where('tipo_pago_id', $tipoPagoId);

        /*
         * Cuando estamos editando, no debemos comparar
         * el registro contra sí mismo.
         */
        if ($exceptoId !== null) {
            $query->where(
                'id',
                '!=',
                $exceptoId
            );
        }

        /*
         * Condición de solapamiento entre el período nuevo
         * [inicio, fin] y uno existente [e_inicio, e_fin]:
         *
         *   e_inicio <= fin
         *   Y
         *   e_fin IS NULL  O  e_fin >= inicio
         *
         * Si el período nuevo no tiene fecha_fin se extiende hasta
         * el infinito, así que la primera condición siempre se cumple
         * y no debe acotarse con $inicio: esa era la causa de que un
         * período abierto previo hiciera fallar siempre la creación.
         *
         * Los períodos existentes sin fecha_fin se consideran abiertos
         * y se dejan al cierre automático de store().
         */
        if ($fin !== null) {
            $query->where('fecha_inicio', '<=', $fin);
        }

        $query->where(function ($query) use ($inicio) {

            $query->whereNull('fecha_fin')
                ->orWhere(
                    'fecha_fin',
                    '>=',
                    $inicio
                );
        });

        $existeSolapamiento = $query->exists();

        if ($existeSolapamiento) {

            throw ValidationException::withMessages([
                'fecha_inicio' =>
                    'El período seleccionado se solapa con otra tarifa del mismo tipo de pago para este empleado.',
            ]);
        }
    }
}

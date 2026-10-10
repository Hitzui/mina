<?php

namespace App\Http\Controllers\Procesos;

use App\DataTables\ProduccionesDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use App\Models\Produccione;
use App\Models\TiposProduccion;
use App\Models\UnidadMedida;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

class ProduccionController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('producciones', ['index']);
        $this->middleware('permission:producciones.view')->only('procesosPorOrden');
    }

    public function index(ProduccionesDataTable $dataTable)
    {
        return $dataTable->render('procesos.producciones.index', [
            'title' => 'Producciones',
            'breadcrumbs' => [
                ['label' => 'Inicio', 'url' => route('home')],
                ['label' => 'Producción'],
                ['label' => 'Producciones'],
            ],
            'ordenes' => OrdenesTrabajo::with('cliente')
                ->orderByDesc(OrdenesTrabajo::FECHA)
                ->orderByDesc(OrdenesTrabajo::ID)
                ->get(),
            'tipos' => TiposProduccion::paraRegistrar()->get(),
            'unidadesMedida' => UnidadMedida::where('estado', true)->orderBy('nombre')->get(),
        ]);
    }

    public function procesosPorOrden(Request $request)
    {
        $datos = $request->validate([
            'orden_trabajo_id' => [
                'required',
                'integer',
                Rule::exists('ordenes_trabajo', 'id')->whereNull('deleted_at'),
            ],
        ]);

        $procesos = ProcesosOrden::with('etapa')
            ->where(ProcesosOrden::ORDEN_TRABAJO_ID, $datos['orden_trabajo_id'])
            ->orderBy(ProcesosOrden::CODIGO)
            ->get()
            ->map(fn (ProcesosOrden $proceso) => [
                'id' => $proceso->id,
                'nombre' => $proceso->nombre_completo,
                'estado' => $proceso->estadoTexto(),
                'cerrado' => $proceso->estaCerrado(),
            ]);

        return response()->json($procesos);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $orden = OrdenesTrabajo::findOrFail($datos[Produccione::ORDEN_TRABAJO_ID]);
        $this->validarAltasEnOrdenYProceso($orden, $datos);

        $this->aplicarUnidad($datos);
        $produccion = Produccione::create($datos);

        Alert::success('Producción guardada');

        return response()->json(['ok' => true, 'id' => $produccion->id]);
    }

    public function edit(Produccione $produccion)
    {
        $produccion->load(['orden_trabajo', 'proceso_orden.etapa', 'tipo_produccion']);

        return response()->json([
            'id' => $produccion->id,
            'orden_trabajo_id' => $produccion->orden_trabajo_id,
            'orden_codigo' => $produccion->orden_trabajo?->codigo,
            'orden_cerrada' => $produccion->orden_trabajo?->estaCerrada() ?? true,
            'proceso_orden_id' => $produccion->proceso_orden_id,
            'proceso_nombre' => $produccion->proceso_orden?->nombre_completo,
            'proceso_cerrado' => $produccion->proceso_orden?->estaCerrado() ?? false,
            'tipo_produccion_id' => $produccion->tipo_produccion_id,
            'tipo_produccion_nombre' => $produccion->tipo_produccion?->nombre,
            'fecha' => $produccion->fecha->format('Y-m-d'),
            'descripcion' => $produccion->descripcion,
            'cantidad' => $produccion->cantidad,
            'unidad_medida' => $produccion->unidad_medida,
            'unidad_medida_id' => $produccion->unidad_medida_id,
            'unidad_medida_nombre' => $produccion->unidad_medida,
            'observaciones' => $produccion->observaciones,
        ]);
    }

    public function update(Request $request, Produccione $produccion)
    {
        $datos = $this->validar($request, $produccion);
        $orden = OrdenesTrabajo::findOrFail($datos[Produccione::ORDEN_TRABAJO_ID]);
        $ordenCambio = (int) $orden->id !== (int) $produccion->orden_trabajo_id;
        $procesoCambio = (int) ($datos[Produccione::PROCESO_ORDEN_ID] ?? 0)
            !== (int) ($produccion->proceso_orden_id ?? 0);

        if ($ordenCambio || $procesoCambio) {
            $this->validarAltasEnOrdenYProceso($orden, $datos);
        }

        $this->aplicarUnidad($datos);
        $produccion->update($datos);

        Alert::success('Producción guardada');

        return response()->json(['ok' => true, 'id' => $produccion->id]);
    }

    public function destroy(Produccione $produccion)
    {
        $produccion->delete();

        Alert::success('Producción eliminada');

        return response()->json(['ok' => true]);
    }

    private function validarAltasEnOrdenYProceso(OrdenesTrabajo $orden, array $datos): void
    {
        if ($orden->estaCerrada()) {
            throw ValidationException::withMessages([
                Produccione::ORDEN_TRABAJO_ID => sprintf(
                    'No se puede registrar producción: la orden %s está %s y no admite datos nuevos. Los registros existentes todavía se pueden corregir.',
                    $orden->codigo,
                    mb_strtolower($orden->estadoTexto())
                ),
            ]);
        }

        $procesoId = $datos[Produccione::PROCESO_ORDEN_ID] ?? null;
        if ($procesoId === null) {
            return;
        }

        $proceso = ProcesosOrden::findOrFail($procesoId);
        if ((int) $proceso->orden_trabajo_id !== (int) $orden->id) {
            throw ValidationException::withMessages([
                Produccione::PROCESO_ORDEN_ID => 'El proceso seleccionado no pertenece a la orden elegida.',
            ]);
        }

        if ($proceso->estaCerrado()) {
            throw ValidationException::withMessages([
                Produccione::PROCESO_ORDEN_ID => sprintf(
                    'No se puede registrar producción: el proceso %s está %s. Los registros existentes todavía se pueden corregir.',
                    $proceso->codigo,
                    mb_strtolower($proceso->estadoTexto())
                ),
            ]);
        }
    }

    private function validar(Request $request, ?Produccione $produccion = null): array
    {
        if (! $request->filled(Produccione::UNIDAD_MEDIDA_ID) && $request->filled(Produccione::UNIDAD_MEDIDA)) {
            $request->merge([Produccione::UNIDAD_MEDIDA_ID => UnidadMedida::where('nombre', trim($request->input(Produccione::UNIDAD_MEDIDA)))->value('id')]);
        }

        $tipoIdActual = (int) ($produccion?->tipo_produccion_id ?? 0);
        $reglaTipoActivo = Rule::exists('tipos_produccion', 'id')
            ->whereNull('deleted_at')
            ->where('estado', true);

        $datos = $request->validate([
            Produccione::ORDEN_TRABAJO_ID => [
                'required',
                'integer',
                Rule::exists('ordenes_trabajo', 'id')->whereNull('deleted_at'),
            ],
            Produccione::PROCESO_ORDEN_ID => [
                'nullable',
                'integer',
                Rule::exists('procesos_orden', 'id')->whereNull('deleted_at'),
            ],
            Produccione::TIPO_PRODUCCION_ID => [
                'required',
                'integer',
                $tipoIdActual > 0
                    ? Rule::exists('tipos_produccion', 'id')->whereNull('deleted_at')
                    : $reglaTipoActivo,
            ],
            Produccione::FECHA => ['required', 'date'],
            Produccione::DESCRIPCION => ['nullable', 'string', 'max:255'],
            Produccione::CANTIDAD => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999.9999'],
            Produccione::UNIDAD_MEDIDA_ID => ['required', 'integer', Rule::exists('unidades_medida', 'id')->where('estado', true)],
            Produccione::OBSERVACIONES => ['nullable', 'string', 'max:5000'],
        ], [
            Produccione::ORDEN_TRABAJO_ID.'.required' => 'Elija la orden de trabajo.',
            Produccione::ORDEN_TRABAJO_ID.'.exists' => 'La orden seleccionada ya no está disponible.',
            Produccione::PROCESO_ORDEN_ID.'.exists' => 'El proceso seleccionado ya no está disponible.',
            Produccione::TIPO_PRODUCCION_ID.'.required' => 'Elija el tipo de producción.',
            Produccione::TIPO_PRODUCCION_ID.'.exists' => 'El tipo seleccionado no está activo o ya no está disponible.',
            Produccione::FECHA.'.required' => 'Indique la fecha de producción.',
            Produccione::FECHA.'.date' => 'La fecha de producción no es válida.',
            Produccione::CANTIDAD.'.required' => 'Indique la cantidad producida.',
            Produccione::CANTIDAD.'.numeric' => 'La cantidad debe ser un número.',
            Produccione::CANTIDAD.'.decimal' => 'La cantidad admite hasta cuatro decimales.',
            Produccione::CANTIDAD.'.min' => 'La cantidad no puede ser negativa.',
            Produccione::UNIDAD_MEDIDA_ID.'.required' => 'Elija una unidad de medida.',
            Produccione::UNIDAD_MEDIDA_ID.'.exists' => 'La unidad de medida ya no está disponible.',
        ]);

        if ($produccion && (int) $datos[Produccione::TIPO_PRODUCCION_ID] === $tipoIdActual) {
            $tipo = TiposProduccion::withTrashed()->find($tipoIdActual);
            if ($tipo && $tipo->trashed()) {
                throw ValidationException::withMessages([
                    Produccione::TIPO_PRODUCCION_ID => 'El tipo asociado fue dado de baja. Elija un tipo activo para corregir la producción.',
                ]);
            }
        } elseif ($produccion && (int) $datos[Produccione::TIPO_PRODUCCION_ID] !== $tipoIdActual) {
            $tipoActivo = TiposProduccion::query()
                ->whereKey($datos[Produccione::TIPO_PRODUCCION_ID])
                ->where(TiposProduccion::ESTADO, true)
                ->exists();
            if (! $tipoActivo) {
                throw ValidationException::withMessages([
                    Produccione::TIPO_PRODUCCION_ID => 'Para cambiar el tipo, elija uno que esté activo.',
                ]);
            }
        }

        $datos[Produccione::DESCRIPCION] = filled($datos[Produccione::DESCRIPCION] ?? null)
            ? $datos[Produccione::DESCRIPCION]
            : null;
        $datos[Produccione::OBSERVACIONES] = filled($datos[Produccione::OBSERVACIONES] ?? null)
            ? $datos[Produccione::OBSERVACIONES]
            : null;

        return $datos;
    }

    private function aplicarUnidad(array &$datos): void
    {
        $unidad = UnidadMedida::findOrFail($datos[Produccione::UNIDAD_MEDIDA_ID]);
        $datos[Produccione::UNIDAD_MEDIDA] = $unidad->nombre;
    }
}

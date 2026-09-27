<?php

namespace App\Http\Controllers\Procesos;

use App\DataTables\CostosProcesoDataTable;
use App\DataTables\EmpleadosSelectorDataTable;
use App\DataTables\MaterialesProcesoDataTable;
use App\DataTables\ProcesoEquiposDataTable;
use App\DataTables\ProcesosOrdenDataTable;
use App\DataTables\TrabajosEmpleadosDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Concerns\ValidaCostos;
use App\Http\Controllers\Controller;
use App\Models\Equipo;
use App\Models\Etapa;
use App\Models\MovimientosInventario;
use App\Models\OrdenesTrabajo;
use App\Models\Producto;
use App\Models\ProcesosOrden;
use App\Models\TiposPagoEmpleado;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RealRashid\SweetAlert\Facades\Alert;

class ProcesoOrdenController extends Controller
{
    use AuthorizesModule;

    /*
     * Solo para los combos del modal de costos. El permiso sigue siendo
     * el de procesos_orden: mostrar un proceso no es lo mismo que poder
     * registrar costos, asi que no se pide el permiso de costos aqui.
     */
    use ValidaCostos;

    public function __construct()
    {
        $this->authorizeModule('procesos_orden');
    }

    /**
     * Pantalla del proceso.
     *
     * Aqui viven los trabajos de los empleados: el trabajo cuelga del
     * proceso, no de la orden, asi que es su sitio natural para
     * registrarlos y para ver cuanto se le paga al proceso.
     */
    public function show(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        TrabajosEmpleadosDataTable $trabajosDataTable,
        ProcesoEquiposDataTable $equiposDataTable,
        CostosProcesoDataTable $costosDataTable,
        MaterialesProcesoDataTable $materialesDataTable,
        EmpleadosSelectorDataTable $empleadosSelectorDataTable
    ) {
        $this->validarProcesoPertenece($ordenTrabajo, $procesoOrden);

        $procesoOrden->load('etapa');

        $title = 'Información del Proceso';

        $breadcrumbs = [
            [
                'label' => 'Dashboard',
                'url' => route('home'),
            ],
            [
                'label' => 'Órdenes de Trabajo',
                'url' => route('procesos.ordenes_trabajo.index'),
            ],
            [
                'label' => $ordenTrabajo->codigo,
                'url' => route('procesos.ordenes_trabajo.show', $ordenTrabajo),
            ],
            [
                'label' => $procesoOrden->nombre_completo,
                'url' => '#',
            ],
        ];

        $trabajosDataTable->setProcesoOrdenId($procesoOrden->id);
        $trabajosDataTable->setOrdenTrabajoId($ordenTrabajo->id);

        $equiposDataTable->setProcesoOrdenId($procesoOrden->id);
        $equiposDataTable->setOrdenTrabajoId($ordenTrabajo->id);

        $costosDataTable->setProcesoOrdenId($procesoOrden->id);
        $costosDataTable->setOrdenTrabajoId($ordenTrabajo->id);

        $materialesDataTable->setProcesoOrdenId($procesoOrden->id);
        $materialesDataTable->setOrdenTrabajoId($ordenTrabajo->id);

        // Para el texto de cuanto trabajo, cuantos equipos y cuanto material
        $cantidadTrabajos = $procesoOrden->trabajos_empleados()->count();
        $cantidadEquipos = $procesoOrden->equipos()->count();
        $cantidadMateriales = $procesoOrden->movimientos_materia_prima()
            ->where('tipo', MovimientosInventario::TIPO_SALIDA)
            ->count();

        // El formulario del trabajo necesita los tipos de pago para su combo
        $tiposPago = TiposPagoEmpleado::query()
            ->where('estado', true)
            ->orderBy('nombre')
            ->get();

        /*
         * El combo de equipos del modal. Se ofrecen todos los activos
         * aunque alguno ya este asignado a otro proceso: la regla de que
         * no pueden cruzarse los periodos la avisa el propio formulario,
         * que es donde el usuario puede entender el conflicto, en vez de
         * esconder el equipo de la lista sin explicacion.
         */
        $equiposDisponibles = Equipo::query()
            ->where('estado', 1)
            ->orderBy('codigo')
            ->get();

        // Los combos del modal de costos
        $combos = $this->datosDeCombos();

        $categorias = $combos['categorias'];
        $monedas = $combos['monedas'];

        /*
         * Periodo por defecto: el propio del proceso. Es lo natural, un
         * equipo se usa mientras dura el proceso, y si el proceso todavia
         * no tiene fecha de inicio se propone la de hoy.
         */
        $fechaInicioPorDefecto = $procesoOrden->fecha_inicio
            ? $procesoOrden->fecha_inicio->format('Y-m-d\TH:i')
            : now()->format('Y-m-d\TH:i');

        // Un costo se fecha el dia en que se registra, no un dia antes
        $fechaPorDefecto = now()->format('Y-m-d');

        // El desglose de donde sale el total del proceso
        $costosDesglosados = $procesoOrden->costosDesglosados();

        /*
         * Los materiales para el modal de consumo. Van con su saldo
         * cargado porque el desplegable muestra cuanto hay de cada uno y a
         * cuanto costo promedio: es la unica forma de que se vea, antes de
         * guardar, que el material no tiene costo cargado y que el consumo
         * sumaria cero al proceso.
         */
        $productos = Producto::query()
            ->where('estado', true)
            ->whereNull('deleted_at')
            ->with('inventario')
            ->orderBy('nombre')
            ->get();

        return view(
            'procesos.procesos_orden.show',
            compact(
                'title',
                'breadcrumbs',
                'ordenTrabajo',
                'procesoOrden',
                'trabajosDataTable',
                'equiposDataTable',
                'costosDataTable',
                'materialesDataTable',
                'empleadosSelectorDataTable',
                'cantidadTrabajos',
                'cantidadEquipos',
                'cantidadMateriales',
                'tiposPago',
                'equiposDisponibles',
                'fechaInicioPorDefecto',
                'categorias',
                'monedas',
                'productos',
                'fechaPorDefecto',
                'costosDesglosados'
            )
        );
    }

    /**
     * El proceso de la URL tiene que ser de la orden de la URL.
     */
    private function validarProcesoPertenece(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden
    ): void {
        abort_unless(
            (int) $procesoOrden->orden_trabajo_id === $ordenTrabajo->id,
            404
        );
    }

    public function create(OrdenesTrabajo $ordenTrabajo)
    {
        $title = 'Nuevo Proceso';

        $breadcrumbs = [
            [
                'label' => 'Órdenes de Trabajo',
                'url' => route('procesos.ordenes_trabajo.index'),
            ],
            [
                'label' => $ordenTrabajo->codigo,
                'url' => route(
                    'procesos.ordenes_trabajo.show',
                    $ordenTrabajo
                ),
            ],
            [
                'label' => 'Nuevo Proceso',
                'url' => '#',
            ],
        ];

        $etapas = Etapa::query()
            ->where('estado', true)
            ->orderBy('orden')
            ->get();

        return view(
            'procesos.procesos_orden.create',
            compact(
                'title',
                'breadcrumbs',
                'ordenTrabajo',
                'etapas'
            )
        );
    }

    public function store(
        Request $request,
        OrdenesTrabajo $ordenTrabajo
    ) {
        $validated = $request->validate([
            'etapa_id' => [
                'required',
                'integer',
                'exists:etapas,id'
            ],

            'fecha_inicio' => [
                'nullable',
                'date'
            ],

            'fecha_fin' => [
                'nullable',
                'date',
                'after_or_equal:fecha_inicio'
            ],

            'peso_entrada' => [
                'nullable',
                'numeric',
                'gte:0'
            ],

            'peso_salida' => [
                'nullable',
                'numeric',
                'gte:0'
            ],

            'observaciones' => [
                'nullable',
                'string'
            ],

            /*
             * La marca de cancelado. No es un estado mas: el estado sale de
             * las fechas, y esta es la unica cosa que no se puede deducir de
             * ellas, porque un proceso abandonado tiene las mismas fechas que
             * uno que se termino a tiempo.
             */
            'cancelado' => ['nullable', 'boolean'],
        ]);

        $validated['orden_trabajo_id'] = $ordenTrabajo->id;
        $validated['codigo'] = $this->generarCodigo($ordenTrabajo);

        /*
         * El estado no se deduce aqui: se deduce cuando se lee. Lo unico que
         * se guarda es si el proceso se marco como cancelado, con el 0. La
         * casilla "cancelado" no es un campo de la tabla, asi que se quita
         * antes de guardar.
         */
        $validated['estado'] = $this->estadoSegunLaMarca($validated);

        unset($validated['cancelado']);

        ProcesosOrden::create($validated);

        Alert::toast(
            'Proceso creado correctamente.'
        )->success()->flash();

        return redirect()->route(
            'procesos.ordenes_trabajo.show',
            $ordenTrabajo
        );
    }

    public function edit(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden
    ) {
        abort_unless(
            $procesoOrden->orden_trabajo_id === $ordenTrabajo->id,
            404
        );

        $title = "Editar Proceso";

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            [
                'label' => 'Órdenes de Trabajo',
                'url' => route(
                    'procesos.ordenes_trabajo.index'
                )
            ],
            [
                'label' => $ordenTrabajo->codigo,
                'url' => route(
                    'procesos.ordenes_trabajo.show',
                    $ordenTrabajo
                )
            ],
            [
                'label' => 'Editar Proceso'
            ],
        ];

        $etapas = Etapa::query()
            ->where(function ($query) use ($procesoOrden) {

                $query
                    ->where('estado', true)
                    ->orWhere('id', $procesoOrden->etapa_id);

            })
            ->orderBy('orden')
            ->get();

        return view(
            'procesos.procesos_orden.edit',
            compact(
                'title',
                'breadcrumbs',
                'ordenTrabajo',
                'procesoOrden',
                'etapas',
            )
        );
    }

    public function update(
        Request $request,
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden
    ) {
        abort_unless(
            $procesoOrden->orden_trabajo_id === $ordenTrabajo->id,
            404
        );

        $validated = $request->validate([

            'etapa_id' => [
                'required',
                'integer',
                Rule::exists('etapas', 'id')
                    ->whereNull('deleted_at')
                    ->where('estado', true),
            ],

            'fecha_inicio' => [
                'nullable',
                'date',
            ],

            'fecha_fin' => [
                'nullable',
                'date',
                'after_or_equal:fecha_inicio',
            ],

            'peso_entrada' => [
                'nullable',
                'numeric',
                'gte:0',
            ],

            'peso_salida' => [
                'nullable',
                'numeric',
                'gte:0',
            ],

            'observaciones' => [
                'nullable',
                'string',
            ],

            /*
             * La marca de cancelado. No es un estado mas: el estado sale de
             * las fechas, y esta es la unica cosa que no se puede deducir de
             * ellas, porque un proceso abandonado tiene las mismas fechas que
             * uno que se termino a tiempo.
             */
            'cancelado' => ['nullable', 'boolean'],
        ]);

        $validated['estado'] = $this->estadoSegunLaMarca($validated);

        unset($validated['cancelado']);

        $procesoOrden->update($validated);

        Alert::toast(
            'Proceso actualizado correctamente.'
        )->success()->flash();

        return redirect()->route(
            'procesos.ordenes_trabajo.show',
            $ordenTrabajo
        );
    }

    /**
     * El numero de estado que se guarda, a partir de la marca de cancelado.
     *
     * Cancelado es 0, que es el unico valor que el modelo respeta. Cualquier
     * otro caso se guarda como Pendiente, que es lo que el calculo ignora:
     * mientras el proceso no este marcado como cancelado, el estado sale de
     * las fechas y no de la columna.
     *
     * Se traduce la casilla a un numero y no al reves: el campo de la
     * pantalla es una casilla de marcar y no un desplegable, y si se
     * guardara el texto, alguien podria mandar un estado inventado que el
     * calculo no reconoceria.
     */
    private function estadoSegunLaMarca(array $validado): int
    {
        return ! empty($validado['cancelado'])
            ? ProcesosOrden::ESTADO_CANCELADO
            : ProcesosOrden::ESTADO_PENDIENTE;
    }

    public function destroy(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden
    ) {
        abort_unless(
            $procesoOrden->orden_trabajo_id === $ordenTrabajo->id,
            404
        );

        $procesoOrden->delete();

        Alert::toast(
            'Proceso eliminado correctamente.'
        )->success()->flash();

        return redirect()->route(
            'procesos.ordenes_trabajo.show',
            $ordenTrabajo
        );
    }

    private function generarCodigo(
        OrdenesTrabajo $ordenTrabajo
    ): string {
        $ultimo = ProcesosOrden::withTrashed()
            ->where(
                'orden_trabajo_id',
                $ordenTrabajo->id
            )
            ->orderByDesc('id')
            ->first();

        $numero = 1;

        if ($ultimo) {
            $partes = explode('-', $ultimo->codigo);

            $numero = ((int) end($partes)) + 1;
        }

        return sprintf(
            'P-%03d',
            $numero
        );
    }

    public function data(OrdenesTrabajo $ordenTrabajo, ProcesosOrdenDataTable $dataTable)
    {
        $dataTable->setOrdenTrabajoId($ordenTrabajo->id);
        return $dataTable->ajax();
    }
}

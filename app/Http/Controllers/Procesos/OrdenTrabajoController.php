<?php

namespace App\Http\Controllers\Procesos;

use App\DataTables\CostosOrdenDataTable;
use App\DataTables\EmpleadosSelectorDataTable;
use App\DataTables\OrdenesTrabajoDataTable;
use App\DataTables\ProcesosOrdenDataTable;
use App\DataTables\TrabajosEmpleadosDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Concerns\ValidaCostos;
use App\Http\Controllers\Controller;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use App\Models\TiposPagoEmpleado;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RealRashid\SweetAlert\Facades\Alert;

class OrdenTrabajoController extends Controller
{
    use AuthorizesModule;

    /*
     * Solo para los combos del modal de costos generales. El permiso sigue
     * siendo el de ordenes_trabajo: ver una OT no es lo mismo que poder
     * registrar costos en ella.
     */
    use ValidaCostos;

    public function __construct()
    {
        /*
         * El calendario y sus eventos cuentan como "ver": sin esto,
         * cualquier usuario autenticado los abria, porque el trait solo
         * vigila los metodos que se le indican y estos no estaban.
         */
        $this->authorizeModule(
            'ordenes_trabajo',
            ['index', 'show', 'calendario', 'eventos']
        );
    }

    /**
     * Display a listing of the resource.
     */
    public function index(OrdenesTrabajoDataTable $dataTable)
    {
        $title = "Órdenes de Trabajo";

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Órdenes de Trabajo'],
        ];

        return $dataTable->render(
            'procesos.ordenes_trabajo.index',
            compact('title', 'breadcrumbs')
        );
    }

    public function calendario()
    {
        $title = "Calendario de Órdenes de Trabajo";

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Órdenes de Trabajo', 'url' => route('procesos.ordenes_trabajo.index')],
            ['label' => 'Calendario'],
        ];

        return view(
            'procesos.ordenes_trabajo.calendario',
            compact('title', 'breadcrumbs')
        );
    }

    /**
     * Las ordenes de un tramo de fechas, en el formato que FullCalendar
     * espera.
     *
     * El calendario pide los eventos por fecha cada vez que se cambia de
     * mes o de semana, en vez de llevar todas las ordenes en la pagina. Con
     * las ordenes metidas en el HTML, al pulsar "mes siguiente" el
     * calendario se veria vacio.
     *
     * El color lo pone el servidor, desde el catalogo de estados del
     * modelo: el calendario no usa clases de Bootstrap, asi que necesita el
     * color en hexadecimal y no el nombre de la clase.
     */
    public function eventos(Request $request)
    {
        $validado = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ], [
            'start.required' => 'El calendario debe indicar la fecha de inicio.',
            'end.required' => 'El calendario debe indicar la fecha de fin.',
        ]);

        $inicio = Carbon::parse($validado['start'])->startOfDay();
        $fin = Carbon::parse($validado['end'])->endOfDay();

        $ordenes = OrdenesTrabajo::query()
            ->with('cliente')
            /*
             * El numero de procesos va con withCount y no contando uno por
             * uno: con las ordenes de un mes da igual, pero al pedir un
             * trimestre salen tantas consultas como ordenes.
             */
            ->withCount('procesos_ordenes')
            // Solo las del tramo pedido, por la fecha de la orden
            ->whereBetween(OrdenesTrabajo::FECHA, [$inicio, $fin])
            ->orderBy(OrdenesTrabajo::FECHA)
            ->get();

        $eventos = $ordenes->map(function (OrdenesTrabajo $orden) {
            $color = $orden->estadoColor();

            return [
                'id' => $orden->id,
                'title' => $orden->codigo,

                // La orden tiene una sola fecha, no un intervalo
                'start' => $orden->fecha?->format('Y-m-d'),
                'allDay' => true,

                'backgroundColor' => $color,
                'borderColor' => $color,
                'textColor' => '#ffffff',

                /*
                 * Lo que el modal necesita. Va en la respuesta y no se
                 * arma en el javascript con una ruta, para que el boton
                 * de "ir a ver" no dependa de que el js sepa construir
                 * urls.
                 */
                'extendedProps' => [
                    'codigo' => $orden->codigo,
                    'cliente' => $orden->cliente?->nombre ?? '—',
                    'estado' => $orden->estadoTexto(),
                    'estado_color' => $color,
                    'fecha' => $orden->fecha?->format('d/m/Y') ?? '—',
                    'peso' => number_format($orden->peso_mineral, 2, '.', ',')
                        . ' ' . $orden->unidad_peso,
                    'descripcion' => $orden->descripcion ?: '',
                    'procesos' => (int) $orden->procesos_ordenes_count,
                    'url' => route('procesos.ordenes_trabajo.show', $orden),
                ],
            ];
        })->all();

        return response()->json($eventos);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $title = "Ingresar Orden de Trabajo";

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            [
                'label' => 'Órdenes de Trabajo',
                'url' => route('procesos.ordenes_trabajo.index')
            ],
            ['label' => 'Ingresar Orden de Trabajo'],
        ];

        return view(
            'procesos.ordenes_trabajo.create',
            compact('title', 'breadcrumbs')
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => [
                'required',
                'integer',
                'exists:clientes,id',
            ],

            'fecha' => [
                'required',
                'date',
            ],

            'descripcion' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'peso_mineral' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'unidad_peso' => [
                'required',
                'string',
                'in:toneladas,kg,gramos',
            ],
        ]);

        $validated['codigo'] = $this->generarCodigo();
        $validated['estado'] = 1;

        $ordenTrabajo = OrdenesTrabajo::create($validated);

        Alert::toast(
            'Orden de trabajo creada correctamente.'
        )->success()->flash();

        return redirect()->route(
            'procesos.ordenes_trabajo.show',
            $ordenTrabajo
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(
        string                     $id,
        ProcesosOrdenDataTable     $dataTable,
        CostosOrdenDataTable       $costosDataTable
    )
    {
        $title = "Información de Orden de Trabajo";
        $ordenTrabajo = OrdenesTrabajo::findOrFail($id);
        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            [
                'label' => 'Órdenes de Trabajo',
                'url' => route('procesos.ordenes_trabajo.index')
            ],
            ['label' => 'Información de Orden de Trabajo'],
        ];

        if ($ordenTrabajo) {
            $dataTable->setOrdenTrabajoId($ordenTrabajo->id);
            $costosDataTable->setOrdenTrabajoId($ordenTrabajo->id);

            // Los combos del modal de costos generales
            $combos = $this->datosDeCombos();

            $categorias = $combos['categorias'];
            $monedas = $combos['monedas'];

            // Un costo se fecha el dia en que se registra, no un dia antes
            $fechaPorDefecto = now()->format('Y-m-d');

            return view(
                'procesos.ordenes_trabajo.show',
                compact(
                    'title',
                    'breadcrumbs',
                    'ordenTrabajo',
                    'dataTable',
                    'costosDataTable',
                    'categorias',
                    'monedas',
                    'fechaPorDefecto'
                )
            );
        } else {
            return redirect()->route('procesos.ordenes_trabajo.index');
        }

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $title = "Editar Orden de Trabajo";
        $ordenTrabajo = OrdenesTrabajo::findOrFail($id);
        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            [
                'label' => 'Órdenes de Trabajo',
                'url' => route('procesos.ordenes_trabajo.index')
            ],
            [
                'label' => 'Información de Orden de Trabajo',
                'url' => route(
                    'procesos.ordenes_trabajo.show',
                    $ordenTrabajo
                )
            ],
            ['label' => 'Editar Orden de Trabajo'],
        ];

        if ($ordenTrabajo) {
            return view(
                'procesos.ordenes_trabajo.edit',
                compact('title', 'breadcrumbs', 'ordenTrabajo')
            );
        } else {
            return redirect()->route('procesos.ordenes_trabajo.index');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $ordenTrabajo = OrdenesTrabajo::findOrFail($id);
        $tieneOperaciones = $ordenTrabajo
            ->procesos_ordenes()
            ->exists();

        if ($tieneOperaciones) {

            $validated = $request->validate([
                'fecha' => [
                    'required',
                    'date',
                ],

                'descripcion' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],
            ]);

        } else {

            $validated = $request->validate([
                'cliente_id' => [
                    'required',
                    'integer',
                    'exists:clientes,id',
                ],

                'fecha' => [
                    'required',
                    'date',
                ],

                'descripcion' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'peso_mineral' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],

                'unidad_peso' => [
                    'required',
                    'string',
                    'in:toneladas,kg,gramos',
                ],
            ]);
        }

        $ordenTrabajo->update($validated);

        Alert::toast(
            'Orden de trabajo actualizada correctamente.'
        )->success()->flash();

        return redirect()->route(
            'procesos.ordenes_trabajo.show',
            $ordenTrabajo->id
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OrdenesTrabajo $ordenTrabajo)
    {
        /*
         * TODO:
         *
         * Antes de eliminar una OT debemos validar:
         *
         * - Procesos registrados
         * - Costos
         * - Mano de obra
         * - Movimientos de inventario
         * - Producción
         * - Recuperaciones
         * - Liquidaciones
         * - Ingresos
         * - Cobros
         * - Pagos pendientes
         *
         * Estas validaciones se incorporarán cuando
         * dichos módulos estén implementados.
         */

        $ordenTrabajo->delete();

        Alert::toast(
            'Orden de trabajo eliminada correctamente.'
        )->success()->flash();

        return redirect()->route(
            'procesos.ordenes_trabajo.index'
        );
    }

    private function generarCodigo(): string
    {
        $anio = now()->year;

        $ultimo = OrdenesTrabajo::withTrashed()
            ->where('codigo', 'like', "OT-{$anio}-%")
            ->orderByDesc('id')
            ->first();

        $numero = 1;

        if ($ultimo) {
            $partes = explode('-', $ultimo->codigo);
            $numero = ((int)end($partes)) + 1;
        }

        return sprintf(
            'OT-%d-%04d',
            $anio,
            $numero
        );
    }
}

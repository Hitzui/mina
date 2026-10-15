<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\Compra;
use App\Models\MovimientoCaja;
use App\Models\MovimientosInventario;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportesController extends Controller
{
    use AuthorizesModule;

    private const REPORTES = [
        'caja' => 'Movimientos de caja',
        'cierres' => 'Cierres de caja',
        'cobros' => 'Cobros e ingresos recibidos',
        'ordenes' => 'Órdenes de trabajo (resumen)',
        'procesos' => 'Procesos de órdenes',
        'costos' => 'Costos por orden y proceso',
        'ingresos' => 'Ingresos facturados por orden',
        'produccion' => 'Producción registrada',
        'trabajos' => 'Trabajos y pagos de empleados',
        'compras' => 'Compras',
        'inventario' => 'Movimientos de inventario',
        'clientes' => 'Clientes y actividad',
    ];

    private const CATEGORIAS = [
        'caja' => ['nombre' => 'Caja e ingresos', 'reportes' => ['caja', 'cierres', 'cobros']],
        'ordenes' => ['nombre' => 'Órdenes y producción', 'reportes' => ['ordenes', 'procesos', 'costos', 'ingresos', 'produccion', 'trabajos']],
        'inventario' => ['nombre' => 'Compras e inventario', 'reportes' => ['compras', 'inventario']],
        'clientes' => ['nombre' => 'Clientes', 'reportes' => ['clientes']],
    ];

    public function __construct()
    {
        $this->middleware('permission:reportes.view')->only('index');
        $this->middleware('permission:reportes.export')->only('exportar');
    }

    public function index(Request $request)
    {
        $filtros = $this->validarFiltros($request);
        [$columnas, $filas] = $this->obtenerDatos($filtros);

        return view('reportes.index', [
            'title' => 'Reportes',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('home')],
                ['label' => 'Reportes'],
            ],
            'reportes' => self::REPORTES,
            'categorias' => self::CATEGORIAS,
            'reportesPorCategoria' => collect(self::CATEGORIAS)->mapWithKeys(fn ($c, $key) => [$key => collect($c['reportes'])->mapWithKeys(fn ($tipo) => [$tipo => self::REPORTES[$tipo]])])->all(),
            'categoria' => $filtros['categoria'],
            'tipo' => $filtros['tipo'],
            'filtros' => $filtros,
            'columnas' => $columnas,
            'filas' => $filas,
            'exportar' => auth()->user()->can('reportes.export'),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        abort_unless(in_array($formato, ['xlsx', 'pdf'], true), 404);
        $filtros = $this->validarFiltros($request);
        [$columnas, $filas] = $this->obtenerDatos($filtros);
        $nombre = 'reporte-'.$filtros['tipo'].'-'.$filtros['fecha_desde'].'-'.$filtros['fecha_hasta'];

        if ($formato === 'pdf') {
            return Pdf::loadView('reportes.pdf', [
                'titulo' => self::REPORTES[$filtros['tipo']],
                'filtros' => $filtros,
                'columnas' => $columnas,
                'filas' => $filas,
            ])->setPaper('a4', 'landscape')->download($nombre.'.pdf');
        }

        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Reporte');
        $hoja->setCellValue('A1', self::REPORTES[$filtros['tipo']]);
        $hoja->setCellValue('A2', 'Período: '.$filtros['fecha_desde'].' al '.$filtros['fecha_hasta']);
        foreach ($columnas as $indice => $columna) {
            $letra = Coordinate::stringFromColumnIndex($indice + 1);
            $hoja->setCellValue($letra.'4', $columna);
        }
        foreach ($filas as $filaIndice => $fila) {
            foreach (array_values($fila) as $columnaIndice => $valor) {
                $letra = Coordinate::stringFromColumnIndex($columnaIndice + 1);
                $hoja->setCellValue($letra.($filaIndice + 5), $valor);
            }
        }
        $ultimaColumna = Coordinate::stringFromColumnIndex(count($columnas));
        $hoja->getStyle('A4:'.$ultimaColumna.'4')->getFont()->setBold(true);
        foreach (range(1, count($columnas)) as $indice) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($indice))->setAutoSize(true);
        }

        $ruta = tempnam(sys_get_temp_dir(), 'reporte-');
        unlink($ruta);
        $ruta .= '.xlsx';
        (new Xlsx($libro))->save($ruta);

        return response()->download($ruta, $nombre.'.xlsx')->deleteFileAfterSend();
    }

    private function validarFiltros(Request $request): array
    {
        $filtros = $request->validate([
            'tipo' => ['nullable', 'in:'.implode(',', array_keys(self::REPORTES))],
            'categoria' => ['nullable', 'in:'.implode(',', array_keys(self::CATEGORIAS))],
            'orden' => ['nullable', 'string', 'max:30'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
        ]);

        $tipo = $filtros['tipo'] ?? 'caja';
        $categoriaPorDefecto = array_search(
            true,
            array_map(fn ($item) => in_array($tipo, $item['reportes'], true), self::CATEGORIAS),
            true
        );
        $categoria = $filtros['categoria'] ?? $categoriaPorDefecto ?: 'caja';
        if (! in_array($tipo, self::CATEGORIAS[$categoria]['reportes'], true)) {
            $tipo = self::CATEGORIAS[$categoria]['reportes'][0];
        }

        return [
            'categoria' => $categoria,
            'tipo' => $tipo,
            'fecha_desde' => $filtros['fecha_desde'] ?? now()->startOfMonth()->toDateString(),
            'fecha_hasta' => $filtros['fecha_hasta'] ?? today()->toDateString(),
            'orden' => trim($filtros['orden'] ?? ''),
        ];
    }

    private function obtenerDatos(array $filtros): array
    {
        return match ($filtros['tipo']) {
            'caja' => $this->movimientosCaja($filtros),
            'cierres' => $this->cierres($filtros),
            'cobros' => $this->cobros($filtros),
            'ordenes' => $this->ordenes($filtros),
            'procesos' => $this->procesos($filtros),
            'costos' => $this->costos($filtros),
            'ingresos' => $this->ingresos($filtros),
            'produccion' => $this->produccion($filtros),
            'trabajos' => $this->trabajos($filtros),
            'compras' => $this->compras($filtros),
            'inventario' => $this->inventario($filtros),
            'clientes' => $this->clientes($filtros),
        };
    }

    private function movimientosCaja(array $filtros): array
    {
        $filas = MovimientoCaja::query()->with(['caja', 'metodoPago', 'moneda'])
            ->whereBetween('fecha', [$filtros['fecha_desde'], $filtros['fecha_hasta']])
            ->orderBy('fecha')->orderBy('id')->get()
            ->map(fn ($m) => [$m->fecha->format('Y-m-d'), $m->caja?->nombre, ucfirst($m->tipo), $m->origen,
                $m->metodoPago?->nombre, $m->moneda?->codigo, $m->monto, $m->descripcion, $m->referencia])
            ->all();

        return [['Fecha', 'Caja', 'Tipo', 'Origen', 'Método', 'Moneda', 'Monto', 'Descripción', 'Referencia'], $filas];
    }

    private function cierres(array $filtros): array
    {
        $filas = DB::table('cierres_caja as c')
            ->join('cajas as ca', 'ca.id', '=', 'c.caja_id')
            ->join('detalle_cierres_caja as d', 'd.cierre_caja_id', '=', 'c.id')
            ->join('metodos_pago as mp', 'mp.id', '=', 'd.metodo_pago_id')
            ->join('monedas as mo', 'mo.id', '=', 'd.moneda_id')
            ->whereNull('c.deleted_at')->whereBetween('c.fecha_hasta', [$filtros['fecha_desde'], $filtros['fecha_hasta']])
            ->orderBy('c.fecha_hasta')->orderBy('c.id')
            ->get(['c.codigo', 'ca.nombre as caja', 'c.fecha_desde', 'c.fecha_hasta', 'mp.nombre as metodo', 'mo.codigo as moneda', 'd.monto_esperado', 'd.monto_contado', 'd.diferencia'])
            ->map(fn ($r) => [$r->codigo, $r->caja, $r->fecha_desde, $r->fecha_hasta, $r->metodo, $r->moneda, $r->monto_esperado, $r->monto_contado, $r->diferencia])
            ->all();

        return [['Cierre', 'Caja', 'Desde', 'Hasta', 'Método', 'Moneda', 'Esperado', 'Contado', 'Diferencia'], $filas];
    }

    private function cobros(array $filtros): array
    {
        $filas = Cobro::query()->with(['cliente', 'caja', 'metodo_pago', 'moneda'])
            ->whereBetween('fecha', [$filtros['fecha_desde'], $filtros['fecha_hasta']])
            ->orderBy('fecha')->orderBy('id')->get()
            ->map(fn ($c) => [$c->fecha->format('Y-m-d'), $c->codigo, $c->cliente?->nombre, Cobro::ESTADOS[$c->estado]['texto'] ?? $c->estado,
                $c->metodo_pago?->nombre, $c->moneda?->codigo, $c->montoCalculado, $c->caja?->nombre, $c->referencia])
            ->all();

        return [['Fecha', 'Código', 'Cliente', 'Estado', 'Método', 'Moneda', 'Monto', 'Caja', 'Referencia'], $filas];
    }

    private function ordenes(array $filtros): array
    {
        $costos = DB::table('movimientos_costos')->whereNull('deleted_at')->select('orden_trabajo_id', DB::raw('SUM(costo_total_nio) as total'))->groupBy('orden_trabajo_id');
        $ingresos = DB::table('ingresos')->whereNull('deleted_at')->select('orden_trabajo_id', DB::raw('SUM(total_nio) as total'))->groupBy('orden_trabajo_id');
        $manoObra = DB::table('trabajos_empleados as t')->join('procesos_orden as p', 'p.id', '=', 't.proceso_orden_id')
            ->whereNull('t.deleted_at')->select('p.orden_trabajo_id', DB::raw('SUM(t.total_nio) as total'))->groupBy('p.orden_trabajo_id');
        $depreciacion = DB::query()->fromSub(\App\Models\ProcesoEquipo::consultaParaCostos(), 'pe')->join('procesos_orden as p', 'p.id', '=', 'pe.proceso_orden_id')
            ->whereNull('pe.deleted_at')->select('p.orden_trabajo_id', DB::raw('SUM(pe.costo_uso_nio) as total'))->groupBy('p.orden_trabajo_id');
        $materiales = DB::table('movimientos_inventario')->whereNull('deleted_at')->where('tipo', MovimientosInventario::TIPO_SALIDA)
            ->whereNotNull('proceso_orden_id')->select('orden_trabajo_id', DB::raw('SUM(costo_total_nio) as total'))->groupBy('orden_trabajo_id');
        $filas = OrdenesTrabajo::query()->with('cliente')
            ->leftJoinSub($costos, 'costos_reporte', fn ($join) => $join->on('costos_reporte.orden_trabajo_id', '=', 'ordenes_trabajo.id'))
            ->leftJoinSub($ingresos, 'ingresos_reporte', fn ($join) => $join->on('ingresos_reporte.orden_trabajo_id', '=', 'ordenes_trabajo.id'))
            ->leftJoinSub($manoObra, 'mano_obra_reporte', fn ($join) => $join->on('mano_obra_reporte.orden_trabajo_id', '=', 'ordenes_trabajo.id'))
            ->leftJoinSub($depreciacion, 'depreciacion_reporte', fn ($join) => $join->on('depreciacion_reporte.orden_trabajo_id', '=', 'ordenes_trabajo.id'))
            ->leftJoinSub($materiales, 'materiales_reporte', fn ($join) => $join->on('materiales_reporte.orden_trabajo_id', '=', 'ordenes_trabajo.id'))
            ->whereBetween('ordenes_trabajo.fecha', [$filtros['fecha_desde'], $filtros['fecha_hasta']])
            ->when($filtros['orden'] !== '', fn ($q) => $q->where('ordenes_trabajo.codigo', 'like', '%'.$filtros['orden'].'%'))
            ->orderBy('ordenes_trabajo.fecha')
            ->get(['ordenes_trabajo.id', 'ordenes_trabajo.codigo', 'ordenes_trabajo.fecha', 'ordenes_trabajo.cliente_id', 'ordenes_trabajo.estado', 'ordenes_trabajo.peso_mineral', 'ordenes_trabajo.unidad_peso', 'costos_reporte.total as costos_nio', 'ingresos_reporte.total as ingresos_nio', 'mano_obra_reporte.total as mano_obra_nio', 'depreciacion_reporte.total as depreciacion_nio', 'materiales_reporte.total as materiales_nio'])
            ->map(fn ($o) => [$o->fecha->format('Y-m-d'), $o->codigo, $o->cliente?->nombre, OrdenesTrabajo::ESTADOS[$o->estado]['texto'] ?? $o->estado,
                $o->peso_mineral, $o->unidad_peso, round((float) $o->ingresos_nio, 2), round((float) $o->costos_nio, 2),
                round((float) $o->mano_obra_nio, 2), round((float) $o->depreciacion_nio, 2), round((float) $o->materiales_nio, 2),
                round((float) $o->costos_nio + (float) $o->mano_obra_nio + (float) $o->depreciacion_nio + (float) $o->materiales_nio, 2)])
            ->all();

        return [['Fecha', 'Orden', 'Cliente', 'Estado', 'Peso', 'Unidad', 'Ingresos NIO', 'Otros costos NIO', 'Mano de obra NIO', 'Uso de equipos NIO', 'Materiales NIO', 'Costo total NIO'], $filas];
    }

    private function procesos(array $filtros): array
    {
        $filas = DB::table('procesos_orden as p')
            ->join('ordenes_trabajo as o', 'o.id', '=', 'p.orden_trabajo_id')
            ->join('clientes as cl', 'cl.id', '=', 'o.cliente_id')
            ->join('etapas as e', 'e.id', '=', 'p.etapa_id')
            ->whereNull('p.deleted_at')->whereNull('o.deleted_at')
            ->whereBetween('p.created_at', [$filtros['fecha_desde'].' 00:00:00', $filtros['fecha_hasta'].' 23:59:59'])
            ->when($filtros['orden'] !== '', fn ($q) => $q->where('o.codigo', 'like', '%'.$filtros['orden'].'%'))
            ->orderBy('o.codigo')->orderBy('p.codigo')
            ->get(['o.codigo as orden', 'cl.nombre as cliente', 'p.codigo as proceso', 'e.nombre as etapa', 'p.fecha_inicio', 'p.fecha_fin', 'p.peso_entrada', 'p.peso_salida', 'p.estado'])
            ->map(fn ($p) => [$p->orden, $p->cliente, $p->proceso, $p->etapa, $p->fecha_inicio, $p->fecha_fin,
                $p->peso_entrada, $p->peso_salida, ProcesosOrden::ESTADOS[$p->estado]['texto'] ?? $p->estado])
            ->all();

        return [['Orden', 'Cliente', 'Código de proceso', 'Etapa', 'Inicio', 'Fin', 'Peso entrada', 'Peso salida', 'Estado'], $filas];
    }

    private function costos(array $filtros): array
    {
        $filas = DB::table('movimientos_costos as c')
            ->join('ordenes_trabajo as o', 'o.id', '=', 'c.orden_trabajo_id')
            ->join('clientes as cl', 'cl.id', '=', 'o.cliente_id')
            ->join('categorias_costos as cc', 'cc.id', '=', 'c.categoria_costo_id')
            ->join('monedas as mo', 'mo.id', '=', 'c.moneda_id')
            ->leftJoin('procesos_orden as p', 'p.id', '=', 'c.proceso_orden_id')
            ->leftJoin('etapas as e', 'e.id', '=', 'p.etapa_id')
            ->whereNull('c.deleted_at')->whereNull('o.deleted_at')
            ->whereBetween('c.fecha', [$filtros['fecha_desde'], $filtros['fecha_hasta']])
            ->when($filtros['orden'] !== '', fn ($q) => $q->where('o.codigo', 'like', '%'.$filtros['orden'].'%'))
            ->orderBy('c.fecha')->orderBy('o.codigo')
            ->get(['c.fecha', 'o.codigo as orden', 'cl.nombre as cliente', 'p.codigo as proceso', 'e.nombre as etapa', 'cc.nombre as categoria', 'c.descripcion', 'c.cantidad', 'mo.codigo as moneda', 'c.costo_total', 'c.costo_total_nio'])
            ->map(fn ($c) => [$c->fecha, $c->orden, $c->cliente, $c->proceso ?? 'General', $c->etapa ?? '—', $c->categoria,
                $c->descripcion, $c->cantidad, $c->moneda, $c->costo_total, $c->costo_total_nio])
            ->all();

        return [['Fecha', 'Orden', 'Cliente', 'Proceso', 'Etapa', 'Categoría', 'Descripción', 'Cantidad', 'Moneda', 'Costo', 'Costo NIO'], $filas];
    }

    private function ingresos(array $filtros): array
    {
        $filas = DB::table('ingresos as i')
            ->join('ordenes_trabajo as o', 'o.id', '=', 'i.orden_trabajo_id')
            ->join('clientes as cl', 'cl.id', '=', 'o.cliente_id')
            ->join('tipos_ingreso as ti', 'ti.id', '=', 'i.tipo_ingreso_id')
            ->join('monedas as mo', 'mo.id', '=', 'i.moneda_id')
            ->whereNull('i.deleted_at')->whereNull('o.deleted_at')
            ->whereBetween('i.fecha', [$filtros['fecha_desde'], $filtros['fecha_hasta']])
            ->when($filtros['orden'] !== '', fn ($q) => $q->where('o.codigo', 'like', '%'.$filtros['orden'].'%'))
            ->orderBy('i.fecha')->orderBy('o.codigo')
            ->get(['i.fecha', 'o.codigo as orden', 'cl.nombre as cliente', 'ti.nombre as tipo', 'i.descripcion', 'i.cantidad', 'i.unidad_medida', 'mo.codigo as moneda', 'i.total', 'i.total_nio'])
            ->map(fn ($i) => [$i->fecha, $i->orden, $i->cliente, $i->tipo, $i->descripcion, $i->cantidad, $i->unidad_medida, $i->moneda, $i->total, $i->total_nio])
            ->all();

        return [['Fecha', 'Orden', 'Cliente', 'Tipo de ingreso', 'Descripción', 'Cantidad', 'Unidad', 'Moneda', 'Total', 'Total NIO'], $filas];
    }

    private function produccion(array $filtros): array
    {
        $filas = DB::table('producciones as pr')
            ->join('ordenes_trabajo as o', 'o.id', '=', 'pr.orden_trabajo_id')
            ->join('clientes as cl', 'cl.id', '=', 'o.cliente_id')
            ->join('tipos_produccion as tp', 'tp.id', '=', 'pr.tipo_produccion_id')
            ->leftJoin('procesos_orden as p', 'p.id', '=', 'pr.proceso_orden_id')
            ->whereNull('pr.deleted_at')->whereNull('o.deleted_at')
            ->whereBetween('pr.fecha', [$filtros['fecha_desde'], $filtros['fecha_hasta']])
            ->when($filtros['orden'] !== '', fn ($q) => $q->where('o.codigo', 'like', '%'.$filtros['orden'].'%'))
            ->orderBy('pr.fecha')->orderBy('o.codigo')
            ->get(['pr.fecha', 'o.codigo as orden', 'cl.nombre as cliente', 'p.codigo as proceso', 'tp.nombre as tipo', 'pr.descripcion', 'pr.cantidad', 'pr.unidad_medida', 'pr.observaciones'])
            ->map(fn ($p) => [$p->fecha, $p->orden, $p->cliente, $p->proceso ?? '—', $p->tipo, $p->descripcion, $p->cantidad, $p->unidad_medida, $p->observaciones])
            ->all();

        return [['Fecha', 'Orden', 'Cliente', 'Proceso', 'Tipo de producción', 'Descripción', 'Cantidad', 'Unidad', 'Observaciones'], $filas];
    }

    private function trabajos(array $filtros): array
    {
        $filas = DB::table('trabajos_empleados as t')
            ->join('empleados as em', 'em.id', '=', 't.empleado_id')
            ->join('monedas as mo', 'mo.id', '=', 't.moneda_id')
            ->join('tipos_pago_empleado as tp', 'tp.id', '=', 't.tipo_pago_id')
            ->leftJoin('procesos_orden as p', 'p.id', '=', 't.proceso_orden_id')
            ->leftJoin('ordenes_trabajo as o', function ($join) {
                $join->on('o.id', '=', 'p.orden_trabajo_id');
            })
            ->whereNull('t.deleted_at')->whereBetween('t.fecha', [$filtros['fecha_desde'], $filtros['fecha_hasta']])
            ->when($filtros['orden'] !== '', fn ($q) => $q->where('o.codigo', 'like', '%'.$filtros['orden'].'%'))
            ->orderBy('t.fecha')->orderBy('em.nombre')
            ->get(['t.fecha', 'em.nombre as empleado', 'o.codigo as orden', 'p.codigo as proceso', 'tp.nombre as tipo_pago', 't.descripcion', 't.cantidad', 't.unidad', 't.tarifa', 't.total', 'mo.codigo as moneda', 't.total_nio'])
            ->map(fn ($t) => [$t->fecha, $t->empleado, $t->orden ?? '—', $t->proceso ?? '—', $t->tipo_pago, $t->descripcion,
                $t->cantidad, $t->unidad, $t->tarifa, $t->total, $t->moneda, $t->total_nio])
            ->all();

        return [['Fecha', 'Empleado', 'Orden', 'Proceso', 'Tipo de pago', 'Descripción', 'Cantidad', 'Unidad', 'Tarifa', 'Total', 'Moneda', 'Total NIO'], $filas];
    }

    private function compras(array $filtros): array
    {
        $filas = Compra::query()->with(['proveedor', 'moneda'])
            ->whereBetween('fecha', [$filtros['fecha_desde'], $filtros['fecha_hasta']])
            ->orderBy('fecha')->orderBy('id')->get()
            ->map(fn ($c) => [$c->fecha->format('Y-m-d'), $c->codigo, $c->proveedor?->nombre, $c->numero_documento, Compra::ESTADOS[$c->estado]['texto'] ?? $c->estado, $c->moneda?->codigo, $c->subtotal, $c->impuesto, $c->total])
            ->all();

        return [['Fecha', 'Compra', 'Proveedor', 'Documento', 'Estado', 'Moneda', 'Subtotal', 'Impuesto', 'Total'], $filas];
    }

    private function inventario(array $filtros): array
    {
        $filas = MovimientosInventario::query()->with(['producto', 'moneda', 'orden_trabajo'])
            ->whereBetween('fecha', [$filtros['fecha_desde'].' 00:00:00', $filtros['fecha_hasta'].' 23:59:59'])
            ->orderBy('fecha')->orderBy('id')->get()
            ->map(fn ($m) => [$m->fecha->format('Y-m-d H:i'), $m->producto?->nombre, $m->tipo, $m->cantidad, $m->moneda?->codigo,
                $m->costo_total, $m->costo_total_nio, $m->orden_trabajo?->codigo, $m->referencia])
            ->all();

        return [['Fecha', 'Producto', 'Movimiento', 'Cantidad', 'Moneda', 'Costo', 'Costo NIO', 'Orden', 'Referencia'], $filas];
    }

    private function clientes(array $filtros): array
    {
        $filas = Cliente::query()->withCount([
            'ordenes_trabajos as ordenes_periodo' => fn ($q) => $q->whereBetween('fecha', [$filtros['fecha_desde'], $filtros['fecha_hasta']]),
            'cobros as cobros_periodo' => fn ($q) => $q->whereBetween('fecha', [$filtros['fecha_desde'], $filtros['fecha_hasta']]),
        ])
            ->orderBy('nombre')->get()
            ->map(fn ($c) => [$c->nombre, $c->telefono, $c->direccion, $c->estado ? 'Activo' : 'Inactivo', $c->ordenes_periodo, $c->cobros_periodo])
            ->all();

        return [['Cliente', 'Teléfono', 'Dirección', 'Estado', 'Órdenes en período', 'Cobros en período'], $filas];
    }
}

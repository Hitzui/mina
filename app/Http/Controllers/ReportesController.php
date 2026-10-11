<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\Compra;
use App\Models\MovimientoCaja;
use App\Models\MovimientosInventario;
use App\Models\OrdenesTrabajo;
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
        'ordenes' => 'Órdenes, ingresos y costos',
        'compras' => 'Compras',
        'inventario' => 'Movimientos de inventario',
        'clientes' => 'Clientes y actividad',
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
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
        ]);

        return [
            'tipo' => $filtros['tipo'] ?? 'caja',
            'fecha_desde' => $filtros['fecha_desde'] ?? now()->startOfMonth()->toDateString(),
            'fecha_hasta' => $filtros['fecha_hasta'] ?? today()->toDateString(),
        ];
    }

    private function obtenerDatos(array $filtros): array
    {
        return match ($filtros['tipo']) {
            'caja' => $this->movimientosCaja($filtros),
            'cierres' => $this->cierres($filtros),
            'cobros' => $this->cobros($filtros),
            'ordenes' => $this->ordenes($filtros),
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
        $filas = OrdenesTrabajo::query()->with('cliente')
            ->leftJoinSub($costos, 'costos_reporte', fn ($join) => $join->on('costos_reporte.orden_trabajo_id', '=', 'ordenes_trabajo.id'))
            ->leftJoinSub($ingresos, 'ingresos_reporte', fn ($join) => $join->on('ingresos_reporte.orden_trabajo_id', '=', 'ordenes_trabajo.id'))
            ->whereBetween('ordenes_trabajo.fecha', [$filtros['fecha_desde'], $filtros['fecha_hasta']])
            ->orderBy('ordenes_trabajo.fecha')
            ->get(['ordenes_trabajo.id', 'ordenes_trabajo.codigo', 'ordenes_trabajo.fecha', 'ordenes_trabajo.cliente_id', 'ordenes_trabajo.estado', 'ordenes_trabajo.peso_mineral', 'costos_reporte.total as costos_nio', 'ingresos_reporte.total as ingresos_nio'])
            ->map(fn ($o) => [$o->fecha->format('Y-m-d'), $o->codigo, $o->cliente?->nombre, OrdenesTrabajo::ESTADOS[$o->estado]['texto'] ?? $o->estado,
                $o->peso_mineral, $o->unidad_peso, round((float) $o->ingresos_nio, 2), round((float) $o->costos_nio, 2)])
            ->all();

        return [['Fecha', 'Orden', 'Cliente', 'Estado', 'Peso', 'Unidad', 'Ingresos NIO', 'Costos NIO'], $filas];
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

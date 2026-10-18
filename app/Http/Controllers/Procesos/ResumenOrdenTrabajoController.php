<?php

namespace App\Http\Controllers\Procesos;

use App\Http\Controllers\Controller;
use App\Models\OrdenesTrabajo;
use App\Services\ResumenOrdenTrabajo;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ResumenOrdenTrabajoController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:ordenes_trabajo.view')->only('show');
        $this->middleware('permission:reportes.export')->only(['pdf', 'xlsx']);
    }

    public function show(OrdenesTrabajo $ordenTrabajo, ResumenOrdenTrabajo $resumenador)
    {
        return view('procesos.ordenes_trabajo.resumen', [
            'title' => 'Resumen de orden '.$ordenTrabajo->codigo,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('home')],
                ['label' => 'Órdenes de Trabajo', 'url' => route('procesos.ordenes_trabajo.index')],
                ['label' => $ordenTrabajo->codigo, 'url' => route('procesos.ordenes_trabajo.show', $ordenTrabajo)],
                ['label' => 'Resumen operativo y financiero'],
            ],
            ...$resumenador->paraOrden($ordenTrabajo),
            'puedeExportar' => auth()->user()->can('reportes.export'),
        ]);
    }

    public function pdf(OrdenesTrabajo $ordenTrabajo, ResumenOrdenTrabajo $resumenador)
    {
        $data = $resumenador->paraOrden($ordenTrabajo);
        $pdf = Pdf::loadView('procesos.ordenes_trabajo.resumen_pdf', [
            'title' => 'Resumen operativo y financiero',
            ...$data,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('resumen-orden-'.$ordenTrabajo->codigo.'.pdf');
    }

    public function xlsx(OrdenesTrabajo $ordenTrabajo, ResumenOrdenTrabajo $resumenador)
    {
        $data = $resumenador->paraOrden($ordenTrabajo);
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Resumen de orden');
        $sheet->setCellValue('A1', 'Resumen operativo y financiero · '.$ordenTrabajo->codigo);
        $sheet->setCellValue('A2', 'Cliente: '.($ordenTrabajo->cliente?->nombre ?? 'Sin cliente'));
        $sheet->setCellValue('A3', 'Estado: '.$ordenTrabajo->estadoTexto());
        $sheet->setCellValue('A5', 'Indicador');
        $sheet->setCellValue('B5', 'Importe / cantidad');
        $metrics = [
            ['Ingresos registrados NIO', $data['ingresos']['total_nio']],
            ['Valor del oro de la empresa NIO', $data['resultado_financiero']['oro_empresa_nio']],
            ['Tarifa pactada NIO', $data['resultado_financiero']['tarifa_pactada_nio']],
            ['Resultado real conocido NIO', $data['resultado_financiero']['resultado_real_nio'] ?? 'Sin ingresos registrados'],
            ['Resultado proyectado NIO', $data['resultado_financiero']['resultado_proyectado_nio'] ?? 'Sin acuerdo valorable'],
            ['Estado del resultado', $data['resultado_financiero']['completo'] ? 'Sin pendientes conocidos' : 'Pendiente de completar datos'],
            ['Costos directos de procesos NIO', $data['costos']['directos_nio']],
            ['Costos generales NIO', $data['costos']['generales_nio']],
            ['Costos generales distribuidos NIO', $data['costos']['generales_distribuidos_nio']],
            ['Costos generales pendientes NIO', $data['costos']['generales_pendientes_nio']],
            ['Costos conocidos de la orden NIO', $data['costos']['total_conocido_nio']],
            ['Costos directos sin equivalente NIO', $data['costos']['directos_sin_equivalente']],
            ['Costos generales sin equivalente NIO', $data['costos']['generales_sin_equivalente']],
            ['Ingresos sin equivalente NIO', $data['ingresos']['sin_equivalente']],
        ];
        foreach ($metrics as $index => [$label, $value]) {
            $row = $index + 6;
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, $value);
        }
        $header = count($metrics) + 6;
        $columns = ['Proceso', 'Etapa', 'Estado', 'Inicio', 'Fin', 'Equipos asignados', 'Costo directo NIO', 'General asignado NIO', 'Costo total con general NIO'];
        foreach ($columns as $index => $column) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).$header, $column);
        }
        foreach ($data['procesos'] as $index => $item) {
            $process = $item['proceso'];
            $row = $header + $index + 1;
            $values = [
                $process->codigo,
                $process->etapa?->nombre,
                $process->estadoTexto(),
                $process->fecha_inicio?->format('Y-m-d'),
                $process->fecha_fin?->format('Y-m-d'),
                $item['equipos']->map(fn ($uso) => trim(($uso->equipo?->codigo ?? '').' · '.($uso->equipo?->nombre ?? '')))->filter()->join(', '),
                $item['costo_directo_nio'],
                $item['gasto_general_asignado_nio'],
                $item['costo_total_nio'],
            ];
            foreach ($values as $columnIndex => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 1).$row, $value ?? '');
            }
        }
        $sheet->getStyle('A5:B5')->getFont()->setBold(true);
        $sheet->getStyle('A'.$header.':I'.$header)->getFont()->setBold(true);
        foreach (range(1, count($columns)) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }
        $path = tempnam(sys_get_temp_dir(), 'resumen-orden-');
        unlink($path);
        $path .= '.xlsx';
        (new Xlsx($book))->save($path);

        return response()->download($path, 'resumen-orden-'.$ordenTrabajo->codigo.'.xlsx')->deleteFileAfterSend();
    }
}

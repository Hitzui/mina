<?php

use App\Http\Controllers\ClienteController;
use App\Http\Controllers\Configuracion\CategoriaCostoController;
use App\Http\Controllers\Configuracion\TipoPagoEmpleadoController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\empleados\EmpleadoPagoController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\EtapaController;
use App\Http\Controllers\Inventario\MaterialesController;
use App\Http\Controllers\Inventario\ProductoController;
use App\Http\Controllers\Procesos\CostosOrdenController;
use App\Http\Controllers\Procesos\OrdenesTrabajo\CostosProcesoController;
use App\Http\Controllers\Procesos\OrdenesTrabajo\MaterialesProcesoController;
use App\Http\Controllers\Procesos\OrdenesTrabajo\ProcesoEquipoController;
use App\Http\Controllers\Procesos\OrdenesTrabajo\TrabajosEmpleadoController;
use App\Http\Controllers\Procesos\OrdenTrabajoController;
use App\Http\Controllers\Procesos\ProcesoOrdenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::get('/', function () {
    return view('welcome', ['title' => 'This is Title', 'breadcrumbs' => []]);
})->name('home');

/*
|--------------------------------------------------------------------------
| Rutas de la aplicacion
|--------------------------------------------------------------------------
|
| Todo el panel administrativo y de procesos exige un usuario autenticado.
| El permiso especifico de cada accion lo aplica el constructor de cada
| controlador mediante Concerns\AuthorizesModule.
|
*/

Route::middleware('auth')->group(function () {

Route::get(
    'admin/clientes/selector',
    [ClienteController::class, 'selector']
)->name('admin.clientes.selector');

Route::resource('admin/clientes', ClienteController::class)->names('admin.clientes');


Route::get(
    'admin/empleados/selector/data',
    [EmpleadoController::class, 'selectorData']
)->name('admin.empleados.selector.data');

Route::resource('admin/empleados', EmpleadoController::class)->names('admin.empleados');

/*
|--------------------------------------------------------------------------
| Pagos y tarifas de empleados
|--------------------------------------------------------------------------
*/

Route::prefix('admin/empleados/{empleado}/pagos')
    ->name('admin.empleados.pagos.')
    ->group(function () {

        Route::get(
            'data',
            [EmpleadoPagoController::class, 'index']
        )->name('data');

        Route::get(
            'create',
            [EmpleadoPagoController::class, 'create']
        )->name('create');

        Route::post(
            '/',
            [EmpleadoPagoController::class, 'store']
        )->name('store');

        Route::get(
            '{empleadoPago}',
            [EmpleadoPagoController::class, 'show']
        )->name('show');

        Route::get(
            '{empleadoPago}/edit',
            [EmpleadoPagoController::class, 'edit']
        )->name('edit');

        Route::put(
            '{empleadoPago}',
            [EmpleadoPagoController::class, 'update']
        )->name('update');

        Route::delete(
            '{empleadoPago}',
            [EmpleadoPagoController::class, 'destroy']
        )->name('destroy');
    });

Route::resource('admin/etapas', EtapaController::class)->names('admin.etapas');

/*
|--------------------------------------------------------------------------
| Equipos
|--------------------------------------------------------------------------
|
| Los equipos son dato maestro del taller. Del valor de adquisicion, el
| valor residual y la vida util sale la depreciacion diaria, que despues
| se cobra a cada proceso donde se use el equipo.
|
*/
Route::resource('admin/equipos', EquipoController::class)->names('admin.equipos');

Route::get('procesos/ordenes-trabajo/calendario',
    [OrdenTrabajoController::class, 'calendario'])
    ->name('procesos.ordenes_trabajo.calendario');
Route::get('procesos/ordenes-trabajo/listado',
    [OrdenTrabajoController::class, 'index'])
    ->name('procesos.ordenes_trabajo.listado');
/*
| Los eventos del calendario, por tramo de fechas.
|
| Va antes del resource a proposito: la ruta del resource para ver una
| orden es {ordenTrabajo}, y sin este orden se comeria la palabra
| "eventos" como si fuera el codigo de una orden.
*/
Route::get('procesos/ordenes-trabajo/eventos',
    [OrdenTrabajoController::class, 'eventos'])
    ->name('procesos.ordenes_trabajo.eventos');
Route::resource('procesos/ordenes-trabajo', OrdenTrabajoController::class)
    /*
     * El resource genera el placeholder {ordenes_trabajo} a partir del
     * nombre en plural, pero destroy() recibe $ordenTrabajo. Sin esto
     * los nombres no coinciden y Laravel NO hace el route model binding:
     * inyecta un modelo vacio, delete() no borra nada y aun asi se
     * muestra "Orden de trabajo eliminada correctamente".
     */
    ->parameters([
        'ordenes-trabajo' => 'ordenTrabajo',
    ])
    ->names('procesos.ordenes_trabajo');


Route::prefix('procesos/ordenes-trabajo/{ordenTrabajo}')
    ->name('procesos.ordenes_trabajo.procesos.')
    ->group(function () {

        Route::get(
            'procesos/create',
            [ProcesoOrdenController::class, 'create']
        )->name('create');

        Route::post(
            'procesos',
            [ProcesoOrdenController::class, 'store']
        )->name('store');

        /*
        |--------------------------------------------------------------------------
        | DataTable
        |--------------------------------------------------------------------------
        */
        Route::get(
            'procesos/data',
            [ProcesoOrdenController::class, 'data']
        )->name('data');

        /*
        | El proceso es donde vive el trabajo de los empleados, asi que
        | necesita pantalla propia: desde ahi se ven y se registran los
        | trabajos del proceso y cuanto se le paga a cada uno.
        */
        Route::get(
            'procesos/{procesoOrden}',
            [ProcesoOrdenController::class, 'show']
        )->name('show');

        Route::get(
            'procesos/{procesoOrden}/edit',
            [ProcesoOrdenController::class, 'edit']
        )->name('edit');

        Route::put(
            'procesos/{procesoOrden}',
            [ProcesoOrdenController::class, 'update']
        )->name('update');

        Route::delete(
            'procesos/{procesoOrden}',
            [ProcesoOrdenController::class, 'destroy']
        )->name('destroy');
    });


Route::resource(
    'configuracion/categorias-costos',
    CategoriaCostoController::class
)->names('configuracion.categorias_costos');


Route::resource(
    'configuracion/tipos-pago-empleado',
    TipoPagoEmpleadoController::class
)
    ->parameters([
        'tipos-pago-empleado' => 'tipoPagoEmpleado',
    ])
    ->names('configuracion.tipos_pago_empleado');


/*
| El almacen: el catalogo de materiales y el kardex de lo que entra y sale.
|
| Las salidas sin proceso tambien se registran aqui, y son las que se
| usan para una salida que no corresponde a ningun proceso (una muestra,
| una venta). El consumo de un proceso tiene su propia pantalla, dentro de
| la orden, porque ahi es donde se sabe a que etapa se le carga.
|
| No hay pantalla de editar un movimiento a proposito: corregir uno quiere
| decir deshacerlo y volverlo a registrar. Editar la cantidad en su lugar
| tendria que volver a tocar el saldo por el camino inverso, y con ello la
| oportunidad de que el almacen y el kardex se queden discrepantes. Dejar
| las rutas de edicion sin implementar daria un error 500 feo a quien las
| encontrara, asi que directamente no se registran.
*/
Route::resource(
    'inventario/movimientos',
    MaterialesController::class
)
    ->only(['index', 'create', 'store', 'show', 'destroy'])
    ->parameters([
        'movimientos' => 'movimiento',
    ])
    ->names('inventario.movimientos');

Route::resource(
    'inventario/productos',
    ProductoController::class
)
    ->parameters([
        'productos' => 'producto',
    ])
    ->names('inventario.productos');


/*
| Los trabajos de los empleados cuelgan de un proceso, no de la orden
| directamente. La orden sigue estando en la URL porque es el contexto de
| navegación, y ademas sirve para comprobar que el proceso pertenece a esa
| orden y no a otra.
*/
Route::get(
    'procesos/ordenes-trabajo/{ordenTrabajo}/procesos/{procesoOrden}/trabajos-empleados/tarifa',
    [TrabajosEmpleadoController::class, 'tarifaVigente']
)->name('procesos.ordenes_trabajo.procesos.trabajos_empleados.tarifa');

Route::resource(
    'procesos/ordenes-trabajo/{ordenTrabajo}/procesos/{procesoOrden}/trabajos-empleados',
    TrabajosEmpleadoController::class
)->names('procesos.ordenes_trabajo.procesos.trabajos_empleados');


/*
| Los equipos usados en el proceso. Igual que los trabajos, cuelgan del
| proceso y no de la orden: se llegan por el proceso, que es quien sabe
| de que orden se trata.
*/

/*
| Esta va antes que el resource a proposito: la ruta del resource para
| ver uno es equipos/{procesoEquipo}, y sin este orden se comeria la
| palabra depreciacion como si fuera el id de una asignacion.
*/
Route::get(
    'procesos/ordenes-trabajo/{ordenTrabajo}/procesos/{procesoOrden}/equipos/depreciacion',
    [ProcesoEquipoController::class, 'depreciacion']
)->name('procesos.ordenes_trabajo.procesos.equipos.depreciacion');

/*
| El resource deduciria {equipo} del ultimo segmento, y ese nombre lo
| busca en la tabla equipos, que es la de los equipos maestros, no la de
| las asignaciones. Se le fija {procesoEquipo} para que el enlace de
| modelo resuelva contra proceso_equipos.
*/
Route::resource(
    'procesos/ordenes-trabajo/{ordenTrabajo}/procesos/{procesoOrden}/equipos',
    ProcesoEquipoController::class
)
    ->parameters([
        'equipos' => 'procesoEquipo',
    ])
    ->names('procesos.ordenes_trabajo.procesos.equipos');


/*
| Los costos de la orden que no son de un proceso: alquiler, transporte,
| un insumo suelto. Van con proceso_orden_id en NULL, que es para lo que
| existe esa columna. Los que si son de un proceso se registran en el.
*/
Route::resource(
    'procesos/ordenes-trabajo/{ordenTrabajo}/costos',
    CostosOrdenController::class
)
    ->parameters([
        'costos' => 'costo',
    ])
    ->names('procesos.ordenes_trabajo.costos');


/*
| Los costos de un proceso: energia, agua, materia prima. Se registran
| desde la pantalla del proceso, igual que los equipos.
*/
Route::resource(
    'procesos/ordenes-trabajo/{ordenTrabajo}/procesos/{procesoOrden}/costos',
    CostosProcesoController::class
)
    ->parameters([
        'costos' => 'costo',
    ])
    ->names('procesos.ordenes_trabajo.procesos.costos');


/*
| La materia prima que consume un proceso: el cemento y los quimicos de
| la etapa de Pilas, o de cualquiera otra. Se registra desde la pantalla
| del proceso, no desde el almacen, porque la fila tiene que quedar
| imputada a la etapa que lo gasta: si se registrara sin proceso, el
| material saldria del stock sin cargarse a ninguna parte.
*/
Route::get(
    'procesos/ordenes-trabajo/{ordenTrabajo}/procesos/{procesoOrden}/materiales',
    [MaterialesProcesoController::class, 'index']
)->name('procesos.ordenes_trabajo.procesos.materiales.index');

Route::get(
    'procesos/ordenes-trabajo/{ordenTrabajo}/procesos/{procesoOrden}/materiales/create',
    [MaterialesProcesoController::class, 'create']
)->name('procesos.ordenes_trabajo.procesos.materiales.create');

Route::post(
    'procesos/ordenes-trabajo/{ordenTrabajo}/procesos/{procesoOrden}/materiales',
    [MaterialesProcesoController::class, 'store']
)->name('procesos.ordenes_trabajo.procesos.materiales.store');

Route::delete(
    'procesos/ordenes-trabajo/{ordenTrabajo}/procesos/{procesoOrden}/materiales/{movimiento}',
    [MaterialesProcesoController::class, 'destroy']
)->name('procesos.ordenes_trabajo.procesos.materiales.destroy');


}); // fin del grupo middleware('auth')

<?php

use App\Http\Controllers\ClienteController;
use App\Http\Controllers\Configuracion\CategoriaCostoController;
use App\Http\Controllers\Configuracion\MonedaController;
use App\Http\Controllers\Configuracion\PrecioOroController;
use App\Http\Controllers\Configuracion\TipoPagoEmpleadoController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\empleados\EmpleadoPagoController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\EtapaController;
use App\Http\Controllers\Inventario\CompraController;
use App\Http\Controllers\Inventario\MaterialesController;
use App\Http\Controllers\Inventario\ProductoController;
use App\Http\Controllers\Inventario\ProveedorController;
use App\Http\Controllers\Configuracion\TipoCambioController;
use App\Http\Controllers\Procesos\CostosOrdenController;
use App\Http\Controllers\Procesos\OrdenesTrabajo\CostosProcesoController;
use App\Http\Controllers\Procesos\OrdenesTrabajo\MaterialesProcesoController;
use App\Http\Controllers\Procesos\OrdenesTrabajo\ProcesoEquipoController;
use App\Http\Controllers\Procesos\OrdenesTrabajo\TrabajosEmpleadoController;
use App\Http\Controllers\Procesos\OrdenTrabajoController;
use App\Http\Controllers\Procesos\ProcesoOrdenController;
use App\Http\Controllers\Procesos\RecuperacionController;
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

/*
| El catalogo de proveedores para elegir uno desde un modal.
|
| Va antes que el resource y no por casualidad: el resource mete un
| {proveedor} que se tragaria el texto "selector" y dejaria el catalogo sin su
| pagina de mostrar. Con la ruta esta primero, el literal llega aqui y el
| resource no lo ve nunca.
|
| La url lleva una barra, y no un guion, por lo mismo: si fuera
| inventario/proveedores-selector no habria colision, pero entonces el patron
| seria distinto del resto de rutas del proyecto sin ninguna necesidad.
*/
Route::get(
    'inventario/proveedores/selector',
    [ProveedorController::class, 'selector']
)->name('inventario.proveedores.selector');

/*
| Los proveedores van antes que los materiales a proposito: comparten el
| camino de los modales y el mismo modelo, asi que leerlos uno tras otro
| ayuda. El orden en que se declaran no importa para que no se solapen: los
| prefijos son distintos.
*/
Route::resource(
    'inventario/proveedores',
    ProveedorController::class
)
    ->parameters([
        'proveedores' => 'proveedor',
    ])
    ->names('inventario.proveedores');


Route::resource(
    'inventario/productos',
    ProductoController::class
)
    ->parameters([
        'productos' => 'producto',
    ])
    ->names('inventario.productos');

/*
| Las compras van despues de los proveedores y de los materiales, que son
| los dos que las llenan. El orden no importa para que no se solapen los
| prefijos, pero leerlos en este orden ayuda: la compra es donde converge lo
| que se dio de alta antes.
*/
Route::resource(
    'inventario/compras',
    CompraController::class
)
    ->parameters([
        'compras' => 'compra',
    ])
    ->names('inventario.compras');

/*
| El tipo de cambio va despues de las compras, que es quien lo lee para
| passar los totales a cordoba. Sin el, una compra en dolares no tiene
| equivalente en NIO.
|
| La importacion va antes que el resource y con su propia ruta, y no como un
| metodo mas: es lo unico de aqui que no cabe en un formulario de una sola
| fila —son treinta dias de golpe— y por eso lleva su propia pantalla y su
| propio permiso. Si fuera un metodo del resource, el trait de permisos la
| colgaria de "create" sin que nadie lo decidiera, y una importacion que
| toca treinta dias no es lo mismo que dar de alta un dia.
*/
Route::post(
    'configuracion/tipos-cambio/importar',
    [TipoCambioController::class, 'importar']
)
    ->name('configuracion.tipos_cambio.importar');

/*
| La plantilla del ejemplo va con metodo GET porque es una descarga, y ponerla
| a mano es lo que evita que route() genere un nombre distinto del que espera
| el javascript.
|
| Comparte el permiso de la importacion, que es la razon por la que se pide:
| descargar el ejemplo es el primer paso de importar, no una accion aparte.
*/
Route::get(
    'configuracion/tipos-cambio/plantilla',
    [TipoCambioController::class, 'plantilla']
)
    ->name('configuracion.tipos_cambio.plantilla');

Route::resource(
    'configuracion/tipos-cambio',
    TipoCambioController::class
)
    ->parameters([
        'tipos-cambio' => 'tipoCambio',
    ])
    ->names('configuracion.tipos_cambio');

/*
| El catalogo de monedas.
|
| Sin parametros: la ruta es "configuracion/monedas" y el resource ya sabe que
| el singular es "moneda". Ponerlo a mano, como en el tipo de cambio, hace
| falta solo cuando la ruta lleva guion.
|
| Se declara despues del tipo de cambio y no por capricho: el resource genera
| siete rutas, y declararlas en este orden deja el tipo de cambio primero, que
| es el que se usa todos los dias. El catalogo de monedas se abre una vez al
| anadir algo nuevo y luego no se vuelve a abrir.
*/
Route::resource(
    'configuracion/monedas',
    MonedaController::class
    )
    ->names('configuracion.monedas');

/*
| El precio del oro.
|
| Las dos rutas sueltas —importar y plantilla— van antes del resource y con su
| propia ruta, y no como metodos mas, por el mismo motivo que las del tipo de
| cambio: la importacion no cabe en un formulario de una sola fila —son treinta
| dias de golpe— y por eso lleva su propia pantalla y su propio permiso. Si
| fuera un metodo del resource, el trait de permisos la colgaria de "create" sin
| que nadie lo decidiera.
*/
Route::post(
    'configuracion/precios-oro/importar',
    [PrecioOroController::class, 'importar']
)
    ->name('configuracion.precios_oro.importar');

Route::get(
    'configuracion/precios-oro/plantilla',
    [PrecioOroController::class, 'plantilla']
)
    ->name('configuracion.precios_oro.plantilla');

Route::resource(
    'configuracion/precios-oro',
    PrecioOroController::class
    )
    ->parameters([
        'precios-oro' => 'precioOro',
    ])
    ->names('configuracion.precios_oro');

/*
| Las recuperaciones de oro van en produccion y no colgando de la orden.
|
| Se podria hacer al reves —una pantalla dentro de la ficha de cada orden— y es
| lo que se hace con los trabajos de empleado, que cuelgan del proceso. Aqui
| no: una recuperacion no es un dato del taller, es un hecho economico. Los
| gramos de una orden se pueden mirar todos juntos, que es como se mira una
| produccion, y ademas una orden puede tener varias —una por partida de
| mineral— que en una pantalla global se ven todas y en una ficha habria que
| ir una por una.
|
| El prefijo de la url es procesos/ y no produccion/ aunque la pantalla salga
| en el menu de Produccion. Es el mismo acuerdo que hay en el resto: el menu
| se llama por el area, y el prefijo de la url por la parte del codigo que la
| atiende. Configuracion vive en configuracion/, Inventario en inventario/, y
| Produccion, que son las pantallas de los procesos, en procesos/.
|
| Lo que si comparte con las que cuelgan de la orden es la regla de la orden
| cerrada, y esa sale del trait OrdenCerrada, que ya la aplica en seis
| pantallas mas.
*/
Route::resource(
    'procesos/recuperaciones',
    RecuperacionController::class
    )
    ->parameters([
        'recuperaciones' => 'recuperacion',
    ])
    ->names('procesos.recuperaciones');


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

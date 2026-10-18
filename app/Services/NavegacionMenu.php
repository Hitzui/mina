<?php

namespace App\Services;

use App\Models\OrdenesTrabajo;
use App\Models\User;

class NavegacionMenu
{
    /** Destinos del menú y tareas habituales; solo se envían los autorizados. */
    public function opciones(?User $usuario, ?OrdenesTrabajo $orden = null): array
    {
        if (! $usuario) {
            return [];
        }

        $opciones = [];
        $agregar = function (string $ruta, string $titulo, string $grupo, ?string $permiso, string $icono, string $descripcion, string $palabras = '', bool $rapido = false) use (&$opciones, $usuario) {
            if ($permiso && ! $usuario->can($permiso)) {
                return;
            }
            $opciones[] = [
                'id' => str_replace('.', '-', $ruta), 'title' => $titulo, 'group' => $grupo,
                'url' => route($ruta), 'icon' => $icono, 'description' => $descripcion,
                'keywords' => $palabras, 'quick' => $rapido,
            ];
        };

        $agregar('home', 'Inicio / Dashboard', 'Inicio', null, 'bi-speedometer2', 'Indicadores, avance de órdenes y resumen del taller.', 'panel resumen inicio');
        $agregar('procesos.ordenes_trabajo.create', 'Nueva OT', 'Órdenes de trabajo', 'ordenes_trabajo.create', 'bi-plus-circle', 'Registrar un nuevo trabajo para un cliente.', 'crear orden trabajo servicio molienda nueva ot', true);
        $agregar('procesos.ordenes_trabajo.index', 'Órdenes de trabajo', 'Órdenes de trabajo', 'ordenes_trabajo.view', 'bi-clipboard2-check', 'Buscar una OT, revisar sus procesos y abrir su detalle.', 'ot orden trabajos procesos seguimiento costos', true);
        $agregar('procesos.cobros.create', 'Registrar cobro', 'Caja e ingresos', 'cobros.create', 'bi-cash-coin', 'Recibir un pago o abono y aplicarlo a ingresos del cliente.', 'cobrar pagar pago abonos recibo efectivo transferencia', true);
        $agregar('procesos.cobros.index', 'Cobros', 'Caja e ingresos', 'cobros.view', 'bi-receipt', 'Consultar pagos, abonos y cobros pendientes de confirmar.', 'cobrar cliente pago abono recibo saldo deuda', ! $usuario->can('cobros.create'));
        $agregar('procesos.ordenes_trabajo.calendario', 'Calendario de trabajo', 'Órdenes de trabajo', 'ordenes_trabajo.view', 'bi-calendar3', 'Ver las órdenes y procesos en la agenda.', 'agenda fechas programacion calendario', true);
        $agregar('inventario.compras.create', 'Nueva compra', 'Inventario', 'compras.create', 'bi-bag-plus', 'Registrar una compra y sus materiales.', 'comprar insumos proveedor factura abastecer', true);
        $agregar('inventario.movimientos.index', 'Almacén', 'Inventario', 'movimientos_inventario.view', 'bi-boxes', 'Consultar entradas, salidas y existencias de materiales.', 'almacen inventario stock existencias entrada salida combustible gasolina diesel quimicos insumos', true);
        $agregar('admin.clientes.index', 'Clientes', 'Administración', 'clientes.view', 'bi-person-vcard', 'Consultar y gestionar los clientes del taller.', 'cliente contacto telefono', true);
        $agregar('procesos.lamas.index', 'Lama y tratamientos', 'Órdenes de trabajo', 'ordenes_trabajo.view', 'bi-recycle', 'Ubicar lotes de lama y seguir su recuperación posterior.', 'lama lamas relaves sobrantes lotes deposito tratamiento recuperar oro', true);
        $agregar('admin.empleados.index', 'Empleados', 'Administración', 'empleados.view', 'bi-person-badge', 'Personal del taller y sus datos.', 'personal trabajadores empleado tarifas salarios');
        $agregar('admin.etapas.index', 'Etapas', 'Administración', 'etapas.view', 'bi-diagram-3', 'Configurar las etapas que se usan en los procesos.', 'etapa proceso molienda lavado');
        $agregar('admin.equipos.index', 'Equipos', 'Administración', 'equipos.view', 'bi-gear-wide-connected', 'Máquinas, depreciación, electricidad y recursos de almacén.', 'maquinaria molino bomba planta horas horometro combustible gasolina diesel energia tarifa');
        $agregar('inventario.proveedores.index', 'Proveedores', 'Inventario', 'proveedores.view', 'bi-truck', 'Consultar proveedores y sus contactos.', 'proveedor suministros compras');
        $agregar('inventario.productos.index', 'Materiales', 'Inventario', 'productos.view', 'bi-box-seam', 'Catálogo de materiales, insumos y existencias.', 'productos material quimicos combustible gasolina diesel stock');
        $agregar('inventario.compras.index', 'Compras', 'Inventario', 'compras.view', 'bi-bag', 'Consultar las compras registradas y su detalle.', 'facturas proveedor adquisicion');
        $agregar('procesos.producciones.index', 'Producciones', 'Producción', 'producciones.view', 'bi-box-seam', 'Registrar y consultar los productos obtenidos del trabajo.', 'produccion resultados productos obtenidos');
        $agregar('procesos.recuperaciones.index', 'Recuperaciones de oro', 'Producción', 'recuperaciones.view', 'bi-gem', 'Registrar los gramos de oro realmente recuperados.', 'oro recuperar gramos pureza recuperacion');
        $agregar('procesos.valoraciones_oro.index', 'Valoraciones de oro', 'Producción', 'valoraciones_oro.view', 'bi-calculator', 'Calcular el valor monetario de las recuperaciones.', 'valorar valor oro gramos pureza precio');
        $agregar('comercial.cotizaciones.index', 'Cotizaciones', 'Comercial', 'cotizaciones.view', 'bi-file-earmark-text', 'Preparar y consultar propuestas de servicio para clientes.', 'cotizar presupuesto oferta propuesta');
        $agregar('procesos.movimientos_caja.index', 'Movimientos de caja', 'Caja e ingresos', 'movimientos_caja.view', 'bi-arrow-left-right', 'Entradas, salidas y saldos por caja, moneda y método.', 'caja dinero saldo efectivo transferencias entrada salida');
        $agregar('procesos.cierres_caja.index', 'Cierres de caja', 'Caja e ingresos', 'cierres_caja.view', 'bi-safe2', 'Comparar el saldo esperado con el dinero contado.', 'cerrar caja arqueo conteo corte cierre');
        $agregar('reportes.index', 'Reportes', 'Reportes', 'reportes.view', 'bi-file-earmark-bar-graph', 'Consultar informes y descargar resultados según permisos.', 'informe reporte excel pdf estadisticas');

        foreach ([
            ['categorias_costos', 'Categorías de costos', 'bi-tags', 'Clasificar los gastos y costos.', 'gastos categorias costo'],
            ['tipos_pago_empleado', 'Tipos de pago de empleado', 'bi-cash-stack', 'Definir cómo se calcula el trabajo del personal.', 'salario jornada tarifa pago empleado'],
            ['tipos_ingreso', 'Tipos de ingreso', 'bi-cash-coin', 'Clasificar los conceptos de ingreso de las OT.', 'ingreso conceptos servicio'],
            ['tipos_produccion', 'Tipos de producción', 'bi-box-seam', 'Clasificar los productos obtenidos.', 'produccion producto tipos'],
            ['tipos_participacion', 'Tipos de participación', 'bi-people', 'Catálogo para clasificar acuerdos con los clientes.', 'participacion tipo acuerdo tarifa porcentaje'],
            ['unidades_medida', 'Unidades de medida', 'bi-rulers', 'Unidades de peso, cantidad y consumo.', 'unidad toneladas quintales gramos litros galones'],
            ['monedas', 'Monedas', 'bi-currency-exchange', 'Monedas disponibles y moneda base.', 'cordobas nio dolares usd divisas'],
            ['precios_oro', 'Precios del oro', 'bi-gem', 'Precios históricos por fecha para valorar el oro.', 'oro precio gramo cotizacion excel'],
            ['cajas', 'Cajas', 'bi-safe2', 'Configurar las cajas donde se registra el dinero.', 'caja asignar usuario caja activa'],
            ['metodos_pago', 'Métodos de pago', 'bi-credit-card-2-front', 'Efectivo, transferencias y otros medios de pago.', 'metodo forma pago efectivo banco transferencia'],
        ] as [$modulo, $titulo, $icono, $descripcion, $palabras]) {
            $agregar('configuracion.'.$modulo.'.index', $titulo, 'Configuración', 'configuracion.'.$modulo.'.view', $icono, $descripcion, $palabras);
        }
        $agregar('configuracion.tipos_cambio.index', 'Tipo de cambio', 'Configuración', 'tipos_cambio.view', 'bi-currency-exchange', 'Equivalencias históricas de monedas por fecha.', 'dolar cordoba conversion tasa divisa excel');

        // Estas tareas viven dentro de una OT, no en un módulo global separado.
        if ($usuario->can('ordenes_trabajo.view')) {
            foreach ([
                ['reparaciones', 'Reparaciones y plazos de equipos', 'proceso_equipo.view', 'bi-tools', 'Consultar progreso, intervalos de reparación, ampliar asignaciones y registrar costos vinculados.', 'reparacion reparaciones averia averias falla dañado mantenimiento progreso avance barra plazo ampliar', '#seguimiento-ot'],
                ['procesos', 'Procesos y recursos de una OT', 'procesos_orden.view', 'bi-diagram-3', 'Abra un proceso para registrar personal, equipos, horas y materiales.', 'proceso asignar equipo horas horometro personal empleados consumo consumir material entregar devolver insumos', '#procesos-orden'],
                ['acuerdos', 'Acuerdos de participación', 'acuerdos_orden.view', 'bi-file-earmark-text', 'Pactar porcentajes, tarifa del servicio y condiciones de lama.', 'acuerdo trato cliente participacion reparto tarifa servicio', '#acuerdos'],
                ['ingresos', 'Ingresos de una OT', 'ingresos.view', 'bi-cash-coin', 'Registrar el importe del servicio antes de aplicar los cobros.', 'ingreso servicio facturar tarifa monto', '#ingresos'],
                ['costos', 'Costos de una OT', 'movimientos_costo.view', 'bi-wallet2', 'Consultar y registrar gastos generales del trabajo.', 'costos gastos generales insumos recursos', '#costos-orden'],
                ['liquidaciones', 'Liquidaciones de oro', 'liquidaciones.view', 'bi-pie-chart', 'Repartir los gramos entre cliente y empresa según el acuerdo.', 'liquidacion reparto oro porcentajes cliente empresa', '#liquidaciones'],
            ] as [$id, $titulo, $permiso, $icono, $descripcion, $palabras, $ancla]) {
                if (! $usuario->can($permiso)) {
                    continue;
                }
                $opciones[] = [
                    'id' => 'tarea-ot-'.$id, 'title' => $titulo, 'group' => $orden ? $orden->codigo : 'Dentro de una OT',
                    'url' => $orden ? route('procesos.ordenes_trabajo.show', $orden).$ancla : route('procesos.ordenes_trabajo.index'),
                    'icon' => $icono, 'description' => $orden ? $descripcion : 'Primero abra una OT → '.$titulo.'. '.$descripcion,
                    'keywords' => $palabras, 'quick' => false,
                ];
            }
        }

        return $opciones;
    }

    public function actuales(): array
    {
        $orden = request()->route('ordenTrabajo');
        // Algunas pantallas reciben el ID sin enlace implícito de modelo.
        if ((is_string($orden) || is_int($orden)) && ctype_digit((string) $orden)
            && auth()->user()?->can('ordenes_trabajo.view')) {
            $orden = OrdenesTrabajo::find($orden);
        }

        return $this->opciones(auth()->user(), $orden instanceof OrdenesTrabajo ? $orden : null);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesYPermisosSeeder extends Seeder
{
    /**
     * Módulos de la aplicación y acciones protegidas.
     *
     * La clave es el prefijo del permiso; los controladores aplican
     * `<prefijo>.view`, `.create`, `.edit` y `.delete` según el método.
     */
    private const MODULOS = [
        'clientes',
        'empleados',
        'empleados.pagos',
        'equipos',
        'etapas',
        'ordenes_trabajo',
        'procesos_orden',
        'proceso_equipo',
        'recuperaciones',
        'valoraciones_oro',
        'movimientos_costo',
        'movimientos_inventario',
        'productos',
        'proveedores',
        'compras',
        'trabajos_empleado',
        'ingresos',
        'tipos_cambio',
        'configuracion.monedas',
        'configuracion.precios_oro',
        'configuracion.categorias_costos',
        'configuracion.tipos_pago_empleado',
        'configuracion.tipos_ingreso',
        'usuarios',
    ];

    private const ACCIONES = ['view', 'create', 'edit', 'delete'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /*
         * 1. Permisos
         */
        $todos = [];

        foreach (self::MODULOS as $modulo) {
            foreach (self::ACCIONES as $accion) {
                $nombre = "{$modulo}.{$accion}";

                $permiso = Permission::firstOrCreate(
                    ['name' => $nombre, 'guard_name' => 'web']
                );

                $todos[$nombre] = $permiso;
            }
        }

        $this->command?->info('  Permisos: ' . count($todos));

        /*
         * 2. Rol administrador: acceso total
         */
        $admin = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web']
        );

        $admin->syncPermissions(array_values($todos));

        /*
         * 3. Rol supervisor: opera el taller, no borra registros
         *    maestros ni toca configuración ni usuarios.
         */
        $supervisor = Role::firstOrCreate(
            ['name' => 'supervisor', 'guard_name' => 'web']
        );

        /*
         * El catalogo de monedas no lo lleva el supervisor, ni siquiera para
         * mirar. No es una lista de consulta como las de clientes o
         * empleados: es el dato que decide en que moneda esta el taller y con
         * que se convierte todo. Cambiar la moneda base es de las pocas cosas
         * que alteran el valor de documentos ya escritos, asi que queda en
         * manos de administracion y de nadie mas.
         *
         * Lo mismo con el precio del oro, y por el mismo motivo de fondo: es
         * la serie con la que se valora lo que sale del taller, asi que un
         * precio mal puesto a mano cambia el valor de las liquidaciones sin
         * que nada avise de nada. Se mira, que es lo que hace falta para
         * saber a cuanto se esta vendiendo el oro; se escribe, no.
         */
        $supervisor->syncPermissions([
            'clientes.view', 'clientes.create', 'clientes.edit',
            'empleados.view', 'empleados.create', 'empleados.edit',
            'empleados.pagos.view', 'empleados.pagos.create', 'empleados.pagos.edit',
            'equipos.view', 'equipos.create', 'equipos.edit',
            'etapas.view',
            'ordenes_trabajo.view', 'ordenes_trabajo.create', 'ordenes_trabajo.edit',
            'procesos_orden.view', 'procesos_orden.create', 'procesos_orden.edit',
            'proceso_equipo.view', 'proceso_equipo.create', 'proceso_equipo.edit',
            'movimientos_costo.view', 'movimientos_costo.create', 'movimientos_costo.edit',
            /*
             * Los ingresos van con el supervisor, y por el mismo motivo que los
             * costos: son la otra cara de la misma orden. Quien opera el taller
             * es quien sabe que se le cobro al cliente y cuanto, asi que cargar
             * el ingreso es suyo. Sin borrar, como los costos: borrar un ingreso
             * es borrar un documento que se le emitio a alguien, y esa decision
             * es de administracion.
             *
             * Y NO LLEVA EL CATALOGO DE TIPOS DE INGRESO, que va mas abajo con
             * las monedas y el precio del oro: el catalogo reparte el dinero en
             * categorias y cambiarlo despues ya no reordena lo que esta escrito.
             */
            'ingresos.view', 'ingresos.create', 'ingresos.edit',
            // El almacen lo lleva administracion: el material entra por ahi y
            // el catalogo es un dato maestro, no trabajo de campo
            'movimientos_inventario.view', 'movimientos_inventario.create',
            'movimientos_inventario.edit', 'movimientos_inventario.delete',
            'productos.view', 'productos.create', 'productos.edit', 'productos.delete',
            'proveedores.view', 'proveedores.create',
            'proveedores.edit', 'proveedores.delete',
            'compras.view', 'compras.create', 'compras.edit', 'compras.delete',
            'trabajos_empleado.view', 'trabajos_empleado.create', 'trabajos_empleado.edit',
            'tipos_cambio.view', 'tipos_cambio.create', 'tipos_cambio.edit',
            'recuperaciones.view', 'recuperaciones.create', 'recuperaciones.edit',
            /*
             * El catalogo de tipos de ingreso NO lo lleva el supervisor, por el
             * mismo motivo que las monedas y el precio del oro: es el dato
             * maestro que decide en que categorias se reparte el dinero de
             * cada orden, y cambiarlo altera como se reparten documentos ya
             * escritos. Se mira, que es lo que hace falta para saber por que
             * una orden dio lo que dio; se escribe, no.
             *
             * Y no lleva ni el de tipos de pago de empleado ni el de categorias
             * de costo tampoco, que ya estaban asi. Los tres son la misma
             * clase de dato y lo raro seria que uno de ellos lo pudiera tocar
             * el supervisor y los otros dos no.
             */
            'configuracion.tipos_ingreso.view',
            /*
             * Valorar el oro va con el supervisor, como las recuperaciones y
             * a diferencia de cargar el precio.
             *
             * La distincion esta en quien decide el numero. El precio lo pone
             * el banco y por eso va con administracion: cargarlo a mano cambia
             * lo que vale el oro. La valoracion no: sale de la cuenta, con el
             * precio que ya hay y los gramos de la recuperacion. Es trabajo de
             * taller —"esto salio el dia 12 y por lo tanto vale esto"— y quien
             * esta en el taller es quien lo sabe hacer.
             */
            'valoraciones_oro.view', 'valoraciones_oro.create', 'valoraciones_oro.edit',
            'configuracion.monedas.view',
            'configuracion.precios_oro.view',
            'configuracion.categorias_costos.view',
            'configuracion.tipos_pago_empleado.view',
        ]);

        /*
         * 4. Rol operador: registra trabajo en campo, solo lectura del
         *    resto. Es el rol para el personal de taller.
         */
        $operador = Role::firstOrCreate(
            ['name' => 'operador', 'guard_name' => 'web']
        );

        $operador->syncPermissions([
            'clientes.view',
            'empleados.view',
            // Solo consulta el equipo: el maestro lo lleva administracion
            'equipos.view',
            'ordenes_trabajo.view',
            'procesos_orden.view', 'procesos_orden.create', 'procesos_orden.edit',
            // Registrar el uso de un equipo en el proceso es trabajo de
            // campo, igual que registrar el trabajo de un empleado
            'proceso_equipo.view', 'proceso_equipo.create', 'proceso_equipo.edit',
            // Y cargar los consumos del proceso (energia, agua, material)
            'movimientos_costo.view', 'movimientos_costo.create', 'movimientos_costo.edit',
            /*
             * El consumo de material en un proceso tambien es trabajo de
             * campo: es quien esta en la etapa de Pilas el que sabe cuanto
             * cemento se gasto. Lo que no lleva el operador es el catalogo
             * de materiales ni el kardex general, que son dato maestro y
             * control de almacen.
             */
            'movimientos_inventario.view', 'movimientos_inventario.create',
            'productos.view',
            'trabajos_empleado.view', 'trabajos_empleado.create', 'trabajos_empleado.edit',
            /*
             * El operador VE los ingresos de la orden y no los toca. Solo ver,
             * porque la tabla esta en la ficha de la orden, que el operador ya
             * puede abrir: sin este permiso la veria rota —la peticion por ajax
             * sale con un 403 y lo que se ve es una tabla vacia sin explicación
             *—, y eso es peor que no enseñarle nada.
             *
             * Verlos es ademas lo lógico: es el mismo trabajo del que esta
             * llevando. Cargar lo que entra, no: eso lo hace quien sabe que se
             * pactó con el cliente, que no es el que esta en la etapa de Pilas.
             */
            'ingresos.view',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('  Roles: ' . Role::count());
    }
}

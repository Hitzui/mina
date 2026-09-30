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
        'movimientos_costo',
        'movimientos_inventario',
        'productos',
        'proveedores',
        'compras',
        'trabajos_empleado',
        'tipos_cambio',
        'configuracion.monedas',
        'configuracion.precios_oro',
        'configuracion.categorias_costos',
        'configuracion.tipos_pago_empleado',
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
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('  Roles: ' . Role::count());
    }
}

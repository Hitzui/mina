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
        'etapas',
        'ordenes_trabajo',
        'procesos_orden',
        'trabajos_empleado',
        'tipos_cambio',
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

        $supervisor->syncPermissions([
            'clientes.view', 'clientes.create', 'clientes.edit',
            'empleados.view', 'empleados.create', 'empleados.edit',
            'empleados.pagos.view', 'empleados.pagos.create', 'empleados.pagos.edit',
            'etapas.view',
            'ordenes_trabajo.view', 'ordenes_trabajo.create', 'ordenes_trabajo.edit',
            'procesos_orden.view', 'procesos_orden.create', 'procesos_orden.edit',
            'trabajos_empleado.view', 'trabajos_empleado.create', 'trabajos_empleado.edit',
            'tipos_cambio.view', 'tipos_cambio.create', 'tipos_cambio.edit',
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
            'ordenes_trabajo.view',
            'procesos_orden.view', 'procesos_orden.create', 'procesos_orden.edit',
            'trabajos_empleado.view', 'trabajos_empleado.create', 'trabajos_empleado.edit',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('  Roles: ' . Role::count());
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISOS = [
        'configuracion.tipos_produccion.view',
        'configuracion.tipos_produccion.create',
        'configuracion.tipos_produccion.edit',
        'configuracion.tipos_produccion.delete',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = collect(self::PERMISOS)
            ->map(fn (string $nombre) => Permission::firstOrCreate([
                'name' => $nombre,
                'guard_name' => 'web',
            ]));

        $admin = Role::query()
            ->where('name', 'admin')
            ->where('guard_name', 'web')
            ->first();

        if ($admin) {
            $admin->givePermissionTo($permisos);
        }

        $supervisor = Role::query()
            ->where('name', 'supervisor')
            ->where('guard_name', 'web')
            ->first();

        if ($supervisor) {
            $supervisor->givePermissionTo($permisos->firstWhere('name', self::PERMISOS[0]));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()
            ->whereIn('name', self::PERMISOS)
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

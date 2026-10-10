<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISOS = [
        'cotizaciones.view',
        'cotizaciones.create',
        'cotizaciones.edit',
        'cotizaciones.delete',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permisos = collect(self::PERMISOS)->map(fn (string $nombre) => Permission::firstOrCreate([
            'name' => $nombre,
            'guard_name' => 'web',
        ]));

        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo($permisos);
        }

        $supervisor = Role::where('name', 'supervisor')->where('guard_name', 'web')->first();
        if ($supervisor) {
            $supervisor->givePermissionTo($permisos->reject(
                fn (Permission $permiso) => $permiso->name === 'cotizaciones.delete'
            ));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::whereIn('name', self::PERMISOS)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

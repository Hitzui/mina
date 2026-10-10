<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISOS = [
        'producciones.view',
        'producciones.create',
        'producciones.edit',
        'producciones.delete',
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
            $supervisor->givePermissionTo($permisos->take(3));
        }

        $operador = Role::query()
            ->where('name', 'operador')
            ->where('guard_name', 'web')
            ->first();
        if ($operador) {
            $operador->givePermissionTo($permisos->take(3));
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

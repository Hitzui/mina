<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISOS = [
        'liquidaciones.view',
        'liquidaciones.create',
        'liquidaciones.edit',
        'liquidaciones.delete',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permisos = collect(self::PERMISOS)->map(fn (string $nombre) => Permission::firstOrCreate([
            'name' => $nombre,
            'guard_name' => 'web',
        ]));

        $admin = Role::query()->where('name', 'admin')->where('guard_name', 'web')->first();
        $admin?->givePermissionTo($permisos);

        $supervisor = Role::query()->where('name', 'supervisor')->where('guard_name', 'web')->first();
        $supervisor?->givePermissionTo($permisos->filter(fn (Permission $permission) => in_array(
            $permission->name,
            ['liquidaciones.view', 'liquidaciones.create', 'liquidaciones.edit'],
            true
        ))->values());

        $operador = Role::query()->where('name', 'operador')->where('guard_name', 'web')->first();
        $operador?->givePermissionTo($permisos->firstWhere('name', 'liquidaciones.view'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->whereIn('name', self::PERMISOS)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

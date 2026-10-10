<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISOS = [
        'acuerdos_orden.view',
        'acuerdos_orden.create',
        'acuerdos_orden.edit',
        'acuerdos_orden.delete',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permisos = collect(self::PERMISOS)->map(fn (string $nombre) => Permission::firstOrCreate([
            'name' => $nombre,
            'guard_name' => 'web',
        ]));

        $administrador = Role::query()->where('name', 'admin')->where('guard_name', 'web')->first();
        $administrador?->givePermissionTo($permisos);

        $supervisor = Role::query()->where('name', 'supervisor')->where('guard_name', 'web')->first();
        $supervisor?->givePermissionTo($permisos->filter(fn (Permission $permiso) => in_array(
            $permiso->name,
            ['acuerdos_orden.view', 'acuerdos_orden.create', 'acuerdos_orden.edit'],
            true
        ))->values());

        $operador = Role::query()->where('name', 'operador')->where('guard_name', 'web')->first();
        $operador?->givePermissionTo($permisos->firstWhere('name', 'acuerdos_orden.view'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->whereIn('name', self::PERMISOS)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

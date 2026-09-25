<?php

namespace App\Http\Controllers\Concerns;

/**
 * Aplica los permisos de un modulo a los metodos del controlador.
 *
 * Se usa desde el constructor del controlador:
 *
 *     public function __construct()
 *     {
 *         $this->authorizeModule('clientes');
 *     }
 *
 * Los nombres de permiso se generan como `<modulo>.<accion>` y se
 * crean en database/seeders/RolesYPermisosSeeder.php
 */
trait AuthorizesModule
{
    protected function authorizeModule(string $prefix): void
    {
        $this->middleware("permission:{$prefix}.view")
            ->only(['index', 'show']);

        $this->middleware("permission:{$prefix}.create")
            ->only(['create', 'store']);

        $this->middleware("permission:{$prefix}.edit")
            ->only(['edit', 'update']);

        $this->middleware("permission:{$prefix}.delete")
            ->only(['destroy']);
    }
}

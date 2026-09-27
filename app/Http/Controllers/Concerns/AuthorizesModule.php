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
    /**
     * Los metodos que se cuentan como "ver" el modulo.
     *
     * Por defecto son los dos de siempre. Un modulo con pantallas extra
     * (un calendario, una vista de agenda) las pasa aqui, porque si no se
     * quedan sin permiso: el trait solo mira una lista, y un metodo que
     * no esta en ella no pide nada. Es un agujero facil de abrir, porque
     * el metodo se escribe y funciona, y el permiso simplemente no se
     * comprueba.
     *
     * @param  array<int, string>  $ver
     */
    protected function authorizeModule(string $prefix, array $ver = ['index', 'show']): void
    {
        $this->middleware("permission:{$prefix}.view")
            ->only($ver);

        $this->middleware("permission:{$prefix}.create")
            ->only(['create', 'store']);

        $this->middleware("permission:{$prefix}.edit")
            ->only(['edit', 'update']);

        $this->middleware("permission:{$prefix}.delete")
            ->only(['destroy']);
    }
}

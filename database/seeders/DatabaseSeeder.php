<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        /*
         * Roles y permisos del control de acceso.
         * NO crea usuarios: el primer usuario administrador lo crea
         * el responsable del sistema (ver README de acceso).
         */
        $this->call(RolesYPermisosSeeder::class);
    }
}

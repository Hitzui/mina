<?php

namespace App\Console\Commands;

use App\Models\Cajas;
use App\Models\User;
use Illuminate\Console\Command;

class AsignarCajaAUsuarioCommand extends Command
{
    protected $signature = 'mina:asignar-caja {usuario : Correo o ID del usuario} {caja_id : ID de una caja activa}';

    protected $description = 'Asigna una caja activa a un usuario';

    public function handle(): int
    {
        $identificador = (string) $this->argument('usuario');
        $usuario = User::query()
            ->where('email', $identificador)
            ->when(ctype_digit($identificador), fn($query) => $query->orWhereKey((int) $identificador))
            ->first();

        if (! $usuario) {
            $this->error('No se encontró el usuario indicado.');

            return self::FAILURE;
        }

        $caja = Cajas::paraRegistrar()->find((int) $this->argument('caja_id'));

        if (! $caja) {
            $this->error('La caja indicada no existe o está desactivada.');

            return self::FAILURE;
        }

        $usuario->forceFill(['caja_id' => $caja->id])->save();

        $this->info("Caja '{$caja->nombre}' asignada al usuario '{$usuario->name}'.");

        return self::SUCCESS;
    }
}

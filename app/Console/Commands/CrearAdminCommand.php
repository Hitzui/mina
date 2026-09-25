<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\PermissionRegistrar;

class CrearAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mina:crear-admin
                            {--name= : Nombre del administrador}
                            {--email= : Correo electronico}
                            {--password= : Contrasena (si se omite se genera una segura)}
                            {--reset : Cambiar la contrasena del administrador existente}
                            {--force : Crear un segundo usuario aunque ya exista uno}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea el usuario administrador del sistema (solo uno)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $existente = User::query()->first();

        if ($existente && ! $this->option('reset') && ! $this->option('force')) {
            $this->error('Ya existe un usuario en el sistema:');
            $this->line('  ' . $existente->email);
            $this->newLine();
            $this->comment('Para cambiarle la contrasena usa:');
            $this->line('  php artisan mina:crear-admin --reset');
            $this->comment('Para crear un usuario adicional sin tocar el anterior:');
            $this->line('  php artisan mina:crear-admin --force --email=otro@correo.com');
            return self::FAILURE;
        }

        $password = $this->option('password') ?: $this->generarPassword();

        $campos = [
            'name' => $this->option('name') ?: ($existente->name ?? 'Administrador'),
            'email' => $this->option('email') ?: ($existente->email ?? 'admin@mina.local'),
            'password' => $password,
        ];

        $validador = Validator::make($campos, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', Password::min(10)],
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if ($existente) {
            $objetivo = $existente;
            $accion = $this->option('force') ? 'actualizado' : 'contrasena actualizada';
        } else {
            $objetivo = new User();
            $accion = 'creado';
        }

        $objetivo->name = $campos['name'];
        $objetivo->email = $campos['email'];
        $objetivo->password = Hash::make($password);
        $objetivo->save();

        // El registro publico esta desactivado, asi que el rol se asigna aqui.
        $objetivo->syncRoles(['admin']);

        $this->info("Usuario {$accion} correctamente.");
        $this->newLine();
        $this->line('  Correo     : ' . $objetivo->email);
        $this->line('  Contrasena : ' . $password);
        $this->line('  Rol        : admin (' . $objetivo->getRoleNames()->implode(', ') . ')');
        $this->newLine();

        if (! $this->option('password')) {
            $this->warn('Anota la contrasena: no se volvera a mostrar.');
        }

        $this->comment('Entra en /login con esas credenciales.');

        return self::SUCCESS;
    }

    /**
     * Generar una contrasena aleatoria segura.
     */
    private function generarPassword(): string
    {
        return Str::password(16);
    }
}

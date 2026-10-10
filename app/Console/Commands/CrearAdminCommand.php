<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Cajas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
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
                            {--caja-id= : ID de la caja activa asignada al usuario}
                            {--reset : Cambiar la contrasena del administrador existente}
                            {--force : Crear un segundo usuario aunque ya exista uno}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea o actualiza un usuario administrador asignado a una caja';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($this->option('reset') && $this->option('force')) {
            $this->error('Use --reset para modificar una cuenta existente o --force para crear otra.');

            return self::FAILURE;
        }

        $existente = null;
        if ($this->option('reset')) {
            $existente = $this->option('email')
                ? User::query()->where('email', $this->option('email'))->first()
                : User::query()->first();

            if (! $existente) {
                $this->error('No se encontró la cuenta que indicó para actualizar.');

                return self::FAILURE;
            }
        } elseif ($this->option('force')) {
            if (! $this->option('email')) {
                $this->error('Indique --email para crear el usuario adicional.');

                return self::FAILURE;
            }
        } elseif (User::query()->exists()) {
            $existente = User::query()->first();
            $this->error('Ya existe un usuario en el sistema:');
            $this->line('  ' . $existente->email);
            $this->newLine();
            $this->comment('Para cambiarle la contrasena usa:');
            $this->line('  php artisan mina:crear-admin --reset');
            $this->comment('Para crear un usuario adicional sin tocar el anterior:');
            $this->line('  php artisan mina:crear-admin --force --email=otro@correo.com --caja-id=ID');
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
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($existente?->id)],
            'password' => ['required', 'string', Password::min(10)],
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $caja = null;
        if ($this->option('caja-id')) {
            $caja = Cajas::paraRegistrar()->find((int) $this->option('caja-id'));

            if (! $caja) {
                $this->error('La caja indicada no existe o está desactivada.');

                return self::FAILURE;
            }
        } elseif (! $existente) {
            $this->error('Al crear un usuario, indique --caja-id con el ID de una caja activa.');

            return self::FAILURE;
        }

        if ($existente) {
            $objetivo = $existente;
            $accion = 'actualizado';
        } else {
            $objetivo = new User();
            $accion = 'creado';
        }

        $objetivo->name = $campos['name'];
        $objetivo->email = $campos['email'];
        $objetivo->password = Hash::make($password);
        if ($caja) {
            $objetivo->caja_id = $caja->id;
        }
        $objetivo->save();

        // El registro publico esta desactivado, asi que el rol se asigna aqui.
        $objetivo->syncRoles(['admin']);

        $this->info("Usuario {$accion} correctamente.");
        $this->newLine();
        $this->line('  Correo     : ' . $objetivo->email);
        $this->line('  Contrasena : ' . $password);
        $this->line('  Rol        : admin (' . $objetivo->getRoleNames()->implode(', ') . ')');
        $this->line('  Caja       : ' . ($objetivo->caja?->nombre ?? 'sin asignar'));
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

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

final class CreateAccessUser extends Command
{
    protected $signature = 'access:create-user';

    protected $description = 'Crear una cuenta de acceso al ERP';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Nombre completo'));

        $email = mb_strtolower(
            trim((string) $this->ask('Correo electrónico'))
        );

        $password = (string) $this->secret('Contraseña (mínimo 12 caracteres)');

        $confirmation = (string) $this->secret('Repite la contraseña');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'max:72', 'confirmed'],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'email.email' => 'El correo no es válido.',
            'email.unique' => 'Ese correo ya está registrado.',
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = new User();
        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->save();

        $this->info('Cuenta creada correctamente.');

        return self::SUCCESS;
    }
}

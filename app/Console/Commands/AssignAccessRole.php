<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

final class AssignAccessRole extends Command
{
    protected $signature = 'access:assign-role';

    protected $description = 'Asignar un rol existente a una cuenta del ERP';

    public function handle(): int
    {
        $email = mb_strtolower(
            trim((string) $this->ask('Correo de la cuenta'))
        );

        $user = User::query()
            ->where('email', $email)
            ->first();

        if ($user === null) {
            $this->error('No existe una cuenta con ese correo.');

            return self::FAILURE;
        }

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        if ($roles === []) {
            $this->error('Primero ejecuta AccessRolesSeeder.');

            return self::FAILURE;
        }

        $roleName = $this->choice('Selecciona el rol', $roles);

        $user->assignRole($roleName);

        $this->info(
            "Rol {$roleName} asignado a {$user->email}."
        );

        return self::SUCCESS;
    }
}

<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('users:make-admin {email}', function () {
    $user = User::where('email', $this->argument('email'))->first();
    if (! $user) {
        $this->error('No existe una cuenta con ese correo.');

        return 1;
    }
    DB::transaction(function () use ($user) {
        $admin = Role::where('name', Role::ADMINISTRATOR)->lockForUpdate()->firstOrFail();
        $user->role()->associate($admin);
        $user->save();
    });
    $this->info('Rol Administrador asignado a '.$user->email);

    return 0;
})->purpose('Asignar un administrador existente desde la consola del propietario');

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| Este archivo se carga desde bootstrap/app.php mediante withRouting().
| El antiguo App\Console\Kernel ya no existe: el horario de tareas
| programadas vive ahora en bootstrap/app.php con withSchedule().
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

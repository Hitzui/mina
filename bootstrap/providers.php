<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\FortifyServiceProvider;

return [

    AppServiceProvider::class,
    AuthServiceProvider::class,
    EventServiceProvider::class,
    FortifyServiceProvider::class,

    /*
    | App\Providers\BroadcastServiceProvider::class,
    |
    | El driver de broadcast actual es 'log' (ver config/broadcasting.php),
    | por lo que no hay canales que registrar. Si en el futuro usas
    | pusher/reverb, descomenta el provider y anade en bootstrap/app.php:
    |
    |     ->withBroadcasting(
    |         __DIR__.'/../routes/channels.php',
    |         attributes: 'guards',
    |     )
    */

];

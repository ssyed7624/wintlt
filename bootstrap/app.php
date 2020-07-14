<?php

require_once __DIR__.'/../vendor/autoload.php';

(new Laravel\Lumen\Bootstrap\LoadEnvironmentVariables(
    dirname(__DIR__)
))->bootstrap();

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| Here we will load the environment and create the application instance
| that serves as the central piece of this framework. We'll use this
| application as an "IoC" container and router for this framework.
|
*/

$app = new Laravel\Lumen\Application(
    dirname(__DIR__)
);

// Enable Facades
$app->withFacades();

// Enable Eloquent
$app->withEloquent();

/*
|--------------------------------------------------------------------------
| Register Container Bindings
|--------------------------------------------------------------------------
|
| Now we will register a few bindings in the service container. We will
| register the exception handler and the console kernel. You may add
| your own bindings here if you like or you can make another file.
|
*/

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->configure('app');
$app->configure('auth');
$app->configure('cors');
$app->configure('database');
$app->configure('portal');
$app->configure('common');
$app->configure('tables');
$app->configure('flight');
$app->configure('hotels');
$app->configure('criterias');
$app->configure('mail');
$app->configure('filesystems');
$app->alias('mailer', Illuminate\Mail\Mailer::class);
$app->alias('mailer', Illuminate\Contracts\Mail\Mailer::class);
$app->alias('mailer', Illuminate\Contracts\Mail\MailQueue::class);

/*
|--------------------------------------------------------------------------
| Register Middleware
|--------------------------------------------------------------------------
|
| Next, we will register the middleware with the application. These can
| be global middleware that run before and after each request into a
| route or middleware that'll be assigned to some specific routes.
|
*/

$app->middleware([
    \Fruitcake\Cors\HandleCors::class,
    \App\Http\Middleware\UserAcl::class,
]);

// Enable auth middleware (shipped with Lumen)
$app->routeMiddleware([
    'cors'              => Fruitcake\Cors\HandleCors::class,
    'auth'              => App\Http\Middleware\Authenticate::class,
    'allowedips'        => App\Http\Middleware\AllowedIPs::class,
    'ticketplugin'      => App\Http\Middleware\TicketPlugin::class,
    'userAcl'           => App\Http\Middleware\UserAcl::class,
    'routeConfigAuth'   => App\Http\Middleware\RouteConfigAuth::class,
]);

/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
|
| Here we will register all of the application's service providers which
| are used to bind services into the container. Service providers are
| totally optional, so you are not required to uncomment this line.
|
*/

$app->register(App\Providers\AppServiceProvider::class);
$app->register(App\Providers\AuthServiceProvider::class);
$app->register(App\Providers\EventServiceProvider::class);


// Finally register two service providers - original one and Lumen adapter
$app->register(Flipbox\LumenGenerator\LumenGeneratorServiceProvider::class);
$app->register(Laravel\Passport\PassportServiceProvider::class);
$app->register(Dusterio\LumenPassport\PassportServiceProvider::class);

\Dusterio\LumenPassport\LumenPassport::routes($app);
// \Dusterio\LumenPassport\LumenPassport::routes($app, ['prefix' => 'v1/oauth']);

$app->register(\Fruitcake\Cors\CorsServiceProvider::class);
$app->register(Dingo\Api\Provider\LumenServiceProvider::class);

$app->register(\Illuminate\Mail\MailServiceProvider::class);
$app->register(\Illuminate\Redis\RedisServiceProvider::class);
$app->register(\Barryvdh\DomPDF\ServiceProvider::class);
$app->register(App\Providers\MinIOStorageServiceProvider::class);
/*
|--------------------------------------------------------------------------
| Load The Application Routes
|--------------------------------------------------------------------------
|
| Next we will include the routes file so that they can all be added to
| the application. This will provide all of the URLs the application
| can respond to, as well as the controllers that may handle them.
|
*/

$app->router->group([
    'namespace' => 'App\Http\Controllers',
], function ($router) {
    require __DIR__.'/../routes/web.php';
});

return $app;

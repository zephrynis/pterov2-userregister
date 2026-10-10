<?php

declare(strict_types=1);

// Development-only router. This file is excluded from runtime archives.
// Run a temporary COPY of the package with php -S <private-address>:8099 -t /app/public.
// All state and fake mail stay beside this router, never in the hosted Panel stores.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/assets/') || str_starts_with($path, '/favicons/')) {
    return false;
}
if (! file_exists(__DIR__.'/fixture.sqlite')) touch(__DIR__.'/fixture.sqlite');
define('USERREGISTER_FIXTURE_DATABASE', __DIR__.'/fixture.sqlite');
$app = require __DIR__.'/bootstrap.php';
$app['env'] = 'production';
@mkdir(__DIR__.'/sessions');
@mkdir(__DIR__.'/cache');
$app['config']->set([
    'session.driver' => 'file', 'session.files' => __DIR__.'/sessions',
    'cache.stores.fixture' => ['driver' => 'file', 'path' => __DIR__.'/cache'],
    'extensions.enabled' => false,
]);
$app['session']->forgetDrivers();
$app->instance(Illuminate\Cache\RateLimiter::class, new Illuminate\Cache\RateLimiter($app['cache']->store('fixture')));
Illuminate\Support\Facades\RateLimiter::clearResolvedInstance(Illuminate\Cache\RateLimiter::class);
Illuminate\Support\Facades\URL::forceRootUrl('http://'.$_SERVER['HTTP_HOST']);

Illuminate\Support\Facades\Route::middleware('web')->prefix('extensions/userregister')
    ->group(__DIR__.'/../routes/web.php');

if ($path === '/__fixture') {
    header('Content-Type: application/json');
    echo json_encode([
        'mail' => file_exists(__DIR__.'/mail.json') ? json_decode(file_get_contents(__DIR__.'/mail.json'), true) : null,
        'users' => Pterodactyl\Models\User::count(),
        'admins' => Pterodactyl\Models\User::where('root_admin', true)->count(),
        'pending' => Illuminate\Support\Facades\DB::table(UserRegister\Services\Registration::TABLE)->count(),
    ]);
    return;
}
if ($path === '/__fixture/close') {
    (new Pterodactyl\Services\Extensions\ExtensionSettings('userregister'))->set('registration_open', false);
    echo 'closed';
    return;
}
if ($path === '/__fixture/verification') {
    (new Pterodactyl\Services\Extensions\ExtensionSettings('userregister'))->set('require_email_verification', ($_GET['required'] ?? '1') !== '0');
    echo 'updated';
    return;
}

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
$mail = Illuminate\Support\Facades\Notification::sent(new Illuminate\Notifications\AnonymousNotifiable, UserRegister\Notifications\VerifyEmail::class)->last();
if ($mail) file_put_contents(__DIR__.'/mail.json', json_encode(['url' => $mail->toMail()->actionUrl]));
$response->send();
$kernel->terminate($request, $response);

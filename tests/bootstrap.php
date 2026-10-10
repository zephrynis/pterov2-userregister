<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Pterodactyl\Services\Extensions\ExtensionSettings;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'UserRegister\\')) {
        require __DIR__.'/../src/'.str_replace('\\', '/', substr($class, strlen('UserRegister\\'))).'.php';
    }
});

// Never use the hosted database, cache, sessions, mail transport, or queue.
$database = defined('USERREGISTER_FIXTURE_DATABASE') ? USERREGISTER_FIXTURE_DATABASE : ':memory:';
$app['config']->set([
    'database.connections.userregister_test' => ['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => true],
    'database.default' => 'userregister_test',
    'cache.default' => 'array', 'cache.limiter' => 'array', 'queue.default' => 'sync',
    'mail.default' => 'array', 'session.driver' => 'array', 'session.secure' => false,
    'session.cookie' => 'userregister_fixture', 'app.debug' => false,
    'view.compiled' => __DIR__.'/compiled', 'logging.default' => 'stderr',
    'app.url' => 'http://localhost',
]);
DB::setDefaultConnection('userregister_test');
app('cache')->forgetDriver();
// The Panel resolves its limiter during boot. Replace that singleton explicitly;
// changing cache.default alone would leave it connected to the hosted cache.
app()->instance(Illuminate\Cache\RateLimiter::class, new Illuminate\Cache\RateLimiter(app('cache')->store('array')));
RateLimiter::clearResolvedInstance(Illuminate\Cache\RateLimiter::class);
app('session')->forgetDrivers();
Notification::fake();
@mkdir(__DIR__.'/compiled');
app('view')->addNamespace('ext-userregister', __DIR__.'/../resources/views');

if (! Schema::hasTable('users')) {
    Schema::create('users', function (Blueprint $t): void {
        $t->increments('id');
        $t->string('uuid')->unique();
        $t->string('external_id')->nullable();
        $t->string('username')->unique();
        $t->string('email')->unique();
        $t->string('password');
        $t->string('name_first')->nullable();
        $t->string('name_last')->nullable();
        $t->boolean('root_admin')->default(false);
        $t->string('language')->default('en');
        $t->boolean('use_totp')->default(false);
        $t->string('totp_secret')->nullable();
        $t->timestamp('totp_authenticated_at')->nullable();
        $t->boolean('gravatar')->default(false);
        $t->rememberToken();
        $t->timestamps();
    });
    Schema::create('extension_settings', function (Blueprint $t): void {
        $t->increments('id'); $t->string('extension'); $t->string('scope');
        $t->unsignedInteger('user_id')->nullable(); $t->unsignedInteger('server_id')->nullable();
        $t->string('key'); $t->boolean('is_secret'); $t->text('value')->nullable();
        $t->timestamps(); $t->unique(['extension', 'scope', 'key']);
    });
    $migration = require __DIR__.'/../database/migrations/2026_10_08_000000_create_ext_userregister_requests.php';
    $migration->up();
    (new ExtensionSettings('userregister'))->set('registration_open', true);
}

return $app;

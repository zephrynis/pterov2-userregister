<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Testing\Fakes\NotificationFake;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Pterodactyl\Contracts\Users\CreatesUsers;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Extensions\ExtensionSettings;
use UserRegister\Http\RegistrationController;
use UserRegister\Http\RegistrationGuard;
use UserRegister\Notifications\VerifyEmail;
use UserRegister\Services\Registration;
use UserRegister\Settings;

$app = require __DIR__.'/bootstrap.php';
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().' at '.$error->getFile().':'.$error->getLine()."\n");
    exit(1);
});
function check(bool $condition, string $message): void
{
    if (! $condition) throw new RuntimeException($message);
    echo 'PASS: '.$message."\n";
}
function rejects(callable $callback, string $message, string $exception = ValidationException::class): void
{
    try { $callback(); } catch (Throwable $error) { check($error instanceof $exception, $message); return; }
    throw new RuntimeException('Expected rejection: '.$message);
}
function token(): string
{
    $notification = Notification::sent(new AnonymousNotifiable, VerifyEmail::class)->last();
    if (! $notification) throw new RuntimeException('No verification notification');
    return basename(parse_url($notification->toMail()->actionUrl, PHP_URL_PATH));
}
function countMail(): int { return Notification::sent(new AnonymousNotifiable, VerifyEmail::class)->count(); }
function input(string $name = 'test-user'): array
{
    return ['username' => $name, 'name_first' => 'Test', 'name_last' => 'Person', 'password' => 'test-password-123', 'password_confirmation' => 'test-password-123'];
}
function request(string $method = 'GET', array $data = []): Request
{
    $request = Request::create('/extensions/userregister', $method, $data, server: ['REMOTE_ADDR' => '192.0.2.10']);
    $request->setLaravelSession(app('session')->driver());
    app()->instance('request', $request);
    return $request;
}
app('view')->share('errors', new ViewErrorBag);
$settings = new ExtensionSettings('userregister');
$service = app(Registration::class);
$controller = app(RegistrationController::class);

check((new Settings)->requiresVerification(), 'Existing installations require verification when the new setting is absent');
rejects(fn () => $service->register([...input('bypass-user'), 'email' => 'bypass@example.test', 'require_email_verification' => false]), 'Direct signup cannot bypass required verification');
check(User::count() === 0, 'Blocked direct signup creates no account');

$service->request('  New@Example.test  ');
$first = token();
$hash = hash('sha256', $first);
check(User::count() === 0, 'Sending verification creates no Panel user');
$pending = DB::table(Registration::TABLE)->first();
check($pending->email === 'new@example.test' && $pending->token_hash === $hash && ! str_contains(json_encode($pending), $first), 'Only a hash of the random token is stored; email normalizes');
$service->request('new@example.test');
check(countMail() === 1 && $service->pending($hash) !== null, 'Immediate resend is suppressed without invalidating the link');
DB::table(Registration::TABLE)->update(['sent_at' => now()->subMinutes(2)]);
$service->request('new@example.test');
$second = token(); $hash2 = hash('sha256', $second);
check($second !== $first && $service->pending($hash) === null && $service->pending($hash2) !== null, 'Resend invalidates the previous link');

rejects(fn () => $service->complete($hash2, [...input(), 'password_confirmation' => 'different']), 'Password confirmation is required');
rejects(fn () => $service->complete($hash2, [...input(), 'password' => 'short', 'password_confirmation' => 'short']), 'Native password minimum enforced');
rejects(fn () => $service->complete($hash2, [...input(), 'username' => 'bad/username']), 'Native username rules enforced');
check($service->pending($hash2) !== null && User::count() === 0, 'Validation failures preserve the verification request');

$verifiedRequest = request();
$redirect = $controller->verify($verifiedRequest, $second);
check($redirect->getTargetUrl() === url('/extensions/userregister/complete') && $verifiedRequest->session()->get('userregister.verified') === $hash2, 'GET exchanges token for session and redirects to a clean URL');
check($service->pending($hash2) !== null, 'GET verification does not consume a link');
$user = $service->complete($hash2, [...input(), 'root_admin' => true, 'email' => 'attacker@example.test']);
check($user->email === 'new@example.test' && ! $user->root_admin && Hash::check('test-password-123', $user->password), 'Creates a native user using verified email, hashed password, and no admin privileges');
check($service->pending($hash2) === null && User::count() === 1, 'Completion consumes request atomically');
rejects(fn () => $service->complete($hash2, input('replayed-user')), 'Token replay cannot create a second account');
check(User::count() === 1, 'Duplicate completion leaves exactly one user');

$mailCount = countMail();
$service->request('new@example.test');
check(countMail() === $mailCount && DB::table(Registration::TABLE)->count() === 0, 'Existing accounts get neutral response with no signup email');
$service->request('expired@example.test'); $expired = hash('sha256', token());
DB::table(Registration::TABLE)->where('token_hash', $expired)->update(['expires_at' => now()->subSecond()]);
rejects(fn () => $service->complete($expired, input('expired-user')), 'Expired verification cannot create account');
check($service->pending($expired) === null && $service->pending('invalid') === null, 'Expired and malformed hashes are rejected');
$result = $controller->verify(request(), str_repeat('z', 64));
check($result->getTargetUrl() === url('/extensions/userregister'), 'Malformed URL tokens return to registration');

$service->request('closed@example.test'); $closedHash = hash('sha256', token());
$settings->set('registration_open', false);
rejects(fn () => $service->request('blocked@example.test'), 'Closed registration blocks requests in the service', Symfony\Component\HttpKernel\Exception\HttpException::class);
rejects(fn () => $service->complete($closedHash, input('closed-user')), 'Closed registration blocks pending completion in the service', Symfony\Component\HttpKernel\Exception\HttpException::class);
$response = (new RegistrationGuard)->handle(request(), fn () => response('unexpected'));
check($response->getStatusCode() === 403 && str_contains($response->getContent(), 'Registration is closed'), 'Closed registration renders an explanatory page');
check($response->headers->get('Referrer-Policy') === 'no-referrer' && str_contains($response->headers->get('Cache-Control'), 'no-store'), 'Signup responses prevent caching and token referrer leakage');
$settings->set('registration_open', true);

for ($i = 0; $i < 3; $i++) {
    DB::table(Registration::TABLE)->where('email', 'limit@example.test')->update(['sent_at' => now()->subMinutes(2)]);
    $service->request('limit@example.test');
}
$before = countMail();
DB::table(Registration::TABLE)->where('email', 'limit@example.test')->update(['sent_at' => now()->subMinutes(2)]);
$service->request('limit@example.test');
check(countMail() === $before, 'Email requests are limited per hour across resends');
for ($i = 0; $i < 5; $i++) $response = (new RegistrationGuard)->handle(request('POST'), fn () => response('ok'));
$response = (new RegistrationGuard)->handle(request('POST'), fn () => response('unexpected'));
check($response->getStatusCode() === 429 && $response->headers->get('Retry-After') === '60', 'IP limiter independently protects POST endpoints');

$service->request('failure@example.test'); $working = hash('sha256', token());
DB::table(Registration::TABLE)->where('email', 'failure@example.test')->update(['sent_at' => now()->subMinutes(2)]);
Notification::swap(new class extends NotificationFake {
    public function send($notifiables, $notification) { throw new RuntimeException('Simulated transport failure'); }
});
rejects(fn () => $service->request('failure@example.test'), 'Mail transport failure propagates without creating users', RuntimeException::class);
check($service->pending($working) !== null, 'Mail failure rolls back resend rotation and preserves working link');
$response = $controller->requestEmail(request('POST', ['email' => 'transport@example.test']));
check($response->getStatusCode() === 503 && ! str_contains($response->getContent(), 'Simulated transport'), 'Delivery failure offers recovery without exposing transport details');
Notification::fake();

$service->request('duplicate@example.test'); $duplicate = hash('sha256', token());
rejects(fn () => $service->complete($duplicate, input('test-user')), 'Duplicate username rejected');
check($service->pending($duplicate) !== null, 'Duplicate username does not consume token');
$nativeCreator = app(CreatesUsers::class);
$nativeCreator->create(['username' => 'external-user', 'email' => 'duplicate@example.test', 'password' => 'existing-password', 'name_first' => 'Existing', 'name_last' => 'Person']);
rejects(fn () => $service->complete($duplicate, input('other-user')), 'Email claimed by administrator during verification rejected');

$service->request('rollback@example.test'); $rollbackHash = hash('sha256', token());
$failingCreator = new class($nativeCreator) implements CreatesUsers {
    public function __construct(private CreatesUsers $native) {}
    public function create(array $data): User {
        $this->native->create($data);
        throw new RuntimeException('Simulated account creation failure');
    }
};
$failingService = new Registration(new Settings, $failingCreator);
rejects(fn () => $failingService->complete($rollbackHash, input('rollback-user')), 'Account creation failure rolls back the whole completion', RuntimeException::class);
check(! User::where('username', 'rollback-user')->exists() && $service->pending($rollbackHash) !== null, 'Failed completion leaves no user and preserves the verification request');

$settings->set('terms_url', 'https://example.test/terms');
$settings->set('privacy_url', 'javascript:alert(1)');
check((new Settings)->policyLinks() === ['Terms of service' => 'https://example.test/terms'], 'Policy links allow only valid HTTP(S) addresses');
$service->request('policy@example.test'); $policyToken = token();
$policyRequest = request(); $controller->verify($policyRequest, $policyToken);
$submission = request('POST', input('policy-user'));
$response = $controller->complete($submission);
check($response->getTargetUrl() === url('/extensions/userregister/complete') && ! User::where('username', 'policy-user')->exists(), 'Configured policy acceptance required on backend');
check(! $submission->session()->hasOldInput('password') && ! $submission->session()->hasOldInput('password_confirmation'), 'Validation redirects never flash passwords');
$submission = request('POST', [...input('policy-user'), 'accept_policies' => '1']);
$response = $controller->complete($submission);
check(str_contains($response->render(), 'Your account is ready') && ! $submission->session()->has('userregister.verified'), 'Successful controller flow clears verified session and shows sign-in link');

// Exercise the actual native request forgery middleware, outside its test bypass.
$app['env'] = 'production';
$csrf = app(Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
$unsafe = request('POST');
rejects(fn () => $csrf->handle($unsafe, fn () => response('unexpected')), 'POST without CSRF token rejected by native web middleware', Illuminate\Session\TokenMismatchException::class);
$safe = request('POST', ['_token' => $unsafe->session()->token()]);
check($csrf->handle($safe, fn () => response('ok'))->getContent() === 'ok', 'Valid CSRF token accepted');
$app['env'] = 'testing';

$command = app(UserRegister\Console\PruneRequests::class);
$command->setLaravel($app);
$command->run(new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\BufferedOutput);
check(DB::table(Registration::TABLE)->where('expires_at', '<=', now())->count() === 0, 'Scheduled cleanup removes expired requests');

$service->request('direct@example.test'); $oldDirectToken = hash('sha256', token());
$settings->set('require_email_verification', false);
check(! (new Settings)->requiresVerification(), 'Administrator can disable verification');
$directPage = $controller->start()->render();
check(str_contains($directPage, 'action="/extensions/userregister/register"') && str_contains($directPage, 'name="email"') && ! str_contains($directPage, 'Email verified:'), 'Disabled verification shows full signup form with email field and truthful copy');
$beforeMail = countMail();
$service->request('no-verification@example.test');
check(countMail() === $beforeMail && $controller->requestEmail(request('POST', ['email' => 'no-verification@example.test']))->getTargetUrl() === url('/extensions/userregister'), 'Disabled verification does not send verification mail and redirects obsolete request forms');
check($controller->sent(request())->getTargetUrl() === url('/extensions/userregister'), 'Old check-inbox page redirects to direct signup when verification is disabled');
$oldVerifiedUser = $service->complete($closedHash, input('old-verified-link-user'));
check($oldVerifiedUser->email === 'closed@example.test' && $service->pending($closedHash) === null, 'Valid previously-sent verification links still complete after verification is disabled');

rejects(fn () => $service->register([...input('bad-email-user'), 'email' => 'invalid']), 'Direct signup validates email');
rejects(fn () => $service->register([...input('bad-password-user'), 'email' => 'weak@example.test', 'password' => 'short', 'password_confirmation' => 'short']), 'Direct signup enforces native password rules');
rejects(fn () => $service->register([...input('bad-confirm-user'), 'email' => 'mismatch@example.test', 'password_confirmation' => 'different']), 'Direct signup enforces password confirmation');
rejects(fn () => $service->register([...input('test-user'), 'email' => 'duplicate-direct@example.test']), 'Direct signup rejects duplicate usernames');
$direct = $service->register([...input('direct-user'), 'email' => ' Direct@Example.test ', 'root_admin' => true, 'verified' => true]);
check($direct->email === 'direct@example.test' && ! $direct->root_admin && Hash::check('test-password-123', $direct->password), 'Direct signup normalizes email, hashes password, and ignores admin privilege injection');
check($service->pending($oldDirectToken) === null && countMail() === $beforeMail, 'Direct signup invalidates old links without sending verification email');
rejects(fn () => $service->register([...input('duplicate-email-user'), 'email' => 'direct@example.test']), 'Direct signup rejects duplicate email addresses');

$failedDirectService = new Registration(new Settings, $failingCreator);
rejects(fn () => $failedDirectService->register([...input('direct-rollback-user'), 'email' => 'direct-rollback@example.test']), 'Direct account creation failures roll back', RuntimeException::class);
check(! User::where('username', 'direct-rollback-user')->exists(), 'Failed direct signup leaves no partial account');

$directSubmission = request('POST', [...input('direct-policy-user'), 'email' => 'direct-policy@example.test']);
$response = $controller->register($directSubmission);
check($response->getTargetUrl() === url('/extensions/userregister') && ! User::where('username', 'direct-policy-user')->exists(), 'Direct signup also requires configured policy acceptance');
check(! $directSubmission->session()->hasOldInput('password') && ! $directSubmission->session()->hasOldInput('password_confirmation'), 'Direct signup validation does not flash passwords');
$directSubmission = request('POST', [...input('direct-policy-user'), 'email' => 'direct-policy@example.test', 'accept_policies' => '1']);
$response = $controller->register($directSubmission);
check(str_contains($response->render(), 'Your account is ready') && ! str_contains($response->render(), 'Your email is verified'), 'Direct signup success does not claim email was verified');

$settings->set('require_email_verification', true);
$staleForm = request('POST', [...input('stale-direct-user'), 'email' => 'stale-direct@example.test', 'accept_policies' => '1']);
$response = $controller->register($staleForm);
check($response->getTargetUrl() === url('/extensions/userregister') && ! User::where('username', 'stale-direct-user')->exists(), 'Re-enabling verification rejects an already-open direct registration form');
check(str_contains($controller->start()->render(), 'Send verification email') && ! str_contains($controller->start()->render(), 'name="password"'), 'Re-enabled verification immediately restores email-first signup');
$settings->set('require_email_verification', false);
$settings->set('registration_open', false);
rejects(fn () => $service->register([...input('closed-direct-user'), 'email' => 'closed-direct@example.test']), 'Closing registration blocks direct signup too', Symfony\Component\HttpKernel\Exception\HttpException::class);
$settings->set('registration_open', true);
$settings->set('require_email_verification', true);

$manifest = app(Pterodactyl\Services\Extensions\ExtensionManifestValidator::class)->fromDirectory(dirname(__DIR__));
$provider = new UserRegister\UserRegisterProvider($app, $manifest);
$provider->beginRegistration();
$provider->boot();
$provider->commitRegistration();
$definition = app(Pterodactyl\Services\Extensions\ExtensionSettingsRegistry::class)->get('userregister');
check($definition !== null && $definition->frontendConfig(publicOnly: true) === ['registration_open' => true], 'Native provider boots and exposes only the registration switch to guests');
$routes = array_filter(iterator_to_array(app('router')->getRoutes()), fn ($route) => str_starts_with((string) $route->getName(), 'extensions.userregister.web.'));
check(count($routes) === 7, 'Native route helper registers all seven extension endpoints');
check($definition->get('require_email_verification') === true && in_array('require_email_verification', array_column($definition->schema(), 'input'), true), 'Native administrator settings include the verification toggle');
DB::table('extension_settings')->where('extension', 'userregister')->where('key', 'registration_open')->delete();
check(! (new Settings)->open(), 'Missing registration setting defaults closed');
echo "All checks passed using isolated SQLite, real Panel account creation, and fake notifications.\n";

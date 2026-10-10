<?php

declare(strict_types=1);

namespace UserRegister\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use UserRegister\Settings;

final class RegistrationGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(Settings::class)->open()) {
            $response = response()->view('ext-userregister::message', ['title' => 'Registration is closed', 'message' => 'New accounts are not being accepted right now. Please contact the Panel administrator.'], 403);
        } elseif ($request->isMethod('POST') && ! RateLimiter::attempt('userregister:ip:'.hash('sha256', (string) $request->ip()), 5, fn () => true, 60)) {
            $response = response()->view('ext-userregister::message', ['title' => 'Please slow down', 'message' => 'Too many attempts. Wait a minute before trying again.'], 429)->header('Retry-After', '60');
        } else {
            $response = $next($request);
        }

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}

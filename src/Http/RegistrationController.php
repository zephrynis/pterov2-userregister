<?php

declare(strict_types=1);

namespace UserRegister\Http;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use UserRegister\Services\Registration;
use UserRegister\Settings;
use Throwable;

final class RegistrationController
{
    public function __construct(private readonly Registration $registration, private readonly Settings $settings) {}

    public function start()
    {
        if (! $this->settings->requiresVerification()) {
            return view('ext-userregister::complete', ['verified' => false, 'policies' => $this->settings->policyLinks()]);
        }

        return view('ext-userregister::start');
    }

    public function requestEmail(Request $request)
    {
        if (! $this->settings->requiresVerification()) {
            return redirect('/extensions/userregister');
        }
        $email = $request->input('email');
        if (! is_string($email)) {
            return redirect('/extensions/userregister')->withErrors(['email' => 'Please enter a valid email address.']);
        }
        $email = mb_strtolower(trim($email));
        try {
            $this->registration->request($email);
        } catch (ValidationException $error) {
            return redirect('/extensions/userregister')->withErrors($error->errors())->withInput(['email' => $email]);
        } catch (Throwable) {
            // Never log the transport exception: it may contain credentials or the raw token.
            return response()->view('ext-userregister::message', ['title' => 'Email is temporarily unavailable', 'message' => 'We could not process your request. Please try again later or contact the Panel administrator.'], 503);
        }
        $request->session()->put('userregister.email', $email);

        return redirect('/extensions/userregister/sent');
    }

    public function sent(Request $request)
    {
        if (! $this->settings->requiresVerification()) {
            return redirect('/extensions/userregister');
        }

        return view('ext-userregister::sent', ['email' => $request->session()->get('userregister.email', ''), 'cooldown' => $this->settings->cooldownSeconds()]);
    }

    public function register(Request $request)
    {
        try {
            if ($this->settings->policyLinks() !== []) {
                $request->validate(['accept_policies' => ['accepted']]);
            }
            $this->registration->register($request->only(['email', 'username', 'name_first', 'name_last', 'password', 'password_confirmation']));
        } catch (ValidationException $error) {
            return redirect('/extensions/userregister')->withErrors($error->errors())
                ->withInput($request->only(['email', 'username', 'name_first', 'name_last']));
        } catch (Throwable) {
            return $this->creationFailed();
        }

        return $this->success($request, verified: false);
    }

    public function verify(Request $request, string $token)
    {
        if (! preg_match('/\A[a-f0-9]{64}\z/D', $token) || ! $this->registration->pending(hash('sha256', $token))) {
            return $this->invalid($request);
        }
        $request->session()->regenerate();
        $request->session()->put('userregister.verified', hash('sha256', $token));

        // Exchange the URL token for a session, keeping it off forms and later URLs.
        return redirect('/extensions/userregister/complete');
    }

    public function form(Request $request)
    {
        $hash = (string) $request->session()->get('userregister.verified', '');
        $pending = $this->registration->pending($hash);
        if (! $pending) {
            return $this->invalid($request);
        }

        return view('ext-userregister::complete', ['verified' => true, 'email' => $pending->email, 'policies' => $this->settings->policyLinks()]);
    }

    public function complete(Request $request)
    {
        $hash = (string) $request->session()->get('userregister.verified', '');
        if (! $this->registration->pending($hash)) {
            return $this->invalid($request);
        }
        try {
            if ($this->settings->policyLinks() !== []) {
                $request->validate(['accept_policies' => ['accepted']]);
            }
            $this->registration->complete($hash, $request->only(['username', 'name_first', 'name_last', 'password', 'password_confirmation']));
        } catch (ValidationException $error) {
            return redirect('/extensions/userregister/complete')->withErrors($error->errors())
                ->withInput($request->only(['username', 'name_first', 'name_last']));
        } catch (Throwable) {
            return $this->creationFailed();
        }

        return $this->success($request, verified: true);
    }

    private function success(Request $request, bool $verified)
    {
        $request->session()->forget(['userregister.verified', 'userregister.email']);
        $request->session()->regenerate();

        return view('ext-userregister::message', ['title' => 'Your account is ready', 'message' => $verified
            ? 'Your email is verified and your account has been created. You can now sign in.'
            : 'Your account has been created. You can now sign in.']);
    }

    private function creationFailed()
    {
        return response()->view('ext-userregister::message', ['title' => 'Please try again', 'message' => 'We could not finish creating your account. Try signing in to check whether your account was created, or retry later.'], 503);
    }

    private function invalid(Request $request)
    {
        $request->session()->forget('userregister.verified');

        return redirect('/extensions/userregister')->withErrors(['verification' => 'This verification link has expired or has already been used. Please start registration again.']);
    }
}

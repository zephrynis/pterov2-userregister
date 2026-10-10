<?php

declare(strict_types=1);

namespace UserRegister\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Pterodactyl\Contracts\Users\CreatesUsers;
use Pterodactyl\Models\User;
use Pterodactyl\Rules\UserEmail;
use Pterodactyl\Validation\UserRules;
use UserRegister\Notifications\VerifyEmail;
use UserRegister\Settings;

final class Registration
{
    public const TABLE = 'ext_userregister_requests';

    public function __construct(private readonly Settings $settings, private readonly CreatesUsers $users) {}

    public function assertOpen(): void
    {
        abort_unless($this->settings->open(), 403, 'Registration is currently closed.');
    }

    public function request(string $email): void
    {
        $this->assertOpen();
        if (! $this->settings->requiresVerification()) {
            return;
        }
        $email = mb_strtolower(trim($email));
        Validator::make(['email' => $email], ['email' => ['required', 'string', 'email:strict', 'max:191', new UserEmail]])->validate();
        $key = 'userregister:email:'.hash('sha256', $email);
        // Rate limits and cooldowns return the same neutral result as an existing account.
        if (! RateLimiter::attempt($key, $this->settings->requestsPerHour(), fn () => true, 3600)) {
            return;
        }

        DB::transaction(function () use ($email): void {
            if (User::query()->where('email', $email)->exists()) {
                return;
            }

            $token = bin2hex(random_bytes(32));
            $minutes = $this->settings->expiryMinutes();
            // Atomic insert + row lock serialize concurrent first requests and resends.
            $inserted = DB::table(self::TABLE)->insertOrIgnore([
                'email' => $email, 'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addMinutes($minutes), 'sent_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $pending = DB::table(self::TABLE)->where('email', $email)->lockForUpdate()->first();
            if (! $pending || (! $inserted && strtotime($pending->sent_at) > now()->subSeconds($this->settings->cooldownSeconds())->getTimestamp())) {
                return;
            }

            DB::table(self::TABLE)->where('id', $pending->id)->update([
                'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes($minutes),
                'sent_at' => now(), 'updated_at' => now(),
            ]);
            // Failure rolls back the rotation, preserving any previous working link.
            Notification::route('mail', $email)->notify(new VerifyEmail($token, $minutes));
        });
    }

    public function pending(string $hash): ?object
    {
        if (! preg_match('/\A[a-f0-9]{64}\z/D', $hash)) {
            return null;
        }

        return DB::table(self::TABLE)->where('token_hash', $hash)->where('expires_at', '>', now())->first();
    }

    public function complete(string $hash, array $input): User
    {
        $this->assertOpen();
        try {
            return DB::transaction(function () use ($hash, $input): User {
                $pending = DB::table(self::TABLE)->where('token_hash', $hash)->lockForUpdate()->first();
                if (! $pending || strtotime($pending->expires_at) <= now()->getTimestamp()) {
                    throw ValidationException::withMessages(['verification' => 'This verification link has expired or has already been used. Please start registration again.']);
                }
                // The verified address comes from storage, never from the submitted form.
                $user = $this->createUser($input, $pending->email);
                DB::table(self::TABLE)->where('id', $pending->id)->delete();

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // The native unique indexes also protect races with admin-created users.
            throw ValidationException::withMessages(['verification' => 'The email address or username is no longer available. Sign in or try a different username.']);
        }
    }

    public function register(array $input): User
    {
        $this->assertOpen();
        if ($this->settings->requiresVerification()) {
            throw ValidationException::withMessages(['verification' => 'Email verification is required. Please request a verification email to continue.']);
        }
        $email = mb_strtolower(trim(is_string($input['email'] ?? null) ? $input['email'] : ''));

        try {
            return DB::transaction(function () use ($input, $email): User {
                $user = $this->createUser($input, $email);
                // Any old verification links for this address are now obsolete.
                DB::table(self::TABLE)->where('email', $email)->delete();

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'The email address or username is no longer available. Sign in or use different account details.']);
        }
    }

    private function createUser(array $input, string $email): User
    {
        $data = [
            'email' => $email,
            'username' => mb_strtolower(trim(is_string($input['username'] ?? null) ? $input['username'] : '')),
            'name_first' => $input['name_first'] ?? null,
            'name_last' => $input['name_last'] ?? null,
            'password' => $input['password'] ?? null,
            'password_confirmation' => $input['password_confirmation'] ?? null,
        ];
        $native = UserRules::rules();
        $rules = array_intersect_key($native, array_flip(['email', 'username', 'name_first', 'name_last']));
        $rules['password'] = ['required', ...UserRules::PASSWORD, 'max:1024', 'confirmed'];
        $validated = Validator::make($data, $rules)->validate();
        unset($validated['password_confirmation']);

        return $this->users->create([...$validated, 'root_admin' => false]);
    }
}

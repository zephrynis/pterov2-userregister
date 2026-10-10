<?php

declare(strict_types=1);

namespace UserRegister\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Send immediately: a resend must not enqueue an obsolete raw token.
final class VerifyEmail extends Notification
{
    public function __construct(private readonly string $token, private readonly int $minutes) {}

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your email — '.config('app.name', 'Pterodactyl'))
            ->greeting('Create your account')
            ->line('Verify your email address to finish creating your account. No account has been created yet.')
            ->action('Verify email', url('/extensions/userregister/verify/'.$this->token))
            ->line('This link expires in '.$this->minutes.' minutes. Requesting another email replaces this link.')
            ->line('If you did not request an account, you can ignore this message.');
    }
}

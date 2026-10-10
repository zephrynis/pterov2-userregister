<?php

declare(strict_types=1);

namespace UserRegister;

use Illuminate\Console\Scheduling\Schedule;
use Pterodactyl\Extensions\ExtensionProvider;
use Pterodactyl\Services\Extensions\ExtensionSettingDefinition as Field;
use Pterodactyl\Services\Extensions\ExtensionSettingsDefinition;
use UserRegister\Console\PruneRequests;

final class UserRegisterProvider extends ExtensionProvider
{
    public function boot(): void
    {
        $this->registerWebRoutes($this->extensionPath('routes', 'web.php'));
        $this->loadExtensionMigrations();
        $this->loadExtensionViews();
        $this->registerSettings(new ExtensionSettingsDefinition($this->settings(), [
            Field::make('registration_open', 'registration_open', false, ['required', 'boolean'])
                ->label('Open public registration')->help('Closing registration also blocks pending completions. Check Panel mail delivery when email verification is required.')
                ->field('toggle')->frontend()->public()->frontendType('boolean'),
            Field::make('require_email_verification', 'require_email_verification', true, ['required', 'boolean'])
                ->label('Require email verification')->help('Require visitors to verify their email before creating an account. Turn off to show the full registration form immediately.')
                ->field('toggle'),
            Field::make('verification_minutes', 'verification_minutes', 30, ['required', 'integer', 'min:5', 'max:1440'])
                ->label('Verification link lifetime (minutes)')->field('number'),
            Field::make('resend_seconds', 'resend_seconds', 60, ['required', 'integer', 'min:60', 'max:3600'])
                ->label('Resend cooldown (seconds)')->field('number'),
            Field::make('email_requests_per_hour', 'email_requests_per_hour', 3, ['required', 'integer', 'min:1', 'max:10'])
                ->label('Maximum requests per email each hour')->field('number'),
            Field::make('terms_url', 'terms_url', '', ['nullable', 'url:http,https', 'max:2048'])
                ->label('Terms of service URL (optional)')->field('text'),
            Field::make('privacy_url', 'privacy_url', '', ['nullable', 'url:http,https', 'max:2048'])
                ->label('Privacy policy URL (optional)')->field('text'),
        ]));
        $this->registerCommands([PruneRequests::class]);
        $this->registerSchedule(fn (Schedule $schedule) => $schedule->command('userregister:prune')->hourly()->withoutOverlapping());
    }
}

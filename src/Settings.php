<?php

declare(strict_types=1);

namespace UserRegister;

use Pterodactyl\Services\Extensions\ExtensionSettings;

final class Settings
{
    private function store(): ExtensionSettings
    {
        // Read fresh settings on every operation, including long-lived workers.
        return new ExtensionSettings('userregister');
    }

    public function open(): bool
    {
        return $this->store()->get('registration_open', false) === true;
    }

    public function requiresVerification(): bool
    {
        // Existing installations keep verification unless an admin explicitly disables it.
        return $this->store()->get('require_email_verification', true) !== false;
    }

    public function expiryMinutes(): int
    {
        return max(5, min(1440, (int) $this->store()->get('verification_minutes', 30)));
    }

    public function cooldownSeconds(): int
    {
        return max(60, min(3600, (int) $this->store()->get('resend_seconds', 60)));
    }

    public function requestsPerHour(): int
    {
        return max(1, min(10, (int) $this->store()->get('email_requests_per_hour', 3)));
    }

    public function policyLinks(): array
    {
        $links = [];
        foreach (['terms_url' => 'Terms of service', 'privacy_url' => 'Privacy policy'] as $key => $label) {
            $url = $this->store()->get($key, '');
            if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                $links[$label] = $url;
            }
        }

        return $links;
    }
}

<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Support;

use Illuminate\Support\Facades\Http;
use Statamic\Facades\Addon;

class RiffRaff
{
    private const ADDON_ID = 'alt-design/alt-riffraff';

    public static function url(string $pathConfigKey): string
    {
        $base = rtrim((string) config('alt-riffraff.api_base_url'), '/');
        $path = '/' . ltrim((string) config('alt-riffraff.' . $pathConfigKey), '/');

        return $base . $path;
    }

    public static function token(): ?string
    {
        if ($apiKey = self::apiKey()) {
            return $apiKey;
        }

        return self::tokenFromLogin();
    }

    /**
     * Resolves the configured API key. The `ALT_RIFFRAFF_API_KEY` environment
     * variable always wins over the value saved against the addon's control
     * panel settings, so a key can be overridden per environment without
     * editing a file that may be committed to version control.
     */
    public static function apiKey(): ?string
    {
        $configured = (string) config('alt-riffraff.api_key', '');

        if ($configured !== '') {
            return $configured;
        }

        return self::apiKeyFromSettings();
    }

    private static function apiKeyFromSettings(): ?string
    {
        $addon = Addon::get(self::ADDON_ID);

        if (! $addon || ! method_exists($addon, 'setting')) {
            return null;
        }

        $value = $addon->setting('api_key');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function tokenFromLogin(): ?string
    {
        $email = (string) config('alt-riffraff.api_email', '');
        $password = (string) config('alt-riffraff.api_password', '');

        if (empty($email) || empty($password)) {
            return null;
        }

        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->post(self::url('api_authentication_path'), [
            'email' => $email,
            'password' => $password,
        ]);

        if ($response->failed()) {
            return null;
        }

        return $response->json('data');
    }
}

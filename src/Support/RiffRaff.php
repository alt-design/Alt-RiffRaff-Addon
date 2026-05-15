<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Support;

use Illuminate\Support\Facades\Http;

class RiffRaff
{
    public static function url(string $pathConfigKey): string
    {
        $base = rtrim((string) config('alt-riffraff.api_base_url'), '/');
        $path = '/' . ltrim((string) config('alt-riffraff.' . $pathConfigKey), '/');

        return $base . $path;
    }

    public static function token(): ?string
    {
        $apiKey = (string) config('alt-riffraff.api_key', '');

        if (! empty($apiKey)) {
            return $apiKey;
        }

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

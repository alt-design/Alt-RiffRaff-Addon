<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Connect;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * A request for a key that is waiting for a team manager to approve it. It is
 * kept in the cache until it expires, so running the command again picks up
 * the same code instead of asking for a new one.
 */
final class PendingConnection
{
    private const CACHE_KEY = 'alt-riffraff.connect.pending';

    public function __construct(
        public readonly string $siteName,
        #[\SensitiveParameter] public readonly string $deviceCode,
        public readonly string $userCode,
        public readonly string $verificationUri,
        public readonly int $interval,
        public readonly int $expiresAt,
    ) {}

    public static function current(): ?self
    {
        $stored = Cache::get(self::CACHE_KEY);

        if (! is_array($stored)) {
            return null;
        }

        $pending = self::fromArray($stored);

        return $pending === null || $pending->hasExpired() ? null : $pending;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function remember(): void
    {
        Cache::put(self::CACHE_KEY, $this->toArray(), Carbon::createFromTimestamp($this->expiresAt));
    }

    public function withInterval(int $interval): self
    {
        return new self(
            $this->siteName,
            $this->deviceCode,
            $this->userCode,
            $this->verificationUri,
            max(1, $interval),
            $this->expiresAt,
        );
    }

    public function hasExpired(): bool
    {
        return Carbon::now()->getTimestamp() >= $this->expiresAt;
    }

    /**
     * @param  array<mixed>  $stored
     */
    private static function fromArray(array $stored): ?self
    {
        foreach (['site_name', 'device_code', 'user_code', 'verification_uri'] as $key) {
            if (! is_string($stored[$key] ?? null) || $stored[$key] === '') {
                return null;
            }
        }

        if (! is_int($stored['interval'] ?? null) || ! is_int($stored['expires_at'] ?? null)) {
            return null;
        }

        return new self(
            $stored['site_name'],
            $stored['device_code'],
            $stored['user_code'],
            $stored['verification_uri'],
            max(1, $stored['interval']),
            $stored['expires_at'],
        );
    }

    /**
     * @return array{site_name: string, device_code: string, user_code: string, verification_uri: string, interval: int, expires_at: int}
     */
    private function toArray(): array
    {
        return [
            'site_name' => $this->siteName,
            'device_code' => $this->deviceCode,
            'user_code' => $this->userCode,
            'verification_uri' => $this->verificationUri,
            'interval' => $this->interval,
            'expires_at' => $this->expiresAt,
        ];
    }
}

<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Connect;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\NullStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * A request for a key that is waiting for a team manager to approve it. It is
 * kept in the cache, encrypted, until it expires, so running the command
 * again picks up the same code instead of asking for a new one.
 */
final class PendingConnection
{
    private const CACHE_KEY_PREFIX = 'alt-riffraff.connect.pending.';

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
        try {
            $stored = Cache::get(self::cacheKey());
            $payload = is_string($stored) ? Crypt::decrypt($stored) : null;
        } catch (Throwable) {
            return null;
        }

        $pending = is_array($payload) ? self::fromArray($payload) : null;

        return $pending === null || $pending->hasExpired() ? null : $pending;
    }

    public static function forget(): void
    {
        try {
            Cache::forget(self::cacheKey());
        } catch (Throwable) {
            return;
        }
    }

    /**
     * Whether a later run of the command will be able to pick this request up.
     */
    public function remember(): bool
    {
        if (self::cacheForgetsBetweenRuns()) {
            return false;
        }

        try {
            return Cache::put(
                self::cacheKey(),
                Crypt::encrypt($this->toArray()),
                Carbon::createFromTimestamp($this->expiresAt),
            );
        } catch (Throwable) {
            return false;
        }
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

    private static function cacheForgetsBetweenRuns(): bool
    {
        try {
            $store = Cache::getStore();
        } catch (Throwable) {
            return true;
        }

        return $store instanceof ArrayStore || $store instanceof NullStore;
    }

    /**
     * Unique to this site and RiffRaff installation, because several sites on
     * one server can share a cache store and key prefix.
     */
    private static function cacheKey(): string
    {
        $site = base_path() . '|' . config('alt-riffraff.api_base_url');

        return self::CACHE_KEY_PREFIX . substr(hash('sha256', $site), 0, 32);
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

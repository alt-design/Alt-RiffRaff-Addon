<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Connect;

use AltDesign\RiffRaff\Support\RiffRaff;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Asks RiffRaff for a key on this site's behalf with the OAuth device flow:
 * RiffRaff hands back a code, a team manager approves it in the dashboard,
 * and the site collects the key by polling.
 */
final class DeviceFlow
{
    public const GRANT_TYPE = 'urn:ietf:params:oauth:grant-type:device_code';

    private const TIMEOUT_SECONDS = 15;

    /**
     * @throws ConnectionFailed
     */
    public function requestCode(string $siteName): PendingConnection
    {
        try {
            $response = $this->http()->post(
                RiffRaff::url('api_device_code_path', '/api/device/code'),
                ['site_name' => $siteName],
            );
        } catch (ConnectionException) {
            throw new ConnectionFailed($this->unreachable());
        }

        if ($response->status() === 429) {
            throw new ConnectionFailed('Too many requests from this server are already waiting for approval. Approve or deny them in RiffRaff, or wait for them to expire, then try again.');
        }

        if ($response->failed()) {
            throw new ConnectionFailed('RiffRaff refused the request: ' . $this->describeFailure($response));
        }

        return $this->pendingFrom($response, $siteName)
            ?? throw new ConnectionFailed('RiffRaff sent a reply this version of the addon does not understand. Updating the addon may help.');
    }

    public function poll(PendingConnection $pending): PollResult
    {
        try {
            $response = $this->http()->post(
                RiffRaff::url('api_device_token_path', '/api/device/token'),
                ['grant_type' => self::GRANT_TYPE, 'device_code' => $pending->deviceCode],
            );
        } catch (ConnectionException) {
            return PollResult::of(PollStatus::Unavailable);
        }

        if ($response->successful()) {
            return $this->approvalFrom($response, $pending);
        }

        if ($response->serverError()) {
            return PollResult::of(PollStatus::Unavailable);
        }

        return match ($response->json('error')) {
            'authorization_pending' => PollResult::of(PollStatus::Pending),
            'slow_down' => PollResult::slowDown($this->slowerInterval($response, $pending)),
            'access_denied' => PollResult::of(PollStatus::Denied),
            'expired_token' => PollResult::of(PollStatus::Expired),
            'invalid_grant' => PollResult::of(PollStatus::Unrecognised),
            default => PollResult::of(PollStatus::Unavailable),
        };
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('alt-riffraff.api_base_url'), '/');
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->asJson()->timeout(self::TIMEOUT_SECONDS);
    }

    private function pendingFrom(Response $response, string $siteName): ?PendingConnection
    {
        $body = $response->json();

        if (! is_array($body)) {
            return null;
        }

        foreach (['device_code', 'user_code', 'verification_uri'] as $key) {
            if (! is_string($body[$key] ?? null) || $body[$key] === '') {
                return null;
            }
        }

        if (! is_int($body['expires_in'] ?? null) || $body['expires_in'] <= 0) {
            return null;
        }

        return new PendingConnection(
            siteName: $siteName,
            deviceCode: $body['device_code'],
            userCode: $body['user_code'],
            verificationUri: $body['verification_uri'],
            interval: is_int($body['interval'] ?? null) ? max(1, $body['interval']) : 5,
            expiresAt: Carbon::now()->getTimestamp() + $body['expires_in'],
        );
    }

    private function approvalFrom(Response $response, PendingConnection $pending): PollResult
    {
        $key = $response->json('access_token');
        $site = $response->json('site');

        if (! is_string($key) || $key === '') {
            return PollResult::of(PollStatus::Unavailable);
        }

        return PollResult::approved($key, is_string($site) && $site !== '' ? $site : $pending->siteName);
    }

    private function slowerInterval(Response $response, PendingConnection $pending): int
    {
        $interval = $response->json('interval');

        return is_int($interval) && $interval > 0 ? $interval : $pending->interval + 5;
    }

    private function describeFailure(Response $response): string
    {
        $description = $response->json('error_description') ?? $response->json('message');

        return is_string($description) && $description !== ''
            ? $description
            : 'HTTP status ' . $response->status() . '.';
    }

    private function unreachable(): string
    {
        return 'Could not reach RiffRaff at ' . $this->baseUrl() . '. Check this server can make outgoing HTTPS requests, then try again.';
    }
}

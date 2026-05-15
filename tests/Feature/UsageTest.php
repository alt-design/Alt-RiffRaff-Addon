<?php

declare(strict_types=1);

use AltDesign\RiffRaff\Http\Controllers\AltSpamController;
use AltDesign\RiffRaff\Support\RiffRaff;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'alt-riffraff.api_base_url' => 'https://api.riff-raff.test',
        'alt-riffraff.api_authentication_path' => '/api/login',
        'alt-riffraff.api_evaluate_path' => '/api/evaluate',
        'alt-riffraff.api_usage_path' => '/api/usage',
        'alt-riffraff.api_key' => '',
        'alt-riffraff.api_email' => '',
        'alt-riffraff.api_password' => '',
    ]);

    Cache::flush();
});

function invokeFetchUsage(): ?array
{
    $controller = new AltSpamController;
    $method = (new ReflectionClass($controller))->getMethod('fetchUsage');

    return $method->invoke($controller);
}

it('joins base url and path', function () {
    expect(RiffRaff::url('api_usage_path'))
        ->toBe('https://api.riff-raff.test/api/usage');
});

it('trims trailing and leading slashes when building the url', function () {
    config([
        'alt-riffraff.api_base_url' => 'https://api.riff-raff.test/',
        'alt-riffraff.api_usage_path' => 'api/usage',
    ]);

    expect(RiffRaff::url('api_usage_path'))
        ->toBe('https://api.riff-raff.test/api/usage');
});

it('returns the api key as the token when one is set', function () {
    config(['alt-riffraff.api_key' => '17|abc123']);

    expect(RiffRaff::token())->toBe('17|abc123');
});

it('logs in when only email and password are configured', function () {
    config([
        'alt-riffraff.api_email' => 'me@example.test',
        'alt-riffraff.api_password' => 'secret',
    ]);

    Http::fake([
        'https://api.riff-raff.test/api/login' => Http::response(['data' => '9|fromlogin'], 200),
    ]);

    expect(RiffRaff::token())->toBe('9|fromlogin');
});

it('returns null when no credentials are configured', function () {
    expect(RiffRaff::token())->toBeNull();
});

it('fetches and returns the parsed usage body', function () {
    config(['alt-riffraff.api_key' => '17|abc123']);

    Http::fake([
        'https://api.riff-raff.test/api/usage' => Http::response([
            'plan' => ['slug' => 'starter', 'name' => 'Starter', 'is_unlimited' => false],
            'is_exempt' => false,
            'limit' => 25000,
            'used' => 1284,
            'remaining' => 23716,
            'percent_used' => 5,
            'period' => [
                'start' => '2026-05-01T00:00:00+00:00',
                'end' => '2026-05-31T23:59:59+00:00',
                'resets_at' => '2026-06-01T00:00:00+00:00',
            ],
        ], 200),
    ]);

    $usage = invokeFetchUsage();

    expect($usage['used'])->toBe(1284)
        ->and($usage['limit'])->toBe(25000)
        ->and($usage['plan']['name'])->toBe('Starter');
});

it('returns null usage when no token is available', function () {
    expect(invokeFetchUsage())->toBeNull();
});

it('returns null usage when the upstream call fails', function () {
    config(['alt-riffraff.api_key' => '17|abc123']);

    Http::fake([
        'https://api.riff-raff.test/api/usage' => Http::response('', 500),
    ]);

    expect(invokeFetchUsage())->toBeNull();
});

it('caches the usage response so repeat calls dont hit the api', function () {
    config(['alt-riffraff.api_key' => '17|abc123']);

    Http::fake([
        'https://api.riff-raff.test/api/usage' => Http::sequence()
            ->push(['used' => 1, 'limit' => 10, 'remaining' => 9, 'percent_used' => 10, 'plan' => ['is_unlimited' => false]], 200)
            ->push(['used' => 2, 'limit' => 10, 'remaining' => 8, 'percent_used' => 20, 'plan' => ['is_unlimited' => false]], 200),
    ]);

    expect(invokeFetchUsage()['used'])->toBe(1)
        ->and(invokeFetchUsage()['used'])->toBe(1);
});

<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Statamic\Facades\Addon;

const USAGE_URL = 'https://api.riff-raff.test/api/usage';
const LOGIN_URL = 'https://api.riff-raff.test/api/login';

beforeEach(function () {
    config([
        'alt-riffraff.api_base_url' => 'https://api.riff-raff.test',
        'alt-riffraff.api_key' => '',
        'alt-riffraff.api_email' => '',
        'alt-riffraff.api_password' => '',
    ]);

    Cache::flush();
});

afterEach(function () {
    File::delete(resource_path('addons/alt-riffraff.yaml'));
});

/**
 * @return array<string, mixed>
 */
function usageReply(array $overrides = []): array
{
    return [
        'plan' => ['slug' => 'starter', 'name' => 'Starter', 'is_unlimited' => false],
        'is_exempt' => false,
        'limit' => 25000,
        'used' => 1284,
        'remaining' => 23716,
        'percent_used' => 5,
        'site' => 'Acme website',
        ...$overrides,
    ];
}

function status(): array
{
    $exitCode = Artisan::call('statamic:riffraff:status');

    return [$exitCode, Artisan::output()];
}

it('says when no key is configured', function () {
    Http::fake();

    [$exitCode, $output] = status();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('No key is configured')
        ->and($output)->toContain('php please riffraff:connect');
    Http::assertNothingSent();
});

it('reports a key from the environment that works, with the site, plan and usage', function () {
    config(['alt-riffraff.api_key' => '17|environment-key']);
    Http::fake([USAGE_URL => Http::response(usageReply())]);

    [$exitCode, $output] = status();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('From the environment (ALT_RIFFRAFF_API_KEY)')
        ->and($output)->toContain('Acme website')
        ->and($output)->toContain('Starter')
        ->and($output)->toContain('1,284 of 25,000 (5%)')
        ->and($output)->not->toContain('environment-key');
    Http::assertSent(fn (Request $request) => $request->url() === USAGE_URL
        && $request->hasHeader('Authorization', 'Bearer 17|environment-key'));
});

it('reports a key saved in the addon settings', function () {
    Addon::get('alt-design/alt-riffraff')->settings()->set(['api_key' => '18|saved-key'])->save();
    Http::fake([USAGE_URL => Http::response(usageReply())]);

    [$exitCode, $output] = status();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Saved in the addon settings')
        ->and($output)->not->toContain('saved-key');
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer 18|saved-key'));
});

it('warns when a saved key is shadowed by the environment', function () {
    config(['alt-riffraff.api_key' => '17|environment-key']);
    Addon::get('alt-design/alt-riffraff')->settings()->set(['api_key' => '18|saved-key'])->save();
    Http::fake([USAGE_URL => Http::response(usageReply())]);

    [, $output] = status();

    expect($output)->toContain('From the environment')
        ->and($output)->toContain('A key is also saved in the addon settings. It is not used while ALT_RIFFRAFF_API_KEY is set.')
        ->and($output)->not->toContain('saved-key');
});

it('reports signing in with an email and password', function () {
    config(['alt-riffraff.api_email' => 'site@example.test', 'alt-riffraff.api_password' => 'secret-password']);
    Http::fake([
        LOGIN_URL => Http::response(['data' => '9|from-login'], 200),
        USAGE_URL => Http::response(usageReply()),
    ]);

    [$exitCode, $output] = status();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Signing in with ALT_RIFFRAFF_EMAIL and ALT_RIFFRAFF_PASSWORD')
        ->and($output)->not->toContain('from-login')
        ->and($output)->not->toContain('secret-password');
});

it('says when signing in fails', function () {
    config(['alt-riffraff.api_email' => 'site@example.test', 'alt-riffraff.api_password' => 'wrong']);
    Http::fake([LOGIN_URL => Http::response(['message' => 'Invalid credentials'], 401)]);

    [$exitCode, $output] = status();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Signing in to RiffRaff failed');
});

it('says when RiffRaff does not accept the key', function () {
    config(['alt-riffraff.api_key' => '17|revoked-key']);
    Http::fake([USAGE_URL => Http::response(['message' => 'Unauthenticated.'], 401)]);

    [$exitCode, $output] = status();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('RiffRaff does not accept this key')
        ->and($output)->not->toContain('revoked-key');
});

it('says when RiffRaff answers with an error', function () {
    config(['alt-riffraff.api_key' => '17|environment-key']);
    Http::fake([USAGE_URL => Http::response('', 503)]);

    [$exitCode, $output] = status();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('RiffRaff answered with HTTP status 503.');
});

it('says when RiffRaff cannot be reached', function () {
    config(['alt-riffraff.api_key' => '17|environment-key']);
    Http::fake([USAGE_URL => Http::failedConnection()]);

    [$exitCode, $output] = status();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Could not reach RiffRaff at https://api.riff-raff.test.');
});

it('describes an unlimited plan and a key not linked to a site', function () {
    config(['alt-riffraff.api_key' => '17|environment-key']);
    Http::fake([USAGE_URL => Http::response(usageReply([
        'plan' => ['slug' => 'agency', 'name' => 'Agency', 'is_unlimited' => true],
        'limit' => null,
        'remaining' => null,
        'percent_used' => null,
        'site' => null,
    ]))]);

    [$exitCode, $output] = status();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Not linked to a site')
        ->and($output)->toContain('1,284, with no monthly limit');
});

it('copes with a usage reply that has no plan or usage figures', function () {
    config(['alt-riffraff.api_key' => '17|environment-key']);
    Http::fake([USAGE_URL => Http::response(['plan' => null])]);

    [$exitCode, $output] = status();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('None')
        ->and($output)->toContain('Unknown');
});

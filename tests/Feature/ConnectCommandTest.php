<?php

declare(strict_types=1);

use AltDesign\RiffRaff\Connect\KeyStorage;
use AltDesign\RiffRaff\Connect\PendingConnection;
use AltDesign\RiffRaff\Support\RiffRaff;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Statamic\Facades\Addon;

const CODE_URL = 'https://api.riff-raff.test/api/device/code';
const TOKEN_URL = 'https://api.riff-raff.test/api/device/token';
const ISSUED_KEY = '42|issued-key-never-printed';
const DEVICE_CODE = 'device-code-never-printed';

beforeEach(function () {
    config([
        'alt-riffraff.api_base_url' => 'https://api.riff-raff.test',
        'alt-riffraff.api_key' => '',
        'alt-riffraff.api_email' => '',
        'alt-riffraff.api_password' => '',
        'statamic.git.enabled' => false,
    ]);

    Cache::flush();
    Carbon::setTestNow('2026-11-03 10:00:00');
    Sleep::fake(syncWithCarbon: true);
    Process::fake(['*' => Process::result(exitCode: 1)]);
});

afterEach(function () {
    File::delete(resource_path('addons/alt-riffraff.yaml'));
    Carbon::setTestNow();
});

/**
 * @return array<string, mixed>
 */
function codeReply(array $overrides = []): array
{
    return [
        'device_code' => DEVICE_CODE,
        'user_code' => 'ABCD-EFGH',
        'verification_uri' => 'https://riff-raff.test/connect',
        'expires_in' => 900,
        'interval' => 5,
        ...$overrides,
    ];
}

function approvedReply(string $site = 'Acme website'): array
{
    return ['access_token' => ISSUED_KEY, 'token_type' => 'Bearer', 'site' => $site];
}

function pollError(string $error, array $extra = []): array
{
    return ['error' => $error, ...$extra];
}

function connect(array $arguments = []): array
{
    $exitCode = Artisan::call('statamic:riffraff:connect', $arguments);

    return [$exitCode, Artisan::output()];
}

function saveKeyInSettings(string $key): void
{
    Addon::get('alt-design/alt-riffraff')->settings()->set(['api_key' => $key])->save();
}

it('shows the code, waits for approval and saves the key', function () {
    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::sequence()
            ->push(pollError('authorization_pending'), 400)
            ->push(approvedReply(), 200),
    ]);

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('ABCD-EFGH')
        ->and($output)->toContain('https://riff-raff.test/connect')
        ->and($output)->toContain('Connected as Acme website.')
        ->and(RiffRaff::apiKey())->toBe(ISSUED_KEY)
        ->and(PendingConnection::current())->toBeNull();

    Http::assertSent(fn (Request $request) => $request->url() === CODE_URL && $request['site_name'] === 'Acme website');
    Http::assertSent(fn (Request $request) => $request->url() === TOKEN_URL
        && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:device_code'
        && $request['device_code'] === DEVICE_CODE);
});

it('never prints the key or the device code', function () {
    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::response(approvedReply()),
    ]);

    [, $output] = connect(['name' => 'Acme website']);

    expect($output)->not->toContain(ISSUED_KEY)
        ->and($output)->not->toContain('issued-key')
        ->and($output)->not->toContain(DEVICE_CODE);
});

it('keeps any other addon settings when it saves the key', function () {
    Addon::get('alt-design/alt-riffraff')->settings()->set(['other_setting' => 'kept'])->save();

    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::response(approvedReply()),
    ]);

    connect(['name' => 'Acme website']);

    expect(Addon::get('alt-design/alt-riffraff')->settings()->get('other_setting'))->toBe('kept')
        ->and(RiffRaff::apiKey())->toBe(ISSUED_KEY);
});

it('reports the site name RiffRaff gives back', function () {
    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::response(approvedReply('Acme website (staging)')),
    ]);

    [, $output] = connect(['name' => 'Acme website']);

    expect($output)->toContain('Connected as Acme website (staging).');
});

it('waits the interval RiffRaff asks for between polls', function () {
    Http::fake([
        CODE_URL => Http::response(codeReply(['interval' => 7])),
        TOKEN_URL => Http::sequence()
            ->push(pollError('authorization_pending'), 400)
            ->push(approvedReply(), 200),
    ]);

    connect(['name' => 'Acme website']);

    Sleep::assertSequence([Sleep::for(7)->seconds(), Sleep::for(7)->seconds()]);
});

it('slows down when RiffRaff says it is polling too often', function () {
    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::sequence()
            ->push(pollError('slow_down', ['interval' => 10]), 400)
            ->push(pollError('slow_down'), 400)
            ->push(approvedReply(), 200),
    ]);

    [$exitCode] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(0);
    Sleep::assertSequence([Sleep::for(5)->seconds(), Sleep::for(10)->seconds(), Sleep::for(15)->seconds()]);
});

it('stops when the request is denied', function () {
    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::response(pollError('access_denied'), 400),
    ]);

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('The request was denied in RiffRaff.')
        ->and(RiffRaff::apiKey())->toBeNull()
        ->and(PendingConnection::current())->toBeNull();
});

it('stops when RiffRaff says the code has expired', function () {
    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::response(pollError('expired_token'), 400),
    ]);

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('The code expired before it was approved.')
        ->and(PendingConnection::current())->toBeNull();
});

it('stops when RiffRaff no longer recognises the request', function () {
    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::response(pollError('invalid_grant'), 400),
    ]);

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('RiffRaff no longer recognises this request.')
        ->and(PendingConnection::current())->toBeNull();
});

it('gives up once the code expires without an answer', function () {
    Http::fake([
        CODE_URL => Http::response(codeReply(['expires_in' => 30])),
        TOKEN_URL => Http::response(pollError('authorization_pending'), 400),
    ]);

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('The code expired before it was approved.')
        ->and(PendingConnection::current())->toBeNull();
    Sleep::assertSleptTimes(6);
});

it('keeps trying when RiffRaff cannot be reached for a moment', function () {
    $polls = 0;

    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => function () use (&$polls) {
            $polls++;

            return match ($polls) {
                1 => Http::failedConnection(),
                2 => Http::response('', 503),
                3 => Http::response(['unexpected' => true], 200),
                default => Http::response(approvedReply(), 200),
            };
        },
    ]);

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(0)
        ->and(substr_count($output, 'could not be reached'))->toBe(1)
        ->and(RiffRaff::apiKey())->toBe(ISSUED_KEY);
});

it('prints the code and stops straight away with --wait=0, keeping the request for the next run', function () {
    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::response(approvedReply()),
    ]);

    [$exitCode, $output] = connect(['name' => 'Acme website', '--wait' => '0']);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('ABCD-EFGH')
        ->and($output)->toContain('Not approved yet. Once it is, run php please riffraff:connect again')
        ->and(PendingConnection::current()?->siteName)->toBe('Acme website')
        ->and(RiffRaff::apiKey())->toBeNull();
    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
});

it('picks up the waiting request on the next run instead of asking for a new code', function () {
    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::response(approvedReply()),
    ]);

    connect(['name' => 'Acme website', '--wait' => '0']);
    Carbon::setTestNow(Carbon::now()->addMinutes(2));

    [$exitCode, $output] = connect(['--wait' => '0']);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Picking up the request already waiting for approval for "Acme website"')
        ->and($output)->toContain('ABCD-EFGH')
        ->and($output)->toContain('Connected as Acme website.')
        ->and(RiffRaff::apiKey())->toBe(ISSUED_KEY);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => $request->url() === TOKEN_URL);
});

it('picks up the waiting request when run again with the same name', function () {
    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::response(approvedReply()),
    ]);

    connect(['name' => 'Acme website', '--wait' => '0']);
    connect(['name' => 'Acme website', '--wait' => '0']);

    Http::assertSentCount(2);
    expect(RiffRaff::apiKey())->toBe(ISSUED_KEY);
});

it('asks for a new code when run again with a different name', function () {
    Http::fake([
        CODE_URL => Http::sequence()
            ->push(codeReply(), 200)
            ->push(codeReply(['device_code' => 'second-device-code', 'user_code' => 'WXYZ-2345']), 200),
        TOKEN_URL => Http::response(pollError('authorization_pending'), 400),
    ]);

    connect(['name' => 'Acme website', '--wait' => '0']);
    [, $output] = connect(['name' => 'Acme shop', '--wait' => '0']);

    expect($output)->toContain('WXYZ-2345')
        ->and(PendingConnection::current()?->siteName)->toBe('Acme shop');
});

it('asks for a new code once the waiting request has expired', function () {
    Http::fake([
        CODE_URL => Http::sequence()
            ->push(codeReply(), 200)
            ->push(codeReply(['user_code' => 'WXYZ-2345']), 200),
    ]);

    connect(['name' => 'Acme website', '--wait' => '0']);
    Carbon::setTestNow(Carbon::now()->addMinutes(16));

    [, $output] = connect(['name' => 'Acme website', '--wait' => '0']);

    expect($output)->toContain('WXYZ-2345');
});

it('stops after --wait seconds and says how to finish', function () {
    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::response(pollError('authorization_pending'), 400),
    ]);

    [$exitCode, $output] = connect(['name' => 'Acme website', '--wait' => '12']);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Not approved yet.')
        ->and(PendingConnection::current())->not->toBeNull();
    Sleep::assertSleptTimes(2);
});

it('needs a name when nothing is waiting', function () {
    Http::fake();

    [$exitCode, $output] = connect();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Give the site a name');
    Http::assertNothingSent();
});

it('rejects a site name RiffRaff would refuse', function (string $name) {
    Http::fake();

    [$exitCode, $output] = connect(['name' => $name]);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('at most 100 characters, with no control characters');
    Http::assertNothingSent();
})->with([
    'too long' => str_repeat('a', 101),
    'control character' => "Acme\x07website",
]);

it('rejects a --wait that is not a number of seconds', function (string $wait) {
    Http::fake();

    [$exitCode, $output] = connect(['name' => 'Acme website', '--wait' => $wait]);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('--wait takes a number of seconds');
    Http::assertNothingSent();
})->with(['negative' => '-5', 'words' => 'soon', 'decimal' => '1.5']);

it('explains when RiffRaff refuses the request for a code', function (int $status, array $body, string $message) {
    Http::fake([CODE_URL => Http::response($body, $status)]);

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain($message)
        ->and(PendingConnection::current())->toBeNull();
})->with([
    'too many waiting' => [429, ['error' => 'slow_down'], 'Too many requests from this server are already waiting'],
    'invalid request' => [400, ['error' => 'invalid_request', 'error_description' => 'site_name is required.'], 'RiffRaff refused the request: site_name is required.'],
    'server error' => [500, [], 'RiffRaff refused the request: HTTP status 500.'],
    'unexpected reply' => [200, ['user_code' => 'ABCD-EFGH'], 'does not understand'],
]);

it('explains when RiffRaff cannot be reached for a code', function () {
    Http::fake([CODE_URL => Http::failedConnection()]);

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Could not reach RiffRaff at https://api.riff-raff.test');
});

it('uses the default paths when the published config file predates them', function () {
    $config = config('alt-riffraff');
    unset($config['api_device_code_path'], $config['api_device_token_path']);
    config(['alt-riffraff' => $config]);

    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::response(approvedReply()),
    ]);

    [$exitCode] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(0);
    Http::assertSent(fn (Request $request) => $request->url() === CODE_URL);
    Http::assertSent(fn (Request $request) => $request->url() === TOKEN_URL);
});

it('refuses when a key is already saved, unless forced', function () {
    saveKeyInSettings('7|existing-saved-key');
    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::response(approvedReply()),
    ]);

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('A key is already saved in the addon settings.')
        ->and($output)->toContain('Nothing was requested from RiffRaff.')
        ->and($output)->not->toContain('existing-saved-key');
    Http::assertNothingSent();

    [$exitCode, $output] = connect(['name' => 'Acme website', '--force' => true]);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Going ahead because of --force')
        ->and(RiffRaff::apiKey())->toBe(ISSUED_KEY);
});

it('refuses when ALT_RIFFRAFF_API_KEY is set', function () {
    config(['alt-riffraff.api_key' => '3|environment-key']);
    Http::fake();

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('ALT_RIFFRAFF_API_KEY is set in the environment')
        ->and($output)->not->toContain('environment-key');
    Http::assertNothingSent();
});

it("refuses when Statamic's git automation would commit the settings file", function () {
    config([
        'statamic.git.enabled' => true,
        'statamic.git.paths' => [base_path('content'), resource_path('addons')],
    ]);
    Http::fake();

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain("Statamic's git automation is on and covers resources/addons/alt-riffraff.yaml");
    Http::assertNothingSent();
});

it('refuses when git automation tracks a folder above the settings file', function () {
    config([
        'statamic.git.enabled' => true,
        'statamic.git.paths' => [resource_path()],
    ]);
    Http::fake();

    [$exitCode] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1);
});

it('does not mind git automation that leaves the settings file alone', function (bool $enabled, array $paths) {
    config([
        'statamic.git.enabled' => $enabled,
        'statamic.git.paths' => array_map(fn (string $path): string => base_path($path), $paths),
    ]);
    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::response(approvedReply()),
    ]);

    [$exitCode] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(0);
})->with([
    'off' => [false, ['resources/addons']],
    'other paths only' => [true, ['content', 'resources/blueprints']],
    'a sibling with a similar name' => [true, ['resources/addons-extra']],
]);

it('refuses when the settings file is tracked in git', function () {
    Process::fake(['*' => Process::result(exitCode: 0)]);
    Http::fake();

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain("resources/addons/alt-riffraff.yaml is tracked in this site's git repository");
    Process::assertRan(fn ($process) => $process->command === ['git', 'ls-files', '--error-unmatch', '--', 'resources/addons/alt-riffraff.yaml']);
    Http::assertNothingSent();
});

it('goes ahead when git cannot be run', function () {
    Process::fake(fn () => throw new RuntimeException('git: command not found'));
    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::response(approvedReply()),
    ]);

    [$exitCode] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(0);
});

it('refuses on a zero downtime release where resources/addons is not shared', function () {
    $site = zeroDowntimeSite(shareAddons: false);

    try {
        Http::fake();

        [$exitCode, $output] = connect(['name' => 'Acme website']);

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('This site deploys with zero downtime')
            ->and($output)->toContain('shared paths in Forge');
        Http::assertNothingSent();
    } finally {
        leaveZeroDowntimeSite($site);
    }
});

it('goes ahead on a zero downtime release where resources/addons is a shared path', function () {
    $site = zeroDowntimeSite(shareAddons: true);

    try {
        expect(app(KeyStorage::class)->problems())->toBe([]);
    } finally {
        leaveZeroDowntimeSite($site);
    }
});

it('does not treat a folder called releases without a current link as zero downtime', function () {
    $site = zeroDowntimeSite(shareAddons: false, linkCurrent: false);

    try {
        expect(app(KeyStorage::class)->problems())->toBe([]);
    } finally {
        leaveZeroDowntimeSite($site);
    }
});

it('says the key could not be saved without printing it', function () {
    $storage = Mockery::mock(KeyStorage::class);
    $storage->shouldReceive('canSave')->andReturnTrue();
    $storage->shouldReceive('problems')->andReturn([]);
    $storage->shouldReceive('save')->andThrow(new RuntimeException('Could not write ' . ISSUED_KEY));
    app()->instance(KeyStorage::class, $storage);

    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::response(approvedReply()),
    ]);

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('the key could not be saved (RuntimeException)')
        ->and($output)->not->toContain('issued-key');
});

it('points at the environment variable where addon settings do not exist', function () {
    $storage = Mockery::mock(KeyStorage::class);
    $storage->shouldReceive('canSave')->andReturnFalse();
    app()->instance(KeyStorage::class, $storage);
    Http::fake();

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Set ALT_RIFFRAFF_API_KEY in the environment instead.');
    Http::assertNothingSent();
});

/**
 * Builds /site/releases/1 with /site/current pointing at it, and runs the app
 * from the release, as a Forge zero downtime deploy does.
 *
 * @return array{root: string, original: string}
 */
function zeroDowntimeSite(bool $shareAddons, bool $linkCurrent = true): array
{
    $root = sys_get_temp_dir() . '/riffraff-zero-downtime-' . bin2hex(random_bytes(4));
    $release = $root . '/releases/1';

    File::ensureDirectoryExists($release . '/resources');

    if ($linkCurrent) {
        symlink($release, $root . '/current');
    }

    if ($shareAddons) {
        File::ensureDirectoryExists($root . '/resources/addons');
        symlink($root . '/resources/addons', $release . '/resources/addons');
    }

    $original = base_path();
    app()->setBasePath($release);

    return ['root' => $root, 'original' => $original];
}

/**
 * @param  array{root: string, original: string}  $site
 */
function leaveZeroDowntimeSite(array $site): void
{
    app()->setBasePath($site['original']);
    File::deleteDirectory($site['root']);
}

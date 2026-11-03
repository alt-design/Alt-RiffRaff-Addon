<?php

declare(strict_types=1);

use AltDesign\RiffRaff\Connect\KeyStorage;
use AltDesign\RiffRaff\Connect\PendingConnection;
use AltDesign\RiffRaff\Support\RiffRaff;
use Illuminate\Contracts\Cache\Store;
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
        'cache.default' => 'file',
    ]);

    Cache::flush();
    Carbon::setTestNow('2026-11-03 10:00:00');
    Sleep::fake(syncWithCarbon: true);
    fakeGit();
});

afterEach(function () {
    Cache::flush();
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

function fakeGit(bool $inRepository = true, bool $tracked = false, bool $ignored = true, ?string $failure = null): void
{
    Process::fake([
        '*rev-parse*' => match (true) {
            $failure !== null => Process::result(errorOutput: $failure, exitCode: 128),
            $inRepository => Process::result(output: "true\n"),
            default => Process::result(errorOutput: 'fatal: not a git repository (or any of the parent directories): .git', exitCode: 128),
        },
        '*ls-files*' => Process::result(exitCode: $tracked ? 0 : 1),
        '*check-ignore*' => Process::result(exitCode: $ignored ? 0 : 1),
    ]);
}

function connectsCleanly(): void
{
    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::response(approvedReply()),
    ]);
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
    connectsCleanly();

    [, $output] = connect(['name' => 'Acme website']);

    expect($output)->not->toContain(ISSUED_KEY)
        ->and($output)->not->toContain('issued-key')
        ->and($output)->not->toContain(DEVICE_CODE);
});

it('keeps the device code out of the cache in readable form', function () {
    Http::fake([CODE_URL => Http::response(codeReply())]);

    connect(['name' => 'Acme website', '--wait' => '0']);

    $files = File::allFiles(storage_path('framework/cache/data'));
    $contents = collect($files)->map(fn ($file) => File::get($file->getPathname()))->implode('');

    expect($files)->not->toBeEmpty()
        ->and($contents)->not->toContain(DEVICE_CODE)
        ->and(PendingConnection::current()?->deviceCode)->toBe(DEVICE_CODE);
});

it('keeps any other addon settings when it saves the key', function () {
    Addon::get('alt-design/alt-riffraff')->settings()->set(['other_setting' => 'kept'])->save();
    connectsCleanly();

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

it('prints text from RiffRaff without letting it format the console', function () {
    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::response(approvedReply("<href=https://evil.example>Acme</>\e[2J")),
    ]);

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('<href=https://evil.example>Acme')
        ->and($output)->not->toContain("\e[2J");
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

it('backs off when the token endpoint rate limits it', function () {
    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::sequence()
            ->push(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '20'])
            ->push(['message' => 'Too Many Attempts.'], 429)
            ->push(approvedReply(), 200),
    ]);

    [$exitCode] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(0);
    Sleep::assertSequence([Sleep::for(5)->seconds(), Sleep::for(20)->seconds(), Sleep::for(25)->seconds()]);
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

it('stops with the reason when the token endpoint refuses outright', function (int $status, array $body, string $reason) {
    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::response($body, $status),
    ]);

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('RiffRaff refused to finish connecting: ' . $reason)
        ->and($output)->not->toContain('could not be reached');
    Http::assertSentCount(2);
})->with([
    'not found' => [404, [], 'HTTP status 404.'],
    'invalid request' => [400, ['error' => 'invalid_request'], 'invalid_request'],
    'described' => [400, ['error' => 'unsupported_grant_type', 'error_description' => 'That grant type is not supported.'], 'That grant type is not supported.'],
]);

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

it('does not call a code expired while it still has time left', function () {
    Http::fake([
        CODE_URL => Http::response(codeReply()),
        TOKEN_URL => Http::response(pollError('slow_down', ['interval' => 60]), 400),
    ]);

    [$exitCode, $output] = connect(['name' => 'Acme website', '--wait' => '30']);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Not approved yet.')
        ->and($output)->not->toContain('expired before')
        ->and(PendingConnection::current()?->interval)->toBe(60);
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
    connectsCleanly();

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
    connectsCleanly();

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
    connectsCleanly();

    connect(['name' => 'Acme website', '--wait' => '0']);
    connect(['name' => 'Acme website', '--wait' => '0']);

    Http::assertSentCount(2);
    expect(RiffRaff::apiKey())->toBe(ISSUED_KEY);
});

it('does not pick up a request made for another site sharing the cache', function () {
    Http::fake([CODE_URL => Http::response(codeReply())]);

    connect(['name' => 'Acme website', '--wait' => '0']);
    $original = base_path();
    app()->setBasePath(sys_get_temp_dir() . '/another-site');

    try {
        expect(PendingConnection::current())->toBeNull();
    } finally {
        app()->setBasePath($original);
    }

    expect(PendingConnection::current())->not->toBeNull();
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

it('says so when the cache cannot keep the request for the next run', function () {
    config(['cache.default' => 'array']);
    Http::fake([CODE_URL => Http::response(codeReply())]);

    [$exitCode, $output] = connect(['name' => 'Acme website', '--wait' => '0']);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain("This site's cache cannot keep the request until the next run")
        ->and($output)->toContain('Run it without --wait=0');
});

it('neither crashes nor prints a secret when the cache store fails', function () {
    Cache::extend('exploding', fn () => Cache::repository(new ExplodingStore));
    config(['cache.stores.exploding' => ['driver' => 'exploding'], 'cache.default' => 'exploding']);
    connectsCleanly();

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain("This site's cache cannot keep the request")
        ->and($output)->toContain('the key could not be saved (RuntimeException)')
        ->and($output)->not->toContain(DEVICE_CODE)
        ->and($output)->not->toContain('issued-key');
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

it('refuses a RiffRaff address that is not HTTPS', function () {
    config(['alt-riffraff.api_base_url' => 'http://api.riff-raff.example']);
    Http::fake();

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('ALT_RIFFRAFF_BASE_URL must start with https://');
    Http::assertNothingSent();
});

it('allows plain HTTP for a RiffRaff on this machine', function () {
    config(['alt-riffraff.api_base_url' => 'http://riff-raff.test']);
    Http::fake([
        'http://riff-raff.test/api/device/code' => Http::response(codeReply(['verification_uri' => 'http://riff-raff.test/connect'])),
        'http://riff-raff.test/api/device/token' => Http::response(approvedReply()),
    ]);

    [$exitCode] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(0);
});

it('refuses a code reply that points somewhere other than RiffRaff', function (array $overrides) {
    Http::fake([CODE_URL => Http::response(codeReply($overrides))]);

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('does not understand')
        ->and($output)->not->toContain('evil.example')
        ->and(PendingConnection::current())->toBeNull();
})->with([
    'another domain' => [['verification_uri' => 'https://evil.example/connect']],
    'a lookalike domain' => [['verification_uri' => 'https://riff-raff.test.evil.example/connect']],
    'plain http' => [['verification_uri' => 'http://riff-raff.test/connect']],
    'console markup in the link' => [['verification_uri' => 'https://riff-raff.test/<href=https://evil.example>x</>']],
    'console markup in the code' => [['user_code' => '<href=https://evil.example>ABCD</>']],
    'no link' => [['verification_uri' => '']],
    'expiry as text' => [['expires_in' => '900']],
]);

it('explains when RiffRaff refuses the request for a code', function (int $status, array $body, string $message) {
    Http::fake([CODE_URL => Http::response($body, $status)]);

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain($message)
        ->and(PendingConnection::current())->toBeNull();
})->with([
    'too many waiting' => [429, ['error' => 'slow_down'], 'Too many requests from this server are already waiting'],
    'rate limited' => [429, ['message' => 'Too Many Attempts.'], 'RiffRaff is limiting requests from this server.'],
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
    connectsCleanly();

    [$exitCode] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(0);
    Http::assertSent(fn (Request $request) => $request->url() === CODE_URL);
    Http::assertSent(fn (Request $request) => $request->url() === TOKEN_URL);
});

it('refuses when a key is already saved, unless asked to replace it', function (array $flag) {
    saveKeyInSettings('7|existing-saved-key');
    connectsCleanly();

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('A key is already saved in the addon settings. Run with --replace to replace it.')
        ->and($output)->toContain('Nothing was requested from RiffRaff.')
        ->and($output)->not->toContain('existing-saved-key');
    Http::assertNothingSent();

    [$exitCode] = connect(['name' => 'Acme website', ...$flag]);

    expect($exitCode)->toBe(0)
        ->and(RiffRaff::apiKey())->toBe(ISSUED_KEY);
})->with([
    '--replace' => [['--replace' => true]],
    '--force' => [['--force' => true]],
]);

it('does not let --replace past a check that protects the key', function () {
    saveKeyInSettings('7|existing-saved-key');
    fakeGit(tracked: true);
    Http::fake();

    [$exitCode, $output] = connect(['name' => 'Acme website', '--replace' => true]);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('is tracked in this site\'s git repository');
    Http::assertNothingSent();
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

it("refuses when Statamic's git automation would commit the settings file", function (string $path) {
    config(['statamic.git.enabled' => true, 'statamic.git.paths' => [base_path('content'), $path === '' ? resource_path() : $path]]);
    fakeGit(ignored: false);
    Http::fake();

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain("Statamic's git automation covers resources/addons/alt-riffraff.yaml and the file is not in .gitignore");
    Http::assertNothingSent();
})->with([
    'the addons folder' => fn () => resource_path('addons'),
    'a folder above it' => '',
    'a relative path' => 'resources/addons',
    'a path with dots in it' => fn () => resource_path('blueprints/../addons'),
]);

it('lets git automation be when the settings file is ignored', function () {
    config(['statamic.git.enabled' => true, 'statamic.git.paths' => [resource_path('addons')]]);
    fakeGit(ignored: true);
    connectsCleanly();

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(0)
        ->and($output)->not->toContain('.gitignore');
});

it('warns, but goes ahead, when the file is not ignored and nothing commits automatically', function (bool $enabled, array $paths) {
    config([
        'statamic.git.enabled' => $enabled,
        'statamic.git.paths' => array_map(fn (string $path): string => base_path($path), $paths),
    ]);
    fakeGit(ignored: false);
    connectsCleanly();

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain("resources/addons/alt-riffraff.yaml is not in the site's .gitignore")
        ->and(RiffRaff::apiKey())->toBe(ISSUED_KEY);
})->with([
    'automation off' => [false, ['resources/addons']],
    'other paths only' => [true, ['content', 'resources/blueprints']],
    'a sibling with a similar name' => [true, ['resources/addons-extra']],
]);

it('refuses when the settings file is tracked in git', function () {
    fakeGit(tracked: true);
    Http::fake();

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain("resources/addons/alt-riffraff.yaml is tracked in this site's git repository");
    Process::assertRan(fn ($process) => $process->command === ['git', 'ls-files', '--error-unmatch', '--', 'resources/addons/alt-riffraff.yaml']);
    Http::assertNothingSent();
});

it('goes ahead when the site is not a git repository', function () {
    fakeGit(inRepository: false);
    connectsCleanly();

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(0)
        ->and($output)->not->toContain('git');
});

it('refuses when git cannot say whether the file would be committed, unless forced', function () {
    fakeGit(failure: "fatal: detected dubious ownership in repository at '/home/forge/site'");
    connectsCleanly();

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Could not check with git whether resources/addons/alt-riffraff.yaml would be committed')
        ->and($output)->toContain('detected dubious ownership');

    [$exitCode] = connect(['name' => 'Acme website', '--force' => true]);

    expect($exitCode)->toBe(0);
});

it('refuses when git cannot be run at all', function () {
    Process::fake(fn () => throw new RuntimeException('git: command not found'));
    Http::fake();

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('git could not be run');
});

it('refuses on a zero downtime release where resources/addons is not shared', function () {
    $site = zeroDowntimeSite(shareAddons: false);

    try {
        Http::fake();

        [$exitCode, $output] = connect(['name' => 'Acme website']);

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('This site deploys with zero downtime')
            ->and($output)->toContain('shared path in Forge');
        Http::assertNothingSent();
    } finally {
        leaveZeroDowntimeSite($site);
    }
});

it('goes ahead on a zero downtime release where resources/addons is a shared path', function () {
    $site = zeroDowntimeSite(shareAddons: true);

    try {
        expect(app(KeyStorage::class)->assess()['problems'])->toBe([]);
    } finally {
        leaveZeroDowntimeSite($site);
    }
});

it('does not treat a folder called releases without a current link as zero downtime', function () {
    $site = zeroDowntimeSite(shareAddons: false, linkCurrent: false);

    try {
        expect(app(KeyStorage::class)->assess()['problems'])->toBe([]);
    } finally {
        leaveZeroDowntimeSite($site);
    }
});

it('says the key could not be saved without printing it', function () {
    useStorage(save: fn () => throw new RuntimeException('Could not write ' . ISSUED_KEY));
    connectsCleanly();

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('the key could not be saved (RuntimeException)')
        ->and($output)->toContain('revoke the unused one')
        ->and($output)->not->toContain('issued-key');
});

it('says when something on the site stops the settings being saved', function () {
    useStorage(save: fn () => false);
    connectsCleanly();

    [$exitCode, $output] = connect(['name' => 'Acme website']);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('something on this site stopped the addon settings being saved')
        ->and($output)->not->toContain('Connected as');
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

function useStorage(Closure $save): void
{
    $storage = Mockery::mock(KeyStorage::class);
    $storage->shouldReceive('canSave')->andReturnTrue();
    $storage->shouldReceive('hasSavedKey')->andReturnFalse();
    $storage->shouldReceive('assess')->andReturn(['problems' => [], 'warnings' => []]);
    $storage->shouldReceive('save')->andReturnUsing($save);
    app()->instance(KeyStorage::class, $storage);
}

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

class ExplodingStore implements Store
{
    public function get($key)
    {
        throw new RuntimeException('Cache is down');
    }

    public function many(array $keys)
    {
        throw new RuntimeException('Cache is down');
    }

    public function put($key, $value, $seconds)
    {
        throw new RuntimeException('Cache is down');
    }

    public function putMany(array $values, $seconds)
    {
        throw new RuntimeException('Cache is down');
    }

    public function increment($key, $value = 1)
    {
        throw new RuntimeException('Cache is down');
    }

    public function decrement($key, $value = 1)
    {
        throw new RuntimeException('Cache is down');
    }

    public function forever($key, $value)
    {
        throw new RuntimeException('Cache is down');
    }

    public function forget($key)
    {
        throw new RuntimeException('Cache is down');
    }

    public function flush()
    {
        return true;
    }

    public function getPrefix()
    {
        return '';
    }
}

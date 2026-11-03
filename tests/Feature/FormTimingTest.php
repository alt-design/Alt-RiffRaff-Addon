<?php

declare(strict_types=1);

use AltDesign\RiffRaff\Listeners\FormSubmittedListener;
use AltDesign\RiffRaff\Support\FormTiming;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Statamic\Contracts\Forms\Form as FormContract;
use Statamic\Contracts\Forms\Submission as SubmissionContract;
use Statamic\Events\FormSubmitted;
use Statamic\Facades\Antlers;
use Statamic\Facades\Form;
use Statamic\Fields\Blueprint;
use Statamic\Tags\Loader;

beforeEach(function () {
    config([
        'app.key' => 'base64:' . base64_encode(str_repeat('k', 32)),
        'alt-riffraff.api_base_url' => 'https://api.riff-raff.test',
        'alt-riffraff.api_evaluate_path' => '/api/evaluate',
        'alt-riffraff.api_key' => '17|abc123',
        'alt-riffraff.duplicate_window' => 0,
        'alt-riffraff.timing_max_age' => 3600,
    ]);
});

afterEach(function () {
    $this->travelBack();
    Mockery::close();
});

function loadedToken(string $formHandle = 'contact'): string
{
    preg_match('/value="([^"]+)"/', FormTiming::field($formHandle), $matches);

    return $matches[1];
}

it('renders a hidden input carrying a signed timestamp', function () {
    $field = FormTiming::field('contact');

    expect($field)
        ->toStartWith('<input type="hidden" name="_riffraff_loaded" value="')
        ->and(loadedToken())->toMatch('/^\d+\.[a-f0-9]{64}$/');
});

it('renders nothing when the application has no key', function () {
    config(['app.key' => '']);

    expect(FormTiming::field('contact'))->toBe('');
});

it('reports the seconds since the form loaded', function () {
    $this->freezeTime();
    $token = loadedToken();

    $this->travel(7)->seconds();

    expect(FormTiming::secondsSinceLoad($token, 'contact'))->toBe(7);
});

it('reports zero for an instant submission', function () {
    $this->freezeTime();

    expect(FormTiming::secondsSinceLoad(loadedToken(), 'contact'))->toBe(0);
});

it('returns null for a value that was not signed by this site', function (mixed $value) {
    expect(FormTiming::secondsSinceLoad($value, 'contact'))->toBeNull();
})->with([
    'missing' => [null],
    'empty' => [''],
    'an array' => [['1.2']],
    'a bare timestamp' => ['1700000000'],
    'a forged signature' => ['1700000000.' . str_repeat('a', 64)],
]);

it('returns null when the timestamp has been edited', function () {
    $this->freezeTime();
    [, $signature] = explode('.', loadedToken());

    $edited = (now()->getTimestamp() - 100) . '.' . $signature;

    expect(FormTiming::secondsSinceLoad($edited, 'contact'))->toBeNull();
});

it('returns null when the token came from another form', function () {
    expect(FormTiming::secondsSinceLoad(loadedToken('newsletter'), 'contact'))->toBeNull();
});

it('returns null when the token was signed with another key', function () {
    $token = loadedToken();

    config(['app.key' => 'base64:' . base64_encode(str_repeat('z', 32))]);

    expect(FormTiming::secondsSinceLoad($token, 'contact'))->toBeNull();
});

it('returns null for a timestamp in the future', function () {
    $token = loadedToken();

    $this->travel(-30)->seconds();

    expect(FormTiming::secondsSinceLoad($token, 'contact'))->toBeNull();
});

it('returns null once the token is older than the maximum age, as on a cached page', function () {
    $token = loadedToken();

    $this->travel(3601)->seconds();

    expect(FormTiming::secondsSinceLoad($token, 'contact'))->toBeNull();
});

it('honours the configured maximum age', function () {
    config(['alt-riffraff.timing_max_age' => 60]);
    $token = loadedToken();

    $this->travel(61)->seconds();

    expect(FormTiming::secondsSinceLoad($token, 'contact'))->toBeNull();
});

function timedSubmission(): FormSubmitted
{
    $form = Mockery::mock(FormContract::class);
    $form->shouldReceive('handle')->andReturn('contact');
    $form->shouldReceive('blueprint')->andReturn(
        (new Blueprint)->setHandle('contact')->setNamespace('forms')->setContents(['fields' => []]),
    );
    $form->shouldReceive('honeypot')->andReturn('honeypot');

    $submission = Mockery::mock(SubmissionContract::class);
    $submission->shouldReceive('id')->andReturn('timed-submission');
    $submission->shouldReceive('form')->andReturn($form);
    $submission->shouldReceive('data')->andReturn(collect(['message' => 'Hello there']));

    return new FormSubmitted($submission);
}

function fakeCleanVerdict(): void
{
    Http::fake([
        'https://api.riff-raff.test/api/evaluate' => Http::response([
            'is_spam' => false,
            'score' => 0,
            'threshold' => 100,
            'reasons' => [],
        ], 200),
    ]);
}

it('sends the seconds to submit when the form carried a valid timestamp', function () {
    fakeCleanVerdict();
    $this->freezeTime();
    request()->merge([FormTiming::FIELD => loadedToken()]);
    $this->travel(1)->seconds();

    (new FormSubmittedListener)->handle(timedSubmission());

    Http::assertSent(fn ($request) => $request['seconds_to_submit'] === 1);
});

it('sends no timing and no honeypot field when there is no valid timestamp', function (mixed $value) {
    fakeCleanVerdict();
    request()->merge([FormTiming::FIELD => $value]);

    (new FormSubmittedListener)->handle(timedSubmission());

    Http::assertSent(fn ($request) => ! isset($request['seconds_to_submit']) && ! isset($request['honeypot_filled']));
})->with([
    'absent' => [null],
    'tampered' => ['1700000000.' . str_repeat('b', 64)],
]);

it('renders nothing when RiffRaff has no credentials, without making a request', function () {
    Http::fake();
    config([
        'alt-riffraff.api_key' => '',
        'alt-riffraff.api_email' => '',
        'alt-riffraff.api_password' => '',
    ]);

    expect(FormTiming::field('contact'))->toBe('');
    Http::assertNothingSent();
});

it('renders the field when login credentials are configured, without making a request', function () {
    Http::fake();
    config([
        'alt-riffraff.api_key' => '',
        'alt-riffraff.api_email' => 'site@example.com',
        'alt-riffraff.api_password' => 'secret',
    ]);

    expect(FormTiming::field('contact'))->toContain('name="_riffraff_loaded"');
    Http::assertNothingSent();
});

it('sends no timing when the submission is not an HTTP request carrying the field', function () {
    fakeCleanVerdict();
    $this->app->instance('request', Request::create('/', 'GET'));

    (new FormSubmittedListener)->handle(timedSubmission());

    Http::assertSent(fn ($request) => ! isset($request['seconds_to_submit']));
});

it('sends no timing when the token was rendered for a different form handle', function () {
    fakeCleanVerdict();
    request()->merge([FormTiming::FIELD => loadedToken('newsletter')]);

    (new FormSubmittedListener)->handle(timedSubmission());

    Http::assertSent(fn ($request) => ! isset($request['seconds_to_submit']));
});

it('adds the timestamp field to a form rendered with the form tag', function () {
    $form = Form::make('contact')->title('Contact');
    $form->save();

    $html = (string) app(Loader::class)->load('form', [
        'parser' => Antlers::parser(),
        'params' => ['in' => 'contact'],
        'content' => '<p>Fields</p>',
        'context' => [],
        'tag' => 'form:create',
        'tag_method' => 'create',
    ])->create();

    expect($html)
        ->toContain('name="_riffraff_loaded"')
        ->and(strpos($html, '_riffraff_loaded'))->toBeLessThan(strpos($html, '</form>'));
});

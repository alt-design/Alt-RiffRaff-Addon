<?php

declare(strict_types=1);

use AltDesign\RiffRaff\Listeners\FormSubmittedListener;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Statamic\Contracts\Forms\Form as FormContract;
use Statamic\Contracts\Forms\Submission as SubmissionContract;
use Statamic\Events\FormSubmitted;
use Statamic\Facades\YAML;
use Statamic\Fields\Blueprint;

beforeEach(function () {
    config([
        'alt-riffraff.api_base_url' => 'https://api.riff-raff.test',
        'alt-riffraff.api_evaluate_path' => '/api/evaluate',
        'alt-riffraff.api_key' => '17|abc123',
        'alt-riffraff.excluded_content_fields' => [
            'page_uri',
            'form_reference',
            'enquiry_source',
            'honeypot',
        ],
        'alt-riffraff.duplicate_window' => 60,
        'alt-riffraff.duplicate_window_except' => [],
    ]);

    if (File::exists(base_path('content/riffraff'))) {
        File::deleteDirectory(base_path('content/riffraff'));
    }
});

afterEach(function () {
    Mockery::close();

    if (File::exists(base_path('content/riffraff'))) {
        File::deleteDirectory(base_path('content/riffraff'));
    }
});

/**
 * @param  array<int, array<string, mixed>>  $blueprintFields
 * @param  array<string, mixed>  $data
 */
function makeFormSubmittedEvent(
    array $blueprintFields,
    array $data,
    string $formHandle = 'contact',
    string $submissionId = 'test-submission',
    string $honeypot = 'honeypot',
): FormSubmitted {
    $blueprint = (new Blueprint)
        ->setHandle($formHandle)
        ->setNamespace('forms')
        ->setContents(['fields' => $blueprintFields]);

    $form = Mockery::mock(FormContract::class);
    $form->shouldReceive('handle')->andReturn($formHandle);
    $form->shouldReceive('blueprint')->andReturn($blueprint);
    $form->shouldReceive('honeypot')->andReturn($honeypot);

    $submission = Mockery::mock(SubmissionContract::class);
    $submission->shouldReceive('id')->andReturn($submissionId);
    $submission->shouldReceive('form')->andReturn($form);
    $submission->shouldReceive('data')->andReturn(collect($data));

    return new FormSubmitted($submission);
}

function fakeEvaluateResponse(array $overrides = []): void
{
    Http::fake([
        'https://api.riff-raff.test/api/evaluate' => Http::response(array_merge([
            'is_spam' => false,
            'score' => 0,
            'threshold' => 100,
            'reasons' => [],
        ], $overrides), 200),
    ]);
}

/**
 * @param  array<string, mixed>  $data
 */
function submitForm(array $data = ['message' => 'Hello there'], string $formHandle = 'contact'): bool
{
    return (new FormSubmittedListener)->handle(makeFormSubmittedEvent(
        blueprintFields: [
            ['handle' => 'message', 'field' => ['type' => 'textarea']],
        ],
        data: $data,
        formHandle: $formHandle,
    ));
}

it('sends the value from a field configured as an email input', function () {
    fakeEvaluateResponse();

    $event = makeFormSubmittedEvent(
        blueprintFields: [
            ['handle' => 'name', 'field' => ['type' => 'text']],
            ['handle' => 'email_address', 'field' => ['type' => 'text', 'input_type' => 'email']],
            ['handle' => 'message', 'field' => ['type' => 'textarea']],
        ],
        data: [
            'name' => 'Jane Doe',
            'email_address' => 'jane@example.com',
            'message' => 'Hello there',
        ],
    );

    (new FormSubmittedListener)->handle($event);

    Http::assertSent(fn ($request) => $request['email'] === 'jane@example.com');
});

it('falls back to the first scalar value that validates as an email address', function () {
    fakeEvaluateResponse();

    $event = makeFormSubmittedEvent(
        blueprintFields: [
            ['handle' => 'name', 'field' => ['type' => 'text']],
            ['handle' => 'reply_to', 'field' => ['type' => 'text']],
            ['handle' => 'message', 'field' => ['type' => 'textarea']],
        ],
        data: [
            'name' => 'Jane Doe',
            'reply_to' => 'jane@example.com',
            'message' => 'Hello there',
        ],
    );

    (new FormSubmittedListener)->handle($event);

    Http::assertSent(fn ($request) => $request['email'] === 'jane@example.com');
});

it('sends no email key when no field looks like an email address', function () {
    fakeEvaluateResponse();

    $event = makeFormSubmittedEvent(
        blueprintFields: [
            ['handle' => 'name', 'field' => ['type' => 'text']],
            ['handle' => 'message', 'field' => ['type' => 'textarea']],
        ],
        data: [
            'name' => 'Jane Doe',
            'message' => 'Hello there',
        ],
    );

    (new FormSubmittedListener)->handle($event);

    Http::assertSent(fn ($request) => ! array_key_exists('email', $request->data()));
});

it('excludes configured metadata fields from the content sent to the api', function () {
    fakeEvaluateResponse();

    $event = makeFormSubmittedEvent(
        blueprintFields: [
            ['handle' => 'message', 'field' => ['type' => 'textarea']],
            ['handle' => 'page_uri', 'field' => ['type' => 'hidden']],
            ['handle' => 'form_reference', 'field' => ['type' => 'hidden']],
            ['handle' => 'enquiry_source', 'field' => ['type' => 'hidden']],
        ],
        data: [
            'message' => 'A genuine enquiry',
            'page_uri' => 'https://example.com/contact',
            'form_reference' => 'contact-form-1',
            'enquiry_source' => 'website',
            'honeypot' => '',
        ],
    );

    (new FormSubmittedListener)->handle($event);

    Http::assertSent(function ($request) {
        $content = $request['content'];

        return str_contains($content, 'A genuine enquiry')
            && ! str_contains($content, 'https://example.com/contact')
            && ! str_contains($content, 'contact-form-1')
            && ! str_contains($content, 'website');
    });
});

it('persists reasons alongside the spam score when a submission is held', function () {
    fakeEvaluateResponse([
        'is_spam' => true,
        'score' => 270,
        'threshold' => 100,
        'reasons' => [
            'Spam phrases detected: "viagra"',
            'Too many links detected: 5 links found (max allowed: 2)',
        ],
    ]);

    $event = makeFormSubmittedEvent(
        blueprintFields: [
            ['handle' => 'message', 'field' => ['type' => 'textarea']],
        ],
        data: ['message' => 'Buy viagra now'],
        submissionId: 'held-submission',
    );

    $result = (new FormSubmittedListener)->handle($event);

    expect($result)->toBeFalse();

    $stored = YAML::parse(File::get(base_path('content/riffraff/held-submission.yaml')));

    expect($stored['reasons'])->toBe([
        'Spam phrases detected: "viagra"',
        'Too many links detected: 5 links found (max allowed: 2)',
    ])->and($stored['spam_score'])->toBe(270);
});

it('stores is_spam using the api value when the score sits exactly on the threshold', function () {
    fakeEvaluateResponse([
        'is_spam' => true,
        'score' => 100,
        'threshold' => 100,
        'reasons' => ['Content too short: only 8 words (minimum 16 expected)'],
    ]);

    $event = makeFormSubmittedEvent(
        blueprintFields: [
            ['handle' => 'message', 'field' => ['type' => 'textarea']],
        ],
        data: ['message' => 'Too short'],
        submissionId: 'boundary-submission',
    );

    $result = (new FormSubmittedListener)->handle($event);

    expect($result)->toBeFalse();

    $stored = YAML::parse(File::get(base_path('content/riffraff/boundary-submission.yaml')));

    expect($stored['is_spam'])->toBeTrue();
});

it('lets a genuine submission through when dispatched to every registered listener', function () {
    fakeEvaluateResponse();

    $submission = makeFormSubmittedEvent(
        blueprintFields: [
            ['handle' => 'message', 'field' => ['type' => 'textarea']],
        ],
        data: ['message' => 'Hello there'],
    )->submission;

    expect(FormSubmitted::dispatch($submission))->not->toBeFalse();

    Http::assertSentCount(1);
});

it('drops an identical repeat to the same form for the length of the duplicate window', function () {
    fakeEvaluateResponse();

    expect(submitForm())->toBeTrue();

    $this->travel(59)->seconds();

    expect(submitForm())->toBeFalse();

    Http::assertSentCount(1);
});

it('lets an identical repeat through once the duplicate window has passed', function () {
    fakeEvaluateResponse();

    submitForm();

    $this->travel(61)->seconds();

    expect(submitForm())->toBeTrue();

    Http::assertSentCount(2);
});

it('lets a different submission to the same form through', function () {
    fakeEvaluateResponse();

    expect(submitForm(['message' => 'First enquiry']))->toBeTrue()
        ->and(submitForm(['message' => 'Second enquiry']))->toBeTrue();
});

it('lets the same submission to a different form through', function () {
    fakeEvaluateResponse();

    expect(submitForm(formHandle: 'contact'))->toBeTrue()
        ->and(submitForm(formHandle: 'newsletter'))->toBeTrue();
});

it('never drops a submission when the duplicate window is zero', function () {
    config(['alt-riffraff.duplicate_window' => 0]);
    fakeEvaluateResponse();

    expect(submitForm())->toBeTrue()
        ->and(submitForm())->toBeTrue();
});

it('never drops a repeat to a form listed in duplicate_window_except', function () {
    config(['alt-riffraff.duplicate_window_except' => ['delivery_lookup']]);
    fakeEvaluateResponse();

    expect(submitForm(formHandle: 'delivery_lookup'))->toBeTrue()
        ->and(submitForm(formHandle: 'delivery_lookup'))->toBeTrue();
});

it('drops an identical repeat even when no api credentials are configured', function () {
    config([
        'alt-riffraff.api_key' => '',
        'alt-riffraff.api_email' => '',
        'alt-riffraff.api_password' => '',
    ]);

    expect(submitForm())->toBeTrue()
        ->and(submitForm())->toBeFalse();
});

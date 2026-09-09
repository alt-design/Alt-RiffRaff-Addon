<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Form;
use Statamic\Facades\User;
use Statamic\Facades\YAML;

beforeEach(function () {
    $this->user = User::make()->makeSuper()->save();

    Form::make('contact')->title('Contact')->save();

    if (File::exists(base_path('content/riffraff'))) {
        File::deleteDirectory(base_path('content/riffraff'));
    }
});

afterEach(function () {
    if (File::exists(base_path('content/riffraff'))) {
        File::deleteDirectory(base_path('content/riffraff'));
    }
});

/**
 * @param  array<string, mixed>  $overrides
 */
function storeHeldSubmission(string $id, array $overrides = []): void
{
    File::ensureDirectoryExists(base_path('content/riffraff'));

    File::put(
        base_path('content/riffraff/' . $id . '.yaml'),
        YAML::dump(array_merge([
            'id' => $id,
            'data' => ['message' => 'Buy cheap watches now'],
            'spam_score' => 270,
            'threshold' => 100,
            'reasons' => ['Spam phrases detected: "watches"'],
            'form_slug' => 'contact',
            'is_spam' => true,
            'flagged_at' => now()->toIso8601String(),
        ], $overrides)),
    );
}

it('shows an empty state when nothing is held', function () {
    $this->actingAs($this->user)
        ->get(cp_route('riffraff.index'))
        ->assertOk()
        ->assertSee('Nothing held for review');
});

it('lists held submissions with the score against the threshold', function () {
    storeHeldSubmission('held-one');

    $this->actingAs($this->user)
        ->get(cp_route('riffraff.index'))
        ->assertOk()
        ->assertSee('270 / 100')
        ->assertSee('170 over the threshold');
});

it('filters the list by search term', function () {
    storeHeldSubmission('matching', ['data' => ['message' => 'Buy cheap watches now']]);
    storeHeldSubmission('not-matching', ['data' => ['message' => 'A genuine enquiry about pricing']]);

    $response = $this->actingAs($this->user)
        ->get(cp_route('riffraff.index', ['q' => 'watches']));

    $response->assertOk()
        ->assertSee('Buy cheap watches now')
        ->assertDontSee('A genuine enquiry about pricing');
});

it('filters the list by form', function () {
    storeHeldSubmission('from-contact', [
        'form_slug' => 'contact-form',
        'data' => ['message' => 'A contact form enquiry'],
    ]);
    storeHeldSubmission('from-newsletter', [
        'form_slug' => 'newsletter-form',
        'data' => ['message' => 'A newsletter signup'],
    ]);

    $this->actingAs($this->user)
        ->get(cp_route('riffraff.index', ['form' => 'newsletter-form']))
        ->assertOk()
        ->assertSee('A newsletter signup')
        ->assertDontSee('A contact form enquiry');
});

it('returns a 404 rather than a server error for a missing submission', function () {
    $this->actingAs($this->user)
        ->get(cp_route('riffraff.show', ['id' => 'does-not-exist']))
        ->assertNotFound();
});

it('shows the reasons and readable fields for a held submission', function () {
    storeHeldSubmission('held-two', [
        'data' => ['name' => 'Jane Doe', 'message' => 'Buy cheap watches now'],
        'reasons' => ['Spam phrases detected: "watches"', 'Too many links detected'],
    ]);

    $this->actingAs($this->user)
        ->get(cp_route('riffraff.show', ['id' => 'held-two']))
        ->assertOk()
        ->assertSee('Spam phrases detected')
        ->assertSee('Too many links detected')
        ->assertSee('Name')
        ->assertSee('Jane Doe');
});

it('keeps a submission held when its form no longer exists', function () {
    storeHeldSubmission('orphaned', ['form_slug' => 'ghost-form']);

    $this->actingAs($this->user)
        ->post(cp_route('riffraff.store', ['id' => 'orphaned']))
        ->assertRedirect(cp_route('riffraff.index'));

    expect(File::exists(base_path('content/riffraff/orphaned.yaml')))->toBeTrue();
});

it('releases a submission and removes it from storage', function () {
    storeHeldSubmission('to-release');

    $this->actingAs($this->user)
        ->post(cp_route('riffraff.store', ['id' => 'to-release']))
        ->assertRedirect(cp_route('riffraff.index'));

    expect(File::exists(base_path('content/riffraff/to-release.yaml')))->toBeFalse();
});

it('deletes a single held submission', function () {
    storeHeldSubmission('to-delete');

    $this->actingAs($this->user)
        ->delete(cp_route('riffraff.destroy', ['id' => 'to-delete']))
        ->assertRedirect(cp_route('riffraff.index'));

    expect(File::exists(base_path('content/riffraff/to-delete.yaml')))->toBeFalse();
});

it('deletes all held submissions', function () {
    storeHeldSubmission('one');
    storeHeldSubmission('two');

    $this->actingAs($this->user)
        ->delete(cp_route('riffraff.destroyAll'))
        ->assertRedirect(cp_route('riffraff.index'));

    expect(File::exists(base_path('content/riffraff/one.yaml')))->toBeFalse()
        ->and(File::exists(base_path('content/riffraff/two.yaml')))->toBeFalse();
});

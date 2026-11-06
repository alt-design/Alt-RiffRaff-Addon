<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Form;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use Statamic\Facades\YAML;

it('denies access to a user without the view riffraff permission', function () {
    Role::make('cp-access')->permissions(['access cp'])->save();

    $user = User::make()->assignRole('cp-access')->save();

    $this->actingAs($user)
        ->get(cp_route('riffraff.index'))
        ->assertRedirect(cp_route('index'));
});

it('allows access to a user with the view riffraff permission', function () {
    Role::make('cp-access')->permissions(['access cp', 'view riffraff'])->save();

    $user = User::make()->assignRole('cp-access')->save();

    $this->actingAs($user)
        ->get(cp_route('riffraff.index'))
        ->assertOk();
});

function storeHeldSubmissionFile(string $id): string
{
    $path = base_path('content/riffraff/' . $id . '.yaml');

    File::ensureDirectoryExists(dirname($path));
    File::put($path, YAML::dump([
        'id' => $id,
        'data' => ['message' => 'Cheap watches'],
        'spam_score' => 200,
        'threshold' => 100,
        'reasons' => ['Spam phrases detected'],
        'form_slug' => 'contact',
        'is_spam' => true,
        'flagged_at' => now()->toIso8601String(),
    ]));

    return $path;
}

it('does not show, release or delete held submissions for a user without the permission', function (string $method, string $route, bool $needsId) {
    Form::make('contact')->title('Contact')->save();
    $path = storeHeldSubmissionFile('held-1');
    $savedBefore = Form::find('contact')->submissions()->count();

    Role::make('cp-access')->permissions(['access cp'])->save();
    $user = User::make()->assignRole('cp-access')->save();

    $url = cp_route($route, $needsId ? ['id' => 'held-1'] : []);

    $this->actingAs($user)->call($method, $url)->assertRedirect(cp_route('index'));

    expect(File::exists($path))->toBeTrue()
        ->and(Form::find('contact')->submissions()->count())->toBe($savedBefore);

    File::delete($path);
})->with([
    'show' => ['GET', 'riffraff.show', true],
    'release' => ['POST', 'riffraff.store', true],
    'delete one' => ['DELETE', 'riffraff.destroy', true],
    'delete all' => ['DELETE', 'riffraff.destroyAll', false],
]);

it('lets a user with the permission release a held submission', function () {
    Form::make('contact')->title('Contact')->save();
    $path = storeHeldSubmissionFile('held-2');

    Role::make('cp-access')->permissions(['access cp', 'view riffraff'])->save();
    $user = User::make()->assignRole('cp-access')->save();

    $this->actingAs($user)
        ->post(cp_route('riffraff.store', ['id' => 'held-2']))
        ->assertRedirect(cp_route('riffraff.index'));

    expect(File::exists($path))->toBeFalse();
});

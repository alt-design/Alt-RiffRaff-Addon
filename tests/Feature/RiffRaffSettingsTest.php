<?php

declare(strict_types=1);

use AltDesign\RiffRaff\Support\RiffRaff;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Addon;

beforeEach(function () {
    config([
        'alt-riffraff.api_key' => '',
        'alt-riffraff.api_email' => '',
        'alt-riffraff.api_password' => '',
    ]);
});

afterEach(function () {
    File::delete(resource_path('addons/alt-riffraff.yaml'));
});

it('registers a settings blueprint so the api key can be managed in the control panel', function () {
    $addon = Addon::get('alt-design/alt-riffraff');

    expect($addon)->not->toBeNull()
        ->and($addon->hasSettingsBlueprint())->toBeTrue();
});

it('falls back to the control panel setting when no environment key is configured', function () {
    Addon::get('alt-design/alt-riffraff')->settings()->set(['api_key' => 'from-settings'])->save();

    expect(RiffRaff::apiKey())->toBe('from-settings');
});

it('prefers the environment variable over the control panel setting', function () {
    Addon::get('alt-design/alt-riffraff')->settings()->set(['api_key' => 'from-settings'])->save();

    config(['alt-riffraff.api_key' => 'from-env']);

    expect(RiffRaff::apiKey())->toBe('from-env');
});

it('returns null when neither the environment nor the control panel setting has a key', function () {
    expect(RiffRaff::apiKey())->toBeNull();
});

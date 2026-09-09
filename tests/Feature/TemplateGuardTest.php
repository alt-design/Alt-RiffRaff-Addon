<?php

declare(strict_types=1);

use AltDesign\RiffRaff\Support\TemplateGuard;

it('breaks mustache pairs so they cannot be parsed as vue template expressions', function () {
    $broken = TemplateGuard::breakMustaches('Please use {{ constructor.constructor("alert(1)")() }} here');

    expect($broken)->not->toContain('{{')
        ->and($broken)->not->toContain('}}')
        ->and($broken)->toContain('constructor.constructor');
});

it('leaves ordinary text untouched', function () {
    expect(TemplateGuard::breakMustaches('Just a normal enquiry message.'))
        ->toBe('Just a normal enquiry message.');
});

it('returns an empty string for null or empty input', function () {
    expect(TemplateGuard::breakMustaches(null))->toBe('')
        ->and(TemplateGuard::breakMustaches(''))->toBe('');
});

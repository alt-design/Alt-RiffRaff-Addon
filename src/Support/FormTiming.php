<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Support;

class FormTiming
{
    public const FIELD = '_riffraff_loaded';

    private const DEFAULT_MAX_AGE = 3600;

    /**
     * Renders the hidden input carrying the moment the form was loaded, signed
     * with the application key so the visitor cannot change it.
     */
    public static function field(string $formHandle): string
    {
        if (! RiffRaff::isConfigured()) {
            return '';
        }

        $token = self::token($formHandle, now()->getTimestamp());

        if ($token === null) {
            return '';
        }

        return '<input type="hidden" name="' . self::FIELD . '" value="' . e($token) . '">';
    }

    /**
     * Whole seconds between the form loading and being submitted, or null
     * when the value is missing, was not signed by this site for this form,
     * lies in the future, or is older than the maximum age. A page served from
     * a cache carries an old timestamp, so it falls past the maximum age and
     * sends nothing rather than a misleading figure.
     */
    public static function secondsSinceLoad(mixed $token, string $formHandle): ?int
    {
        if (! is_string($token) || ! preg_match('/^(\d{1,12})\.([a-f0-9]{64})$/', $token, $parts)) {
            return null;
        }

        $expected = self::token($formHandle, (int) $parts[1]);

        if ($expected === null || ! hash_equals($expected, $token)) {
            return null;
        }

        $elapsed = now()->getTimestamp() - (int) $parts[1];

        if ($elapsed < 0 || $elapsed > self::maxAge()) {
            return null;
        }

        return $elapsed;
    }

    private static function token(string $formHandle, int $timestamp): ?string
    {
        $key = (string) config('app.key', '');

        if ($key === '') {
            return null;
        }

        $signature = hash_hmac('sha256', 'riffraff-form-loaded|' . $formHandle . '|' . $timestamp, $key);

        return $timestamp . '.' . $signature;
    }

    private static function maxAge(): int
    {
        return (int) config('alt-riffraff.timing_max_age', self::DEFAULT_MAX_AGE);
    }
}

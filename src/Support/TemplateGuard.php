<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Support;

class TemplateGuard
{
    /**
     * Statamic 6 compiles addon control panel Blade views as a live Vue
     * template at runtime, rather than inserting rendered HTML inertly. This
     * addon exists to display form submissions that are, by definition,
     * attacker-supplied, so a literal `{{ }}` pair inside a held submission
     * would otherwise be interpreted as a Vue mustache expression once
     * mounted, rather than shown as text. Splitting the pair with a
     * zero-width space breaks the token without changing what the browser
     * displays.
     */
    public static function breakMustaches(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return str_replace(
            ['{{', '}}'],
            ["{\u{200B}{", "}\u{200B}}"],
            $value,
        );
    }
}

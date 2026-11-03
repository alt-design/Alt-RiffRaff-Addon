<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Connect;

/**
 * What git would do with the addon settings file.
 */
enum GitStatus
{
    case Tracked;
    case Ignored;
    case NotIgnored;
    case NoRepository;
    case Unknown;
}

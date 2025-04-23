<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Tests;

use AltDesign\RiffRaff\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}

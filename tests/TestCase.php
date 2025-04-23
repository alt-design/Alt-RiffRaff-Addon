<?php

declare(strict_types=1);

namespace AltDesign\SpamAddon\Tests;

use AltDesign\SpamAddon\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}

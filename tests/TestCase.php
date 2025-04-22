<?php

declare(strict_types=1);

namespace FRoepstorf\StaticCacheBuster\Tests;

use FRoepstorf\StaticCacheBuster\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}

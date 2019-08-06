<?php

namespace DarkGhostHunter\Laralocker\Tests;

trait RegistersPackage
{
    protected function getPackageProviders($app)
    {
        return ['DarkGhostHunter\Laralocker\LaralockerServiceProvider'];
    }
}

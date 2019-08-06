<?php

namespace DarkGhostHunter\Laralocker\Tests;

use DarkGhostHunter\Laralocker\Tests\testing\QueueableJob;
use Orchestra\Testbench\TestCase;

class ExampleTest extends TestCase
{
    use RegistersPackage;

    public function testGenerates()
    {
        dispatch(new QueueableJob());

        $this->assertEquals(11, QueueableJob::$current_slot);
    }
}

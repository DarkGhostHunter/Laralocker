<?php

namespace DarkGhostHunter\Laralocker\Tests\Stubs;

use DarkGhostHunter\Laralocker\Contracts\Lockable;
use DarkGhostHunter\Laralocker\HandlesSlot;
use Illuminate\Cache\Repository;
use Mockery;

class LockableCustomJob implements Lockable
{
    use HandlesSlot;

    public $slotTtl = 99;

    public $prefix = 'test_prefix';

    public function handle()
    {
        $this->reserveSlot();
    }

    public function cache()
    {
        $mock = Mockery::spy(Repository::class);

        $mock->shouldReceive('remember')
            ->once()
            ->with('test_prefix:last_slot', null, Mockery::type('Closure'))
            ->andReturn(1);

        $mock->shouldReceive('has')
            ->once()
            ->with('test_prefix|11')
            ->andReturnFalse();

        $mock->expects('put')
            ->once()
            ->with('test_prefix|11', Mockery::type('float'), 99)
            ->andReturnTrue();

        return $mock;
    }

    public function startFrom()
    {
        return 1;
    }

    public function next($slot)
    {
        return $slot + 10;
    }
}

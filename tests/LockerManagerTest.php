<?php

namespace DarkGhostHunter\Laralocker\Tests;

use DarkGhostHunter\Laralocker\Tests\Stubs\LockableExceptionJob;
use DarkGhostHunter\Laralocker\Tests\Stubs\LockableFailedJob;
use DarkGhostHunter\Laralocker\Tests\Stubs\LockableJob;
use Exception;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Orchestra\Testbench\TestCase;

class LockerManagerTest extends TestCase
{
    use RegistersPackage;

    public function test_locks_jobs(): void
    {
        $job = new LockableJob;

        $repository = Mockery::mock(Repository::class);

        Cache::shouldReceive('store')
            ->twice()
            ->andReturn($repository);

        $repository->expects('remember')
            ->once()
            ->with('queue_locker:last_slot', null, Mockery::type('Closure'))
            ->andReturn($job->startFrom());

        $repository->expects('has')
            ->once()
            ->with('queue_locker|11')
            ->andReturnFalse();

        $repository->expects('put')
            ->once()
            ->with('queue_locker|11', Mockery::type('float'), config('laralocker.ttl'))
            ->andReturnTrue();

        $repository->expects('get')
            ->once()
            ->with('queue_locker|11', 0)
            ->andReturn(1500000000.0001);

        $repository->expects('get')
            ->once()
            ->with('queue_locker:microtime', 0)
            ->andReturn(1500000000.0000);

        $repository->expects('forever')
            ->once()
            ->with('queue_locker:microtime', Mockery::type('float'))
            ->andReturnTrue();

        $repository->expects('forever')
            ->once()
            ->with('queue_locker:last_slot', 11)
            ->andReturnTrue();

        $repository->expects('forget')
            ->once()
            ->with('queue_locker|11')
            ->andReturnTrue();

        dispatch($job);

        static::assertEquals(11, LockableJob::$current_slot);
        LockableJob::$current_slot = 0;
    }

    public function test_lockable_failed_does_clears_instead_of_releasing(): void
    {
        $repository = Mockery::mock(Repository::class);

        Cache::shouldReceive('store')
            ->andReturn($repository);

        $repository->expects('remember')
            ->once()
            ->with('queue_locker:last_slot', null, Mockery::type('Closure'))
            ->andReturn(1);

        $repository->expects('has')
            ->once()
            ->with('queue_locker|11')
            ->andReturnFalse();

        $repository->expects('put')
            ->once()
            ->with('queue_locker|11', Mockery::type('float'), config('laralocker.ttl'))
            ->andReturnTrue();

        $repository->expects('forget')
            ->once()
            ->with('queue_locker|11')
            ->andReturnTrue();

        $repository->shouldNotReceive('get')
            ->with('queue_locker|11', 0)
            ->andReturn(0);

        $repository->shouldNotReceive('get')
            ->with('queue_locker:microtime', 0)
            ->andReturn(0);

        $repository->shouldNotReceive('forever')
            ->with('queue_locker:microtime', Mockery::type('float'));

        $repository->shouldNotReceive('forever')
            ->with('queue_locker:last_slot', 11);

        dispatch(new LockableFailedJob);

        static::assertEquals(11, LockableFailedJob::$current_slot);
    }

    public function test_lockable_exception_does_clears_instead_of_releasing(): void
    {
        $repository = Mockery::mock(Repository::class);

        Cache::shouldReceive('store')
            ->andReturn($repository);

        Cache::shouldReceive('store')
            ->andReturn($repository);

        $repository->expects('remember')
            ->once()
            ->with('queue_locker:last_slot', null, Mockery::type('Closure'))
            ->andReturn(1);

        $repository->expects('has')
            ->once()
            ->with('queue_locker|11')
            ->andReturnFalse();

        $repository->expects('put')
            ->once()
            ->with('queue_locker|11', Mockery::type('float'), config('laralocker.ttl'))
            ->andReturnTrue();

        $repository->expects('forget')
            ->once()
            ->with('queue_locker|11')
            ->andReturnTrue();

        $repository->shouldNotReceive('get')
            ->with('queue_locker|11', 0)
            ->andReturn(0);

        $repository->shouldNotReceive('get')
            ->with('queue_locker:microtime', 0)
            ->andReturn(0);

        $repository->shouldNotReceive('forever')
            ->with('queue_locker:microtime', Mockery::type('float'));

        $repository->shouldNotReceive('forever')
            ->with('queue_locker:last_slot', 11);

        try {
            dispatch(new LockableExceptionJob);
        } catch (Exception $exception) {
            static::assertEquals(11, LockableExceptionJob::$current_slot);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Mockery::close();
    }
}

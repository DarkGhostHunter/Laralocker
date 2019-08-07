<?php

namespace DarkGhostHunter\Laralocker\Tests;

use DarkGhostHunter\Laralocker\Contracts\Lockable;
use DarkGhostHunter\Laralocker\HandlesSlot;
use Exception;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Orchestra\Testbench\TestCase;

class LockerManagerTest extends TestCase
{
    use RegistersPackage;

    public function testLocksJobs()
    {
        $job = new LockableJob;

        $repository = Mockery::mock(Repository::class);

        Cache::shouldReceive('store')
            ->once()
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

        $job->handle();

        $this->assertEquals(11, LockableJob::$current_slot);
        LockableJob::$current_slot = 0;
    }

    public function testLockableFailedDoesNotClears()
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
            ->with('queue_locker|11', 0);

        $repository->shouldNotReceive('get')
            ->with('queue_locker:microtime');

        $repository->shouldNotReceive('forever')
            ->with('queue_locker:microtime', Mockery::type('float'));

        $repository->shouldNotReceive('forever')
            ->with('queue_locker:last_slot', 11);

        dispatch(new LockableFailedJob);

        $this->assertEquals(0, LockableJob::$current_slot);
    }

    public function testLockableExceptionDoesNotClears()
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

        $repository->shouldNotReceive('forget')
            ->with('queue_locker|11');

        $repository->shouldNotReceive('get')
            ->with('queue_locker|11', 0);

        $repository->shouldNotReceive('get')
            ->with('queue_locker:microtime');

        $repository->shouldNotReceive('forever')
            ->with('queue_locker:microtime', Mockery::type('float'));

        $repository->shouldNotReceive('forever')
            ->with('queue_locker:last_slot', 11);

        try {
            dispatch(new LockableExceptionJob);
        } catch (Exception $exception) {
            $this->assertEquals(0, LockableJob::$current_slot);
        }

    }

    public function testUsesCustomProperties()
    {
        dispatch(new LockableCustomJob);
    }

    public function testSequentialJobs()
    {
        $job_a = new LockableJob;
        $job_b = new LockableJob;
        $job_c = new LockableJob;

        $job_b->handle();
        $job_c->handle();
        $job_a->handle();

        $this->assertEquals(31, LockableJob::$current_slot);
        LockableJob::$current_slot = 0;
    }

    public function testConcurrentJobs()
    {
        $job_0 = new LockableConcurrentJob;
        $job_1 = new LockableConcurrentJob;
        $job_2 = new LockableConcurrentJob;
        $job_3 = new LockableConcurrentJob;
        $job_4 = new LockableConcurrentJob;
        $job_5 = new LockableConcurrentJob;
        $job_6 = new LockableConcurrentJob;

        $job_0->handle();
        $job_1->handle();
        $job_2->handle();  // Stalls
        $job_1->releaseSlot(); // Inverse Order
        $job_0->releaseSlot(); // Inverse Order
        $job_3->handle(); // Starts when other is stalled
        $job_3->releaseSlot(); // Ends when other is stalled
        $job_4->handle();
        $job_4->releaseSlot();
        $job_2->releaseSlot(); // Stalls releases lock late
        $job_5->handle();
        $job_6->handle();
        $job_5->releaseSlot();
        $job_6->releaseSlot();

        $this->assertEquals([
            '10', '20', '30', '40', '50', '60', '70'
        ], LockableConcurrentJob::$slots);
        LockableConcurrentJob::$slots = [];

        $this->assertEquals(10, $job_0->getSlot());
        $this->assertEquals(20, $job_1->getSlot());
        $this->assertEquals(30, $job_2->getSlot());
        $this->assertEquals(40, $job_3->getSlot());
        $this->assertEquals(50, $job_4->getSlot());
        $this->assertEquals(60, $job_5->getSlot());
        $this->assertEquals(70, $job_6->getSlot());
    }

    public function testJobUsesClearedSlot()
    {
        $job_0 = new LockableConcurrentJob;
        $job_1 = new LockableConcurrentJob;
        $job_2 = new LockableConcurrentJob;

        $job_0->handle();
        $job_0->clearSlot();
        $job_1->handle();
        $job_2->handle();
        $job_2->releaseSlot();
        $job_1->releaseSlot();

        $job_0->handle();
        $job_0->releaseSlot();

        $this->assertEquals([
            '10', '10', '20', '30',
        ], LockableConcurrentJob::$slots);
        LockableConcurrentJob::$slots = [];

        $this->assertEquals(10, $job_1->getSlot());
        $this->assertEquals(20, $job_2->getSlot());
        $this->assertEquals(30, $job_0->getSlot());
    }

    public function testJobRetriedAndDidntRelease()
    {
        $job_0 = new LockableConcurrentJob;
        $job_1 = new LockableConcurrentJob;
        $job_2 = new LockableConcurrentJob;

        // Job 2 fails so it will reserve the slot but not release it. Next jobs
        // will skip it even if the job was retried and failed.
        $job_0->handle();
        $job_1->handle();
        $job_2->handle();
        $job_2->handle();
        $job_1->releaseSlot();
        $job_2->handle();
        $job_0->releaseSlot();

        $this->assertEquals([
            '10', '20', '30', '40', '50',
        ], LockableConcurrentJob::$slots);
        LockableConcurrentJob::$slots = [];

        $this->assertEquals(10, $job_0->getSlot());
        $this->assertEquals(20, $job_1->getSlot()); // Failed 3 times, reserved 3 slots ahead
        $this->assertEquals(50, $job_2->getSlot());
    }
}

class LockableJob implements ShouldQueue, Lockable
{
    use HandlesSlot;

    public static $current_slot = 0;

    public function handle()
    {
        $this->reserveSlot();

        static::$current_slot = $this->slot;

        $this->releaseSlot();
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

class LockableFailedJob implements ShouldQueue, Lockable
{
    use InteractsWithQueue;
    use HandlesSlot;

    public static $current_slot = 0;

    public function handle()
    {
        $this->reserveSlot();

        static::$current_slot = $this->slot;

        $this->clearSlot();
        $this->fail();
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

class LockableExceptionJob implements ShouldQueue, Lockable
{
    use InteractsWithQueue;
    use HandlesSlot;

    public static $current_slot = 0;

    public function handle()
    {
        $this->reserveSlot();

        static::$current_slot = $this->slot;

        throw new Exception('error');
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
        $mock = Mockery::spy(\Illuminate\Cache\Repository::class);

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

class LockableConcurrentJob implements Lockable
{
    use HandlesSlot;

    public static $slots = [];

    public function handle()
    {
        self::$slots[] = $this->reserveSlot();
    }

    public function startFrom()
    {
        return 0;
    }

    public function next($slot)
    {
        return $slot + 10;
    }
}

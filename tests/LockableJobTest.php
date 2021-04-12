<?php

namespace DarkGhostHunter\Laralocker\Tests;

use DarkGhostHunter\Laralocker\Tests\Stubs\LockableConcurrentJob;
use DarkGhostHunter\Laralocker\Tests\Stubs\LockableCustomJob;
use DarkGhostHunter\Laralocker\Tests\Stubs\LockableJob;
use DarkGhostHunter\Laralocker\Tests\Stubs\LockableTaggableJob;
use Orchestra\Testbench\TestCase;

class LockableJobTest extends TestCase
{
    use RegistersPackage;

    public function test_uses_custom_properties(): void
    {
        dispatch(new LockableCustomJob);

        static::assertTrue(LockableCustomJob::$cacheCalled);
    }

    public function test_lockable_job_runs(): void
    {
        dispatch(new LockableJob);
        dispatch(new LockableJob);
        dispatch(new LockableJob);

        static::assertEquals(31, LockableJob::$current_slot);
    }

    public function test_concurrent_jobs(): void
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

        static::assertEquals(['10', '20', '30', '40', '50', '60', '70'], LockableConcurrentJob::$slots);

        static::assertEquals(10, $job_0->getSlot());
        static::assertEquals(20, $job_1->getSlot());
        static::assertEquals(30, $job_2->getSlot());
        static::assertEquals(40, $job_3->getSlot());
        static::assertEquals(50, $job_4->getSlot());
        static::assertEquals(60, $job_5->getSlot());
        static::assertEquals(70, $job_6->getSlot());
    }

    public function test_job_uses_cleared_slot(): void
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

        static::assertEquals(['10', '10', '20', '30'], LockableConcurrentJob::$slots);

        static::assertEquals(10, $job_1->getSlot());
        static::assertEquals(20, $job_2->getSlot());
        static::assertEquals(30, $job_0->getSlot());
    }

    public function test_job_retried_and_didnt_release(): void
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

        static::assertEquals(['10', '20', '30', '40', '50'], LockableConcurrentJob::$slots);

        static::assertEquals(10, $job_0->getSlot());
        static::assertEquals(20, $job_1->getSlot()); // Failed 3 times, reserved 3 slots ahead
        static::assertEquals(50, $job_2->getSlot());
    }

    public function test_works_with_taggable_cache(): void
    {
        $job_0 = new LockableTaggableJob;
        $job_1 = new LockableTaggableJob;
        $job_2 = new LockableTaggableJob;
        $job_3 = new LockableTaggableJob;
        $job_4 = new LockableTaggableJob;
        $job_5 = new LockableTaggableJob;
        $job_6 = new LockableTaggableJob;

        $job_0->handle();
        $job_1->handle();
        $job_2->handle();  // Stalls and dies
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

        static::assertEquals(['10', '20', '30', '40', '50', '60', '70'], LockableTaggableJob::$slots);

        static::assertEquals(10, $job_0->getSlot());
        static::assertEquals(20, $job_1->getSlot());
        static::assertEquals(30, $job_2->getSlot());
        static::assertEquals(40, $job_3->getSlot());
        static::assertEquals(50, $job_4->getSlot());
        static::assertEquals(60, $job_5->getSlot());
        static::assertEquals(70, $job_6->getSlot());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        LockableCustomJob::$cacheCalled = false;
        LockableJob::$current_slot = 0;
        LockableConcurrentJob::$slots = [];
    }
}

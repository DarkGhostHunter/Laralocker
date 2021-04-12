<?php

namespace DarkGhostHunter\Laralocker\Tests\Stubs;

use DarkGhostHunter\Laralocker\LockerJobMiddleware;
use Illuminate\Support\Facades\Cache;

class LockableTaggableJob extends LockableConcurrentJob
{
    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        return [new LockerJobMiddleware()];
    }

    public function cache()
    {
        return Cache::store('array')->tags('test_tag_store');
    }
}

<?php

namespace DarkGhostHunter\Laralocker\Tests\Stubs;

use Illuminate\Support\Facades\Cache;

class LockableTaggableJob extends LockableConcurrentJob
{
    public function cache()
    {
        return Cache::store('array')->tags('test_tag_store');
    }
}

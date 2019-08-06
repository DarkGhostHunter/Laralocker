<?php

namespace DarkGhostHunter\Laralocker\Listeners;

use DarkGhostHunter\Laralocker\Contracts\Lockable;
use Illuminate\Queue\Events\JobProcessed;

class ReleasesJobListener extends AbstractJobListener
{
    /**
     * Handle the event.
     *
     * @param \Illuminate\Queue\Events\JobProcessed $event
     * @return void
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function handle(JobProcessed $event)
    {
        if (! $event->job instanceof Lockable) {
            return;
        }

        $this->prepareCache($event->job);

        $this->locker->setInstance($event->job)->handleSlotRelease();
    }
}

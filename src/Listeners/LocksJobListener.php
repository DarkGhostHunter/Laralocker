<?php

namespace DarkGhostHunter\Laralocker\Listeners;

use DarkGhostHunter\Laralocker\Contracts\Lockable;
use Illuminate\Queue\Events\JobProcessing;

class LocksJobListener extends AbstractJobListener
{
    /**
     * Handle the event.
     *
     * @param \Illuminate\Queue\Events\JobProcessing $event
     * @return void
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function handle(JobProcessing $event)
    {
        if (!$event->job instanceof Lockable) {
            return;
        }

        $this->prepareCache($event->job);

        $this->locker->setInstance($event->job)->reserveNextAvailableSlot();
    }
}

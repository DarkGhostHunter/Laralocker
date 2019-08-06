<?php

namespace DarkGhostHunter\Laralocker\Listeners;

use DarkGhostHunter\Laralocker\Contracts\Lockable;

class CleansJobListener extends AbstractJobListener
{
    /**
     * Handle the event.
     *
     * @param \Illuminate\Queue\Events\JobFailed|\Illuminate\Queue\Events\JobExceptionOccurred $event
     * @return void
     */
    public function handle($event)
    {
        if (! $event->job instanceof Lockable) {
            return;
        }

        $this->prepareCache($event->job);

        $this->locker->setInstance($event->job)->releaseSlot();
    }
}

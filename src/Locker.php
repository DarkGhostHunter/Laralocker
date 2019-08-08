<?php

namespace DarkGhostHunter\Laralocker;

use Illuminate\Contracts\Cache\Repository;

class Locker
{
    /**
     * Cache Store
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $store;

    /**
     * Queued Lockable Job instance
     *
     * @var \DarkGhostHunter\Laralocker\Contracts\Lockable
     */
    protected $instance;

    /**
     * Prefix to use in the Cache Repository
     *
     * @var string
     */
    protected $prefix;

    /**
     * Slot Reservation Time to Live
     *
     * @var int
     */
    protected $ttl;

    /**
     * Creates a new Concurrent instance
     *
     * @param $instance
     * @param \Illuminate\Contracts\Cache\Repository $store
     * @param string $prefix
     * @param int $ttl
     */
    public function __construct($instance, Repository $store, string $prefix, int $ttl)
    {
        $this->instance = $instance;
        $this->store = $store;
        $this->prefix = $prefix;
        $this->ttl = $ttl;
    }

    /**
     * Handles the release of the slot
     *
     * @return void
     */
    public function handleSlotRelease()
    {
        $this->updateInitialSlot();
        $this->releaseSlot();
    }

    /**
     * Updates the initial Slot so next Jobs can start reserving from there
     *
     * @return void
     */
    protected function updateInitialSlot()
    {
        // If the slot was reserved before the time were we updated the last slot used, then we will
        // update it. This will allow next Jobs to use get the next slot from the last used instead
        // from the very beginning, and block the next jobs from replacing it with a previous slot.
        if ($this->lastSlotTime() < $this->reservedSlotTime()) {
            $this->store->forever($this->prefix . ':microtime', microtime(true));
            $this->store->forever($this->prefix . ':last_slot', $this->instance->getSlot());
        }
    }

    /**
     * Return when was saved the last slot
     *
     * @return int
     */
    protected function lastSlotTime()
    {
        return $this->store->get($this->prefix . ':microtime', 0);
    }

    /**
     * Return the time of the reserved slot
     *
     * @return float
     */
    protected function reservedSlotTime()
    {
        // If we miss the cache, we will assume it expired while the Job was still processing.
        // In that case, returning zero will allow to NOT update the last saved slot because
        // we don't have any guarantee if this Job ended before the last one to update it.
        return $this->store->get($this->key($this->instance->getSlot()), 0);
    }

    /**
     * Returns the slot key
     *
     * @param $slot
     * @return string
     */
    protected function key($slot)
    {
        return $this->prefix . '|' . $slot;
    }

    /**
     * Deletes the slot used by the Job
     *
     * @return bool
     */
    public function releaseSlot()
    {
        return $this->store->forget(
            $this->key($this->instance->getSlot())
        );
    }

    /**
     * Returns the next available slot to use by the Job
     *
     * @return mixed
     */
    public function reserveNextAvailableSlot()
    {
        $slot = $this->initialSlot();

        do {
            $slot = $this->instance->next($slot);
        } while ($this->isReserved($slot));

        return $this->instance->setSlot($this->reserveSlot($slot));
    }

    /**
     * Retrieves the initial Slot to start reserving
     *
     * @return mixed
     */
    protected function initialSlot()
    {
        // The logic in these lines is fairly simplistic. If we did not save in the cache the
        // last slot, we will call the job to tell us where to start. Once we save it, we
        // will prefer retrieving the last slot from the cache because its be faster.
        return $this->store->remember($this->prefix . ':last_slot', null, function () {
            return $this->instance->startFrom();
        });
    }

    /**
     * Return if the slot has been reserved by other Job
     *
     * @param $slot
     * @return bool
     */
    protected function isReserved($slot)
    {
        return $this->store->has($this->key($slot));
    }

    /**
     * Reserves the Slot into the Repository
     *
     * @param $slot
     * @return mixed
     */
    protected function reserveSlot($slot)
    {
        $this->store->put($this->key($slot), microtime(true), $this->ttl);

        return $slot;
    }
}

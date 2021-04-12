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
    protected Repository $store;

    /**
     * Queued Lockable Job instance
     *
     * @var \DarkGhostHunter\Laralocker\Contracts\Lockable
     */
    protected Contracts\Lockable $instance;

    /**
     * Prefix to use in the Cache Repository
     *
     * @var string
     */
    protected string $prefix;

    /**
     * Slot Reservation Time to Live
     *
     * @var int
     */
    protected int $ttl;

    /**
     * Creates a new Concurrent instance
     *
     * @param  \DarkGhostHunter\Laralocker\Contracts\Lockable  $instance
     * @param  \Illuminate\Contracts\Cache\Repository  $store
     * @param  string  $prefix
     * @param  int  $ttl
     */
    public function __construct(Contracts\Lockable $instance, Repository $store, string $prefix, int $ttl)
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
    public function handleSlotRelease(): void
    {
        $this->updateInitialSlot();
        $this->releaseSlot();
    }

    /**
     * Updates the initial Slot so next Jobs can start reserving from there
     *
     * @return void
     */
    protected function updateInitialSlot(): void
    {
        // To avoid updating the last saved slot with an old slot, we will check if this last slot
        // was saved before the moment we reserved the next in the locker. Otherwise, we will not
        // update it, since it will make the next job to use a (probably) already used old slot.
        if ($this->lastSlotTime() < $this->reservedSlotTime()) {
            $this->store->forever($this->prefix . ':microtime', microtime(true));
            $this->store->forever($this->prefix . ':last_slot', $this->instance->getSlot());
        }
    }

    /**
     * Return when was saved the last slot
     *
     * @return float
     */
    protected function lastSlotTime(): float
    {
        return $this->store->get($this->prefix . ':microtime', 0);
    }

    /**
     * Return the time of the reserved slot
     *
     * @return float
     */
    protected function reservedSlotTime(): float
    {
        // If we miss the cache, we will assume it expired while the Job was still processing.
        // In that case, returning zero will allow to NOT update the last saved slot because
        // we don't have any guarantee if this Job ended before the last one to update it.
        return $this->store->get($this->key($this->instance->getSlot()), 0);
    }

    /**
     * Returns the slot key
     *
     * @param \Illuminate\Support\Stringable|\Stringable|string|int $slot
     * @return string
     */
    protected function key($slot): string
    {
        return $this->prefix . '|' . $slot;
    }

    /**
     * Deletes the slot used by the Job
     *
     * @return bool
     */
    public function releaseSlot(): bool
    {
        return $this->store->forget(
            $this->key($this->instance->getSlot())
        );
    }

    /**
     * Returns the next available slot to use by the Job
     *
     * @return void
     */
    public function reserveNextAvailableSlot(): void
    {
        $slot = $this->initialSlot();

        do {
            $slot = $this->instance->next($slot);
        } while ($this->isReserved($slot));

        $this->instance->setSlot($this->reserveSlot($slot));
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
     * @param \Illuminate\Support\Stringable|\Stringable|string|int $slot
     * @return bool
     */
    protected function isReserved($slot): bool
    {
        return $this->store->has($this->key($slot));
    }

    /**
     * Reserves the Slot into the Repository
     *
     * @param \Illuminate\Support\Stringable|\Stringable|string|int $slot
     * @return \Illuminate\Support\Stringable|\Stringable|string|int
     */
    protected function reserveSlot($slot)
    {
        $this->store->put($this->key($slot), microtime(true), $this->ttl);

        return $slot;
    }
}

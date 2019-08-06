<?php

namespace DarkGhostHunter\Laralocker;

use DarkGhostHunter\Laralocker\Contracts\Lockable;
use Illuminate\Contracts\Cache\Repository;

class Locker
{
    /**
     * Cache Repository
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $repository;

    /**
     * Queued Slottable Job instance
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
     * @param \Illuminate\Contracts\Cache\Repository $repository
     * @param string $prefix
     * @param int $ttl
     */
    public function __construct(Repository $repository, string $prefix, int $ttl)
    {
        $this->repository = $repository;
        $this->prefix = $prefix;
        $this->ttl = $ttl;
    }

    /**
     * Set the Repository to use with the Slot Checker
     *
     * @param \Illuminate\Contracts\Cache\Repository $repository
     * @return \DarkGhostHunter\Laralocker\Locker
     */
    public function setRepository(Repository $repository)
    {
        $this->repository = $repository;

        return $this;
    }

    /**
     * Set the job Instance
     *
     * @param \DarkGhostHunter\Laralocker\Contracts\Lockable $instance
     * @return \DarkGhostHunter\Laralocker\Locker
     */
    public function setInstance(Lockable $instance)
    {
        $this->instance = $instance;

        return $this;
    }

    /**
     * Handles the release of the slot
     *
     * @return void
     * @throws \Psr\SimpleCache\InvalidArgumentException
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
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function updateInitialSlot()
    {
        // If the slot was reserved before the time were we updated the last slot used, then we will
        // update it. This will allow next Jobs to use get the next slot from the last used instead
        // of the very beginning, and block the next jobs from overriding it with a previous slot.
        if ($this->lastSlotTime() > $this->reservedSlotTime()) {
            $this->repository->forever($this->keyPrefix() . ':microtime', microtime(true));
            $this->repository->forever($this->keyPrefix() . ':last_slot', $this->instance->slot);
        }
    }

    /**
     * Return when was saved the last slot
     *
     * @return int
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function lastSlotTime()
    {
        return $this->repository->get($this->keyPrefix() . ':microtime');
    }

    /**
     * Returns the concurrency prefix to handle the jobs
     *
     * @return string
     */
    protected function keyPrefix()
    {
        return ($this->instance->prefix ?? $this->prefix) . '|' . ($this->instance->queue ?? 'default');
    }

    /**
     * Return the time of the reserved slot
     *
     * @return float
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function reservedSlotTime()
    {
        return $this->repository->get($this->key($this->instance->slot));
    }

    /**
     * Returns the slot key
     *
     * @param $slot
     * @return string
     */
    protected function key($slot)
    {
        return $this->keyPrefix() . '|' . $slot;
    }

    /**
     * Deletes the slot used by the Job
     *
     * @return bool
     */
    public function releaseSlot()
    {
        return $this->repository->forget(
            $this->key($this->instance->slot)
        );
    }

    /**
     * Returns the next available slot to use by the Job
     *
     * @return mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function reserveNextAvailableSlot()
    {
        $slot = $this->initialSlot();

        do {
            $slot = $this->instance->next($slot);
        } while ($this->isReserved($slot));

        return $this->reserveSlot($slot);
    }

    /**
     * Retrieves the initial Slot to start reserving
     *
     * @return mixed
     */
    protected function initialSlot()
    {
        return $this->repository->remember($this->keyPrefix() . ':last_slot', null, function () {
            return $this->instance->startFrom();
        });
    }

    /**
     * Return if the slot has been reserved by other Job
     *
     * @param $slot
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function isReserved($slot)
    {
        return $this->repository->has($this->key($slot));
    }

    /**
     * Reserves the Slot into the Repository
     *
     * @param $slot
     * @return mixed
     */
    protected function reserveSlot($slot)
    {
        $this->repository->put($this->key($slot), microtime(true), $this->reservationTtl());

        return $slot;
    }

    /**
     * Return the Job maximum time to live before timing out
     *
     * @return int
     */
    protected function reservationTtl()
    {
        return $this->instance->slotTtl
            ?? $this->instance->timeout
            ?? method_exists($this->instance, 'retryUntil')
                ? $this->instance->retryUntil()
                : $this->ttl;
    }
}

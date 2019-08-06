# Laralocker

![John Doyle - Unsplash (UL) #dAW17ADBZEM](https://images.unsplash.com/photo-1543853801-8e627b5e6ebf?ixlib=rb-1.2.1&auto=format&fit=crop&w=1280&h=400&q=80)

[![Latest Stable Version](https://poser.pugx.org/darkghosthunter/laralocker/v/stable)](https://packagist.org/packages/darkghosthunter/laralocker) [![License](https://poser.pugx.org/darkghosthunter/laralocker/license)](https://packagist.org/packages/darkghosthunter/laralocker)
![](https://img.shields.io/packagist/php-v/darkghosthunter/laralocker.svg) [![Build Status](https://travis-ci.com/DarkGhostHunter/Passless.svg?branch=master)](https://travis-ci.com/DarkGhostHunter/Laralock) [![Coverage Status](https://coveralls.io/repos/github/DarkGhostHunter/Laralock/badge.svg?branch=master)](https://coveralls.io/github/DarkGhostHunter/Laralock?branch=master) [![Maintainability](https://api.codeclimate.com/v1/badges/8f1790a00c264e287df4/maintainability)](https://codeclimate.com/github/DarkGhostHunter/Laralock/maintainability) [![Test Coverage](https://api.codeclimate.com/v1/badges/8f1790a00c264e287df4/test_coverage)](https://codeclimate.com/github/DarkGhostHunter/Laralock/test_coverage)

Avoid [race conditions](https://en.wikipedia.org/wiki/Race_condition) in your Jobs, Listeners and Notifications with this simple locker reservation system.

## Requisites

* Laravel 5.8 or 6.0

> Next versions will only support 6.0

## Installation

You can install the package via composer:

```bash
composer require darkghosthunter/laralocker
```

## What can this be used for?

Anything that has **[race conditions](https://en.wikipedia.org/wiki/Race_condition)**.

For example, let's say we need to create a sequential serial key for a sold Ticket, which is pushed to a queue. This introduces three problems:
 
* If two or more jobs started at the same time, these would check the last sold also at the same time, and save the next Ticket with the same serial key.
* If we use [Pessimistic Locking](https://laravel.com/docs/5.8/queries#pessimistic-locking) in our queue, we can be victims of [deadlocks](https://en.wikipedia.org/wiki/Deadlock) and stall the whole job.
* If we have one Queue Worker, it will only process one Ticket at a time. It will work for a small queue, but if a flood of users buy 1000 tickets in one minute, a single Queue Worker will take its sweet time to process all. The Concert starts in five minutes, hope your CPU is an EPYC top of the line!

Using this package, all Tickets can be dispatched concurrently without fear of collisions, just by announcing what ticket are processing.

## How it works

This package listens for every `Lockable` Job you send to the queue. That includes Notifications and Listeners. Then, using your application cache, the Job *look ahead* for free "slots", and reserve it.

> For sake of simplicity, I will treat Notifications and Listeners as a Job.

Once the Job finishes processing, it will release the "slot", and mark that slot as the starting point for the next Jobs so they don't look ahead from the beginning.

This is useful when your Jobs needs data that the Job from before also uses: Serial keys, result of calculations, you name it.

## Usage

1) Add the `Lockable` interface to your Job, Notification or Listener.

2) Add the `Locks` trait.

3) Then implement the `startFrom()` and `next($slot)` methods.

The fourth steps depends on your Laravel version.

### For Laravel 6.0

This package uses the new power of the Job Middlewares. Add the `LockerJobMiddleware` to your Job middleware.

```php
public function middleware()
{
     return [new LockerJobMiddleware];
}
```

### For Laravel 5.8

Finally, add `$this->lock()` and `$this->release()` to the start and end of your handle.

Is recommended, but not mandatory, to use the `$this->clear()` in your `failed()` method to [clear up](https://laravel.com/docs/5.8/queues#cleaning-up-after-failed-jobs).  

## Example

Here is a full example of a simple Listener that handles Serial Keys when a Ticket is sold for a given Concert to a given User.

```php
<?php

namespace App\Listeners;

use App\Ticket;
use App\Events\TicketSold;
use Illuminate\Contracts\Queue\ShouldQueue;
use DarkGhostHunter\Laralocker\Contracts\Lockable;
use DarkGhostHunter\Laralocker\Locks;

class CreateTicket implements ShouldQueue, Lockable
{
    use Locks;

    /**
     * Return the starting slot for the Jobs
     *
     * @return mixed
     */
    public function startFrom()
    {
        return Ticket::latest()->value('serial_key');
    }

    /**
     * The next slot to check for availability
     *
     * @param mixed $slot
     * @return mixed
     */
    public function next($slot)
    {
        return $slot + 10;
    }

    /**
     * Handle the event.
     *
     * @param \App\Listeners\TicketSold $event
     * @return void
     */
    public function handle(TicketSold $event)
    {
        // Acquire the lock for this job and create the slot
        // $this->lock(); // Not needed for Laravel 6.0

        $ticket = Ticket::make([
            'serial_key' => $this->slot,
        ]);

        // Associate the Ticket to the Concert and the User 
        $ticket->concert()->associate($event->concert);
        $ticket->user()->associate($event->user);

        // Save the Ticket into the system
        $ticket->save();

        // Unlock the job
        // $this->release(); // Not needed for Laravel 6.0
    }
}
```

Let's start checking what does each method:

### Starting with `lock()` and ending with `release()`

The `lock()` method boots up the locking system to reserve the job slot. Ideally, this should be in the first line of code, but as long is before calling the `$this->slot` will be fine.

The `release()` method tells the locking system to release the job, like a "light clean up". This should be the last line of code, but you can use it on the destructor.

The `clear()` is used if the Job fails to clear the reserved slot. It allows for other jobs to re-use the slot immediately, avoiding slot jumping.

> If you're using Laravel 6.0, these methods are unnecessary since they're handled by the Middleware itself.

### `startFrom()`

When the Job asks where to start, this will be used to get the "last slot" used.

Once this starting point is retrieved, the Locker will save the last used in the Cache and retrieve it from there, instead of executing this method in each Job. This is used only when the first Job hits the queue, or if the cache returns null (maybe because you flushed it).

> You should return a string, or an [object instance that can be represented as a string](https://www.php.net/manual/en/language.oop5.magic.php#object.tostring).

### `next($slot)`

Next, the Queue Worker will put the starting slot into this method to retrieve the next slot that should be free.

If the next slot is "reserved" in the Cache by another Job, it will recursively call `next($slot)` until it finds one that is not.

> For example, if your first slot is `10`, the method will receive `10` and then return `20`. The Locker will check if `20` is reserved, and if its not free, then it call `next()` again but it will receive `20`, and so on, until it finds one that is not reserved, like `60`.

### `cache()` (optional)

This is entirely optional. If you want that particular Job to use another Cache store, you can return it here. Just remember to [have properly configured the Cache driver](https://laravel.com/docs/5.8/cache#driver-prerequisites) you want to use in your application beforehand.

```php
<?php 

use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Queue\ShouldQueue;
use DarkGhostHunter\Laralocker\Contracts\Lockable;

class CreateTicket implements ShouldQueue, Lockable
{
    // ...

    /**
     * Use a non-default Cache repository for handling slots (optional)
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    public function cache()
    {
        return Cache::store('sqs');
    }
}
```

### `$slotTtl` (optional)

Also entirely optional. Slots are reserved by a given time by using the Cache. While the default is of 60 seconds, you can set a bigger _ttl_ if your Job takes its sweet time, like 3 minutes or more.

Is always recommended to set a maximum to avoid slot creeping in your cache.  

```php
<?php 

use Illuminate\Contracts\Queue\ShouldQueue;
use DarkGhostHunter\Laralocker\Contracts\Lockable;

class CreateTicket implements ShouldQueue, Lockable
{
    /**
     * Maximum Slot reservation time
     *
     * @var \Illuminate\Support\Carbon|int
     */
    public $slotTtl = 180;

    // ...
}
```

> If you don't use `$slotTtl`, the Locker will automatically get it from the `$timeout`, `retryUntil()` and finally the default from the config file, to match the Job lifecycle.

### `$prefix` (optional)

Also optional, this manages the prefix that it will be used for the slot reservations for the Job.

```php
<?php 

use Illuminate\Contracts\Queue\ShouldQueue;
use DarkGhostHunter\Laralocker\Contracts\Lockable;

class CreateTicket implements ShouldQueue, Lockable
{
    /**
     * Prefix for slots reservations
     *
     * @var string
     */
    public $prefix = 'ticket_slots';

    // ...
}
```

> If you don't use `$slotTtl`, the Locker will automatically get it from the `$timeout`, `retryUntil()` and finally the default from the config file, to match the Job lifecycle.

## Detailed inner workings

Before *handling* the Job, a custom Event Listener will check if it has the `Lockable` interface. If that's the case, then it will pass the Job to the Locker, that will check what was the last slot used for the Job using the Cache.

If there was no last slot used (because is the first in the queue, or the Cache was flushed), it will call `startFrom()` and save what it returns into slot into the Cache, forever.

Next, the Locker will check the next slot by calling `next($slot)` of the Job. It will recursively call `next($slot)` a non-reserved slot is found.

Once found, the Locker will reserve it using the Cache. If the Job has a timeout, it will use that value as Time-To-Live for the Cache key to avoid zombie reservations in the Cache.

Then the Locker will copy it inside the `$slot` property of the Job, and then the Job will be **_handled_**. That way, the developer can use the slot inside the Job (like in our Ticket example).

Once the Job finishes, the Locker will save the `$slot` as the last slot used in the Cache, forever. This will allow other Jobs to start from that slot, instead of checking from the very first slot and encounter unreserved slots that expired in the Cache.

If the slot was already saved as the last, it will compare the timestamp from when the Job was started, and update it only if its more recent. This allow to NOT save a slot that is "older".

Finally, it will "release" the current reserved slot from the reservation pool in the Cache, avoiding zombie keys into the cache.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

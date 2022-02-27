The problematic of this package is resolved using [Unique Jobs](https://laravel.com/docs/queues#unique-jobs).

This package is no longer maintained.

---

# Laralocker

![John Doyle - Unsplash (UL) #dAW17ADBZEM](https://images.unsplash.com/photo-1543853801-8e627b5e6ebf?ixlib=rb-1.2.1&auto=format&fit=crop&w=1280&h=400&q=80)

[![Latest Stable Version](https://poser.pugx.org/darkghosthunter/laralocker/v/stable)](https://packagist.org/packages/darkghosthunter/laralocker) [![License](https://poser.pugx.org/darkghosthunter/laralocker/license)](https://packagist.org/packages/darkghosthunter/laralocker)
![](https://img.shields.io/packagist/php-v/darkghosthunter/laralocker.svg) ![](https://github.com/DarkGhostHunter/Laralocker/workflows/PHP%20Composer/badge.svg) [![Coverage Status](https://coveralls.io/repos/github/DarkGhostHunter/Laralocker/badge.svg?branch=master)](https://coveralls.io/github/DarkGhostHunter/Laralocker?branch=master) [![Maintainability](https://api.codeclimate.com/v1/badges/b60b3f4c0f71f7841163/maintainability)](https://codeclimate.com/github/DarkGhostHunter/Laralocker/maintainability)

Avoid [race conditions](https://en.wikipedia.org/wiki/Race_condition) in your Jobs, Listeners and Notifications with this simple locker reservation system.

## Requisites

* PHP 7.4, 8.0 or later
* Laravel 7.x, 8.x or later

## Installation

Fire up composer:

```bash
composer require darkghosthunter/laralocker
```

## When you should use Laralocker?

Anything that has **[race conditions](https://en.wikipedia.org/wiki/Race_condition)**.

For example, let's say we need to create a sequential serial key for a sold Ticket, like `AAAA-BBBB-CCCC`. This is done by a Job pushed to the queue. This introduces three problems:
 
* If two or more jobs started at the same time, these would check the last sold also at the same time, and **save the next Ticket with the same serial key**.
* If we use [Pessimistic Locking](https://laravel.com/docs/8.x/queries#pessimistic-locking) in our queue, we can be victims of [deadlocks](https://en.wikipedia.org/wiki/Deadlock) (a locked row cannot be modified _forever_).
* If we have one Queue Worker, it will only process one Ticket at a time. When a flood of users buy 1,000 tickets in one minute, a single Queue Worker will take its sweet time to process all. The Concert starts in five minutes, hope your CPU is a top of the line AMD EPYC!

Using this package, all Tickets can be dispatched concurrently without fear of collisions, just by reserving a _slot_ (like and ID) for processing.

## How it works

This package allows your Job, Listener or Notification to be `Lockable`. With just adding three lines of code, the Job will *look ahead* for a free "slot", and reserve it.

> For sake of simplicity, I will treat Notifications and Listeners as a Jobs, since all of these can be pushed to the Queue.

Once the Job finishes processing, it will release the "slot", and mark that slot as the starting point for the next Jobs, so they don't look ahead from the very beginning.

This is useful when your Jobs needs sequential data: Serial keys, result of calculations, timestamps, you name it.

## Usage

1. Add the `Lockable` interface to your Job, Notification or Listener.
2. Add the `HandlesLock` trait.
3. Add the `LockerJobMiddleware` middleware.
4. Implement the `startFrom()` and `next()`.

> Job Middleware only runs when the Job implements the `ShouldQueue` interface. If you need your job to run in the same process without bypassing the middleware, use `dispatch_sync()` or [`MyJob::dispatchSync()`](https://laravel.com/docs/8.x/queues#synchronous-dispatching).

## Example

Here is a full example of a simple Listener that handles Serial Keys when a Ticket is sold for a given Concert to a given User. Once done, the user will be able to print his ticket and use it on the Concert premises to enter.

```php
<?php

namespace App\Listeners;

use App\Ticket;
use App\Events\TicketSold;
use App\Notifications\TicketAvailableNotification;
use DarkGhostHunter\Laralocker\Contracts\Lockable;
use DarkGhostHunter\Laralocker\LockerJobMiddleware;
use DarkGhostHunter\Laralocker\HandlesLockerSlot;
use Illuminate\Contracts\Queue\ShouldQueue;
use SerialGenerator\SerialGenerator;

class CreateTicket implements ShouldQueue, Lockable
{
    use HandlesLockerSlot;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        return [new LockerJobMiddleware()];
    }

    /**
     * Return the starting slot for the Jobs.
     *
     * @return mixed
     */
    public function startFrom()
    {
        // Get the latest stored ticket serial key.
        return Ticket::latest()->value('serial_key');
    }

    /**
     * The next slot to check for availability.
     *
     * @param mixed $slot
     * @return mixed
     */
    public function next($slot)
    {
        // Ask our hypothetical generator to create a new serial from that.
        return SerialGenerator::baseSerial($slot)->getNextSerial();
    }

    /**
     * Handle the event.
     *
     * @param \App\Listeners\TicketSold $event
     * @return void
     */
    public function handle(TicketSold $event)
    {
        // Create a new Ticket instance with this locked slot value.  
        $ticket = Ticket::make([
            'serial_key' => $this->slot,
        ]);

        // Associate the Ticket to the Concert and the User 
        $ticket->concert()->associate($event->concert);
        $ticket->user()->associate($event->user);

        // Save the Ticket into the system
        $ticket->save();

        // Notify the user that his ticket bought is available
        $event->user->notify(
            new TicketAvailableNotification($ticket)        
        );
    }
}
```

Let's start checking what each method does.

### `startFrom()`

When the Job asks where to start, this will be used to get the "last slot" used. If it's the first, it's fine to return `null`.

Once this starting point is retrieved, the Locker will save it in the Cache. Subsequent calls to the starting point will be use the Cache instead of executing this method in each Job.

This is used only when the first Job hits the queue, or if the cache returns null (maybe because you flushed it).

> You should return a string, or an [object instance that can be represented as a string](https://www.php.net/manual/en/language.oop5.magic.php#object.tostring).

### `next($slot)`

After retrieving the starting slot, the Queue Worker will put it into this method to get the next slot that should be free to reserve by the next job. It may receive anything you set, even `null`.

If the next slot was already "reserved" by another Job, it will recursively call `next($slot)` until it finds one that is not.

> For example, if your initial slot is `null`, the method will receive `null`, add ten and then return `10`. The Locker will check if `10` is reserved, and if it's not free, then it calls `next($slot)` again but using `10`, and so on, until it finds one that is not reserved, like `60`.

### `cache()` (optional)

This is entirely optional. If you want that particular Job to use another Cache store, you can return it here. Just remember to [have properly configured the Cache driver](https://laravel.com/docs/cache#driver-prerequisites) you want to use in your application beforehand.

If your cache is compatible with tagging, like `redis` and `memcached`, you can set your tag here transparently. This allows you to flush a tag if something goes wrong, or have more granular control on it.

```php
/**
 * Use a non-default Cache repository for handling slots (optional)
 *
 * @return \Illuminate\Contracts\Cache\Repository
 */
public function cache()
{
    return Cache::store('redis')->tag('tickets_queue');
}
```

### `$slotTtl` (optional)

Also, entirely optional. Slots are reserved in the Cache by 60 seconds as default. You can set a bigger _ttl_ if your Job takes its sweet time, like 10 minutes.

Is always recommended setting a maximum to avoid slot creeping in your Cache store.

```php
/**
 * Maximum Slot reservation time
 *
 * @var \Illuminate\Support\Carbon|int
 */
public $slotTtl = 180;
```

> If you don't use `$slotTtl`, the Locker will automatically get it from the `$timeout`, `retryUntil()`, or the default from the config file, in that order.

### `$prefix` (optional)

Also optional, this manages the prefix that it will be used for the slot reservations for the Job, avoiding clashing with other Cache keys.

```php
/**
 * Prefix for slots reservations
 *
 * @var string
 */
public string $prefix = 'ticket_locking';
```

## Configuration

Laralocker works hands-off, but if you need to change the default configuration, just publish the config file.

```bash
php artisan vendor:publish --provider=DarkGhostHunter\Laralocker\LaralockerServiceProvider --tag="config"
```

You will get a `laralocker.php` file in your config directory with the following contents:

```php
<?php 
return [
    'cache' => null,
    'prefix' => 'queue_locker',
    'ttl' => 60,
];
```

The contents are pretty much self-explanatory, but let's describe them one by one.

### Cache

```php
return [
    'cache' => 'redis',
];
```

Laralocker uses the default Cache of your application when this is set to `null`. On fresh Laravel installations, it's the `file` store.
 
If you need high performance, you may want to switch to `redis`, `sqs`, `memecached` or whatever you have available for your application. This must be one of your `stores` described in your `config/cache.php` file. 

### Prefix
```php
return [
    'prefix' => 'app_slots_queue',
];
```

To avoid collision with other Cache keys, Laralock will prefix the slots with a string. If for any reason you're using this prefix in your application, you may want to change it.

### Slot Reservation Time-to-Live

```php
return [
    'ttl' => 300,
];
```

Slots reserved in the cache always have a maximum time to live, which after that are automatically freed. This is a mechanism to avoid creeping the Cache with zombie reservations.

Of course some Jobs may take its while to process. You may want to extend this to a safe value if your Jobs may take much time.

## Releasing and Clearing slots

When a Job fails, the `releaseSlot()` isn't called. This will allow to NOT update the last slot if the job fails, and will leave the slot reserved until it expires. 

If you release a Job doesn't use the `Queueable` trait, be sure to call `clearSlot()` when your job fails. This will delete the slot reservation so other Jobs can reserve it.

## Detailed inner workings

Curious about how this works? Fasten your seatbelts:

When *handling* the Job, the Job will pass itself to the Locker. This class will check what was the last slot used for the Job using the Cache.

If there is no last slot used (because is the first in the queue, or the Cache was flushed), it will call `startFrom()` and save what it returns into slot into the Cache, forever, to avoid calling `startFrom()` every time.

Next, the Locker will pass the initial slot to `next($slot)`, and then check if the resulted slot is free. It will recursively call `next($slot)` until a non-reserved slot is found.

Once found, the Locker will reserve it using the Cache with a save Time-To-Live for the Cache key to avoid keeping zombie reservations in the Cache.

The Locker will copy the used slot inside the `$slot` property of the Job, and then the Job keep executing. That way, the developer can use the slot inside the Job (like in our Ticket example).

Once the Job calls `releaseSlot()`, the Locker will save the `$slot` as the last slot used in the Cache, forever. This will allow other Jobs to start from that slot, instead of checking from the very first slot and encounter unreserved slots that expired in the Cache.

If the Job fails, no "last slot" will be updated, and the slot will stay reserved until it expires.

If the slot was already saved as the last, it will compare the timestamp from when the Job was started, and update it only if its more recent. This allows to NOT save a slot that is "older", allowing the slots to keep going forward.

Finally, it will "release" the current reserved slot from the reservation pool in the Cache, avoiding zombie keys into the cache.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

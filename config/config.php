<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lock handler
    |--------------------------------------------------------------------------
    |
    | We need to use your application cache to save the lock information of
    | each job. The default option is the default cache of your app, but
    | you can set other connections as long they're registered inside.
    |
    */

    'cache' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Prefix
    |--------------------------------------------------------------------------
    |
    | To avoid collision in your application cache, the locker will use the
    | following prefix for all locking keys. While this string keeps it
    | simple, you may want to change it if you're already using it.
    |
    */

    'prefix' => 'queue_locker',

    /*
    |--------------------------------------------------------------------------
    | Reservation Time to Live
    |--------------------------------------------------------------------------
    |
    | Slot reservation is 60 seconds, but you can change the default time to
    | live here. You can set this for all Lockable Jobs, Notifications or
    | Listeners, and then set one for each one of these if you need it.
    |
    */
    'ttl' => 60,
];

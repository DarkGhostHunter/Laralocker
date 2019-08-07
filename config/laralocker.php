<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lock Cache handler
    |--------------------------------------------------------------------------
    |
    | We need to use your application default Cache to save information about
    | the lock in each Job. You can change this default to any you want as
    | long the connection is set and configured inside your application.
    |
    */

    'cache' => null,

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

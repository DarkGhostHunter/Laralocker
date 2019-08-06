<?php

namespace DarkGhostHunter\Laralocker;

use Closure;
use DarkGhostHunter\Laralocker\Contracts\Lockable;
use Throwable;

class LockerJobMiddleware
{
    /**
     * Handle the command being dispatched
     *
     * @param \DarkGhostHunter\Laralocker\Contracts\Lockable $command
     * @param Closure $next
     * @return mixed
     * @throws \Throwable
     */
    public function handle(Lockable $command, Closure $next)
    {
        $command->lock();

        try {
            $result = $next($command);
        } catch (Throwable $throwable) {
            $command->clear();
            throw $throwable;
        }

        $command->release();

        return $result;
    }
}

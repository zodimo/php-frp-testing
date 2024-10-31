<?php

declare(strict_types=1);

namespace Zodimo\FRPTesting;

use Psr\EventDispatcher\ListenerProviderInterface;

class FrpListenerProvider implements ListenerProviderInterface
{
    /**
     * @var array<string,array<callable>>
     */
    public array $listeners = [];

    /**
     * @param object $event An event for which to return the relevant listeners
     *
     * @return iterable<callable> An iterable (array, iterator, or generator) of callables.  Each
     *                            callable MUST be type-compatible with $event.
     */
    public function getListenersForEvent($event): iterable
    {
        foreach ($this->listeners as $eventClass => $listeners) {
            if (is_a($event, $eventClass)) {
                return $listeners;
            }
        }

        return [];
    }

    public function on(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }
}

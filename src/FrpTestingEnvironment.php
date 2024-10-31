<?php

declare(strict_types=1);

namespace Zodimo\FRPTesting;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Zodimo\FRP\Listeners\FrpRuntimeListener;
use Zodimo\FRP\Listeners\ListenerInterface;
use Zodimo\FRP\Runtime;

class FrpTestingEnvironment
{
    public ContainerInterface $container;
    public Runtime $runtime;

    public function __construct(
        ContainerInterface $container,
        Runtime $runtime
    ) {
        $this->container = $container;
        $this->runtime = $runtime;
    }

    /**
     * @param array<ListenerInterface> $listeners
     */
    public static function create(array $listeners = []): FrpTestingEnvironment
    {
        $wireUpListener = function (ListenerInterface $listener, FrpListenerProvider $listenerProvider): void {
            foreach ($listener->listen() as $eventClass) {
                $listenerProvider->on($eventClass, [$listener, 'process']);
            }
        };

        $listenerProvider = new FrpListenerProvider();
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions(
            [
                EventDispatcherInterface::class => function (ContainerInterface $container) use ($listenerProvider) {
                    $dispatcher = new EventDispatcher();
                    foreach ($listenerProvider->listeners as $event => $listeners) {
                        foreach ($listeners as $listener) {
                            $dispatcher->addListener($event, $listener);
                        }
                    }

                    return $dispatcher;
                },
            ]
        );
        $container = $containerBuilder->build();

        $runtime = new Runtime();

        $allListeners = [
            new FrpRuntimeListener($runtime),
            ...$listeners,
        ];

        foreach ($allListeners as $listener) {
            $wireUpListener($listener, $listenerProvider);
        }

        return new self(
            $container,
            $runtime,
        );
    }
}

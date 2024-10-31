<?php

declare(strict_types=1);

namespace Zodimo\FRPTesting;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Zodimo\FRP\Listeners\FrpRuntimeListener;
use Zodimo\FRP\Listeners\ListenerInterface;

class FrpTestingEnvironment
{
    public ContainerInterface $container;

    public function __construct(
        ContainerInterface $container
    ) {
        $this->container = $container;
    }

    /**
     * @param array<class-string<ListenerInterface>> $listeners
     */
    public static function create(array $listeners = []): FrpTestingEnvironment
    {
        $containerBuilder = new ContainerBuilder();
        $allListeners = [
            FrpRuntimeListener::class,
            ...$listeners,
        ];

        $containerBuilder->addDefinitions(
            [
                EventDispatcherInterface::class => function (ContainerInterface $container) use ($allListeners) {
                    $listenerProvider = new FrpListenerProvider();
                    $wireUpListener = function (ListenerInterface $listener, FrpListenerProvider $listenerProvider): void {
                        foreach ($listener->listen() as $eventClass) {
                            $listenerProvider->on($eventClass, [$listener, 'process']);
                        }
                    };

                    $dispatcher = new EventDispatcher();

                    foreach ($allListeners as $listener) {
                        $wireUpListener($container->get($listener), $listenerProvider);
                    }

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

        return new self(
            $container,
        );
    }
}

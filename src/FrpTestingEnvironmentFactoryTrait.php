<?php

declare(strict_types=1);

namespace Zodimo\FRPTesting;

use Zodimo\FRP\Listeners\ListenerInterface;

trait FrpTestingEnvironmentFactoryTrait
{
    /**
     * @param array<class-string<ListenerInterface>> $listeners
     */
    public function createFrpTestEnvironment(array $listeners = []): FrpTestingEnvironment
    {
        return FrpTestingEnvironment::create($listeners);
    }
}

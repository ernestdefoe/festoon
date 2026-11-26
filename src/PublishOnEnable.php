<?php

namespace Ernestdefoe\Festoon;

use Ernestdefoe\Festoon\Style\Publisher;
use Flarum\Extend\ExtenderInterface;
use Flarum\Extend\LifecycleInterface;
use Flarum\Extension\Extension;
use Illuminate\Contracts\Container\Container;

/**
 * Publishes the decoration files the moment Festoon is enabled. An event
 * listener can't do it: listeners are only registered for extensions that
 * were already enabled when the request began.
 */
class PublishOnEnable implements ExtenderInterface, LifecycleInterface
{
    public function extend(Container $container, ?Extension $extension = null): void
    {
        // Nothing to register; this extender only acts on enable.
    }

    public function onEnable(Container $container, Extension $extension): void
    {
        $container->make(Publisher::class)->publishAll(true);
    }

    public function onDisable(Container $container, Extension $extension): void
    {
        // The files stay: unlinked, harmless, and replaced on the next enable.
    }
}

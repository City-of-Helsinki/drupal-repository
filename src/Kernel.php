<?php

declare(strict_types = 1);

namespace App;

use App\Commands\AutomationPullRequestChangelog;
use App\Commands\Consumer;
use App\Commands\PackageIndexQueue;
use App\Commands\ReleaseChangelog;
use App\Commands\TriggerDispatchEvent;
use DI\Container;
use Symfony\Component\Console\Application;

class Kernel
{
    public static function boot(Container $container): Application
    {
        $application = new Application();
        $application->addCommand($container->get(Consumer::class));
        $application->addCommand($container->get(PackageIndexQueue::class));
        $application->addCommand($container->get(ReleaseChangelog::class));
        $application->addCommand($container->get(AutomationPullRequestChangelog::class));
        $application->addCommand($container->get(TriggerDispatchEvent::class));
        return $application;
    }
}

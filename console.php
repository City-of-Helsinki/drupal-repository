<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use App\Kernel;
use DI\ContainerBuilder;

$appEnv = getenv('APP_ENV');
$builder = new ContainerBuilder();
$builder->useAttributes(true);
$builder->addDefinitions(__DIR__ . '/config.php');

if (!$appEnv || $appEnv === 'prod') {
    $builder->enableCompilation('/tmp');
    $builder->writeProxiesToFile(true, '/tmp/proxies');
}
$container = $builder->build();

$application = Kernel::boot($container);
$application->run();

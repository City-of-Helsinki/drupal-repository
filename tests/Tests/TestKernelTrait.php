<?php

declare(strict_types=1);

namespace App\Tests;

use App\Kernel;
use App\Settings;
use DI\Container;
use DI\ContainerBuilder;
use Enqueue\Fs\FsConnectionFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Interop\Queue\Context;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\CacheInterface;

trait TestKernelTrait
{

    public function getContainer(array|string $settings): Container
    {
        $container = new ContainerBuilder();
        $container->useAttributes(true);
        $container->addDefinitions([
            CacheInterface::class => new FilesystemAdapter(defaultLifetime: 60),
            Context::class => (new FsConnectionFactory())->createContext(),
        ]);
        $container->addDefinitions($settings);
        return $container->build();
    }

    /**
     * Creates HTTP history middleware client stub.
     *
     * @param array $container
     *   The container.
     * @param \Psr\Http\Message\ResponseInterface[]|\GuzzleHttp\Exception\GuzzleException[] $responses
     *   The expected responses.
     *
     * @return \GuzzleHttp\Client
     *   The client.
     */
    protected function createMockHistoryMiddlewareHttpClient(array &$container, array $responses = []) : Client
    {
        $history = Middleware::history($container);
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);

        return new Client(['handler' => $handlerStack]);
    }

    /**
     * Creates HTTP client stub.
     *
     * @param \Psr\Http\Message\ResponseInterface[]|\GuzzleHttp\Exception\GuzzleException[] $responses
     *   The expected responses.
     *
     * @return \GuzzleHttp\Client
     *   The client.
     */
    protected function createMockHttpClient(array $responses) : Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);

        return new Client(['handler' => $handlerStack]);
    }
}

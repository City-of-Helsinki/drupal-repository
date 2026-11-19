<?php

declare(strict_types=1);

namespace App\Tests;

use App\Kernel;
use App\Queue\Package;
use App\ReleaseNoteGeneratorInterface;
use App\Settings;
use DI\ContainerBuilder;
use Enqueue\Fs\FsConnectionFactory;
use Github\Api\Repo;
use Github\Client;
use Interop\Queue\Context;
use Interop\Queue\Message;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Runs the commands from hooks.json through the real console application.
 *
 * HooksTest checks which arguments each hook passes. This checks that those
 * arguments reach the right code, using the real config.php with GitHub and
 * the queue replaced.
 */
final class HookCommandsTest extends TestCase
{
    use ProphecyTrait;

    private string $queueDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queueDirectory = sys_get_temp_dir() . '/hook-commands-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->queueDirectory);
        parent::tearDown();
    }

    /**
     * Runs a command with the given container definitions replaced.
     *
     * @param array<string, mixed> $definitions
     * @param list<string> $argv
     */
    private function runCommand(array $definitions, array $argv): void
    {
        $builder = new ContainerBuilder();
        $builder->useAttributes(true);
        $builder->addDefinitions(__DIR__ . '/../../config.php');
        $builder->addDefinitions($definitions);

        $application = Kernel::boot($builder->build());
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $output = new BufferedOutput();
        $this->assertSame(Command::SUCCESS, $application->run(new ArgvInput($argv), $output), $output->fetch());
    }

    public function testAutomationPullRequestChangelog(): void
    {
        $generator = $this->prophesize(ReleaseNoteGeneratorInterface::class);
        $generator
            ->updateChangelogForPullRequest(
                'city-of-helsinki',
                'drupal-helfi-kymp',
                'dev',
                'update-configuration',
                '123',
            )
            ->shouldBeCalledOnce();

        $this->runCommand(
            [ReleaseNoteGeneratorInterface::class => $generator->reveal()],
            HooksTest::CHANGELOG_COMMAND,
        );
    }

    public function testReleaseNote(): void
    {
        $generator = $this->prophesize(ReleaseNoteGeneratorInterface::class);
        $generator
            ->updateChangelogForRelease('city-of-helsinki', 'drupal-helfi-kymp', '2026-11-05.1')
            ->shouldBeCalledOnce();

        $this->runCommand(
            [ReleaseNoteGeneratorInterface::class => $generator->reveal()],
            HooksTest::RELEASE_NOTE_COMMAND,
        );
    }

    public function testDispatch(): void
    {
        $config = require __DIR__ . '/../../config.php';
        $projects = count($config[Settings::DISPATCH_TRIGGER]['config-update']);
        $this->assertGreaterThan(0, $projects);

        $repo = $this->prophesize(Repo::class);
        $repo->dispatch(Argument::any(), Argument::any(), 'config_change', Argument::any())
            ->shouldBeCalledTimes($projects);
        $repo->dispatch('city-of-helsinki', 'drupal-helfi-kymp', 'config_change', Argument::any())
            ->shouldBeCalledOnce();
        $client = $this->prophesize(Client::class);
        $client->authenticate(Argument::cetera())->shouldBeCalled();
        $client->repo()->willReturn($repo->reveal());

        $this->runCommand([Client::class => $client->reveal()], HooksTest::DISPATCH_COMMAND);
    }

    public function testUpdateIndex(): void
    {
        $context = (new FsConnectionFactory('file://' . $this->queueDirectory))->createContext();

        $this->runCommand([Context::class => $context], HooksTest::INDEX_COMMAND);

        $message = $context
            ->createConsumer($context->createQueue(Settings::PACKAGE_QUEUE))
            ->receiveNoWait();
        $this->assertInstanceOf(Message::class, $message);
        $this->assertSame('drupal/helfi_api_base', $message->getBody());
        $this->assertSame(Package::class, $message->getProperty('instance'));
    }
}

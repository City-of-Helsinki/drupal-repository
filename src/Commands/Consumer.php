<?php

declare(strict_types=1);

namespace App\Commands;

use App\Queue\WorkerInterface;
use DI\Container;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'queue:consume'
)]
final class Consumer extends Command
{
    private bool $continueProcessing = true;

    public function __construct(
        private readonly Context $queueContext,
        private readonly Container $container,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();
        $this->addArgument(
            'queue',
            InputArgument::REQUIRED,
            'The queue.',
        );
        $this->addOption(
            'item-limit',
            '-i',
            InputOption::VALUE_REQUIRED,
            'The number of items to process before exiting.',
            100,
        );
    }

    private function processMessage(Message $message, OutputInterface $output) : void
    {
        $service = $message->getProperty('instance');

        if (!$this->container->has($service)) {
            throw new \InvalidArgumentException('Failed to parse worker instance.');
        }
        /** @var \App\Queue\WorkerInterface $worker */
        $worker = $this->container->get($service);
        $worker->process($message, $output);
    }

    private function stopProcessing() : void
    {
        $this->continueProcessing = false;
    }

    public function execute(InputInterface $input, OutputInterface $output) : int
    {
        $itemLimit = $input->getOption('item-limit');
        assert(is_int($itemLimit));

        $consumer = $this->queueContext
            ->createConsumer(
                $this->queueContext->createQueue($input->getArgument('queue'))
            );

        $count = 0;
        while ($this->continueProcessing) {
            $message = $consumer->receive(5000);

            if (!$message instanceof Message) {
                // Sleep for 1 sec between cycles.
                time_nanosleep(0, 10000000);

                continue;
            }
            $this->processMessage($message, $output);
            $count++;

            if ($count >= $itemLimit) {
                $this->stopProcessing();
            }
        }
        $output->writeln("Processed {$count} items.");
        return Command::SUCCESS;
    }
}

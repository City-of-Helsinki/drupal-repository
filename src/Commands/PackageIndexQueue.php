<?php

declare(strict_types=1);

namespace App\Commands;

use App\PackageTrait;
use App\Queue\Package;
use App\Settings;
use DI\Attribute\Inject;
use Interop\Queue\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'queue:package'
)]
final class PackageIndexQueue extends Command
{
    use PackageTrait;

    public function __construct(
        private readonly Context $queueContext,
        #[Inject(Settings::PACKAGES_LIST)] protected array $packages,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addArgument(
            'package',
            InputArgument::REQUIRED,
            'The package to update. Enter "all" if you wish to rebuild entire index.'
        );
    }

    public function execute(InputInterface $input, OutputInterface $output) : int
    {
        $package = $input->getArgument('package');

        $this->queueContext->createProducer()->send(
            $this->queueContext->createQueue(Settings::PACKAGE_QUEUE),
            $this->queueContext->createMessage(
                $this->getPackageName($package),
                ['instance' => Package::class],
            )
        );
        return Command::SUCCESS;
    }
}

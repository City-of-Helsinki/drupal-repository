<?php

declare(strict_types=1);

namespace App\Commands;

use App\Settings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

abstract class Base extends Command
{

    public function __construct(protected Settings $settings)
    {
        parent::__construct();
    }

    protected function outputCallback(OutputInterface $output) : callable
    {
        return function ($type, $buffer) use ($output) {
            $output->write($buffer);
        };
    }

    protected function ensureInstallation(OutputInterface $output) : void
    {
        (new Process(['/usr/bin/make']))
            ->run($this->outputCallback($output));
    }
}

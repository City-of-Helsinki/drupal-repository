<?php

declare(strict_types=1);

namespace App\Queue;

use Interop\Queue\Message;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final class Package implements WorkerInterface
{

    public function process(Message $message, OutputInterface $output): void
    {
        $args = [
            '/usr/bin/php',
            '-dmemory_limit=-1',
            'vendor/bin/satis',
            'build',
            'satis.json',
            'dist',
        ];
        $package = $message->getBody();

        if (!empty($package)) {
            $args[] = $package;
        }
        $output->writeln(sprintf('Running: "%s"', implode(' ', $args)));

        $process = (new Process($args))
            ->setTimeout(3600);
        $process->run(fn ($type, $buffer) => $output->write($buffer));
    }
}

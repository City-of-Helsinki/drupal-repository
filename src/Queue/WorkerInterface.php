<?php

declare(strict_types=1);

namespace App\Queue;

use Interop\Queue\Message;
use Symfony\Component\Console\Output\OutputInterface;

interface WorkerInterface
{
    public function process(Message $message, OutputInterface $output);
}

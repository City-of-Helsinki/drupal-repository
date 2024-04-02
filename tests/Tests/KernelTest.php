<?php

declare(strict_types=1);

namespace App\Tests;

use App\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;

class KernelTest extends TestCase
{
    use TestKernelTrait;

    public function testKernelBoot(): void
    {
        $application = Kernel::boot($this->getContainer(__DIR__ . '/../../config.php'));
        $this->assertInstanceOf(Application::class, $application);
    }
}

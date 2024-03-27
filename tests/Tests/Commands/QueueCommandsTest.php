<?php

declare(strict_types=1);

namespace App\Tests\Commands;

use App\Commands\Consumer;
use App\Commands\PackageIndexQueue;
use App\Settings;
use App\Tests\TestKernelTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Filesystem;

class QueueCommandsTest extends TestCase
{
    use ProphecyTrait;
    use TestKernelTrait;

    public const TEST_FILENAME = 'dist/p2/drupal/helfi_api_base.json';

    protected function setUp(): void
    {
        parent::setUp();

        $fs = new Filesystem();
        $fs->remove(self::TEST_FILENAME);
    }


    public function testExecute(): void
    {
        $this->assertFileDoesNotExist(self::TEST_FILENAME);
        $container = $this->getContainer([
            Settings::PACKAGES_LIST => [
                'drupal/helfi_api_base' => (object) ['name' => 'drupal/helfi_api_base'],
            ],
        ]);
        $application = new Application();
        $application->add($container->get(PackageIndexQueue::class));
        $application->add($container->get(Consumer::class));
        $application->setAutoExit(false);
        $applicationTest = new ApplicationTester($application);
        $applicationTest->run(['queue:package', 'package' => 'drupal/helfi_api_base']);
        $applicationTest->run([
            'queue:consume',
            'queue' => Settings::PACKAGE_QUEUE,
            '--item-limit' => 1,
        ]);
        $output = $applicationTest->getDisplay();
        $this->assertStringContainsString('Processed 1 items.', $output);
        $this->assertFileExists(self::TEST_FILENAME);
    }
}

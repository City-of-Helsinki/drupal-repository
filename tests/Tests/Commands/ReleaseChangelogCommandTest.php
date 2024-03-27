<?php

declare(strict_types=1);

namespace App\Tests\Commands;

use App\Commands\ReleaseChangelog;
use App\Settings;
use App\Tests\TestKernelTrait;
use DI\DependencyException;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

class ReleaseChangelogCommandTest extends TestCase
{
    use ProphecyTrait;
    use TestKernelTrait;

    /**
     * @dataProvider changelogArgumentExceptionData
     */
    public function testReleaseChangeLogExceptions(
        array $settings,
        string $expectedExceptionMessage,
        string $expectedException
    ): void {
        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $container = $this->getContainer($settings);
        $application = new Application();
        $application->add($container->get(ReleaseChangelog::class));
        $application->setAutoExit(false);
        $applicationTest = new ApplicationTester($application);
        $applicationTest->run(['changelog:project-release']);
    }

    public function changelogArgumentExceptionData(): array
    {
        return [
            [
                [],
                "No entry or class found for '" . Settings::GITHUB_OAUTH . "'",
                DependencyException::class,
            ],
            [
                [Settings::GITHUB_OAUTH => '123'],
                "No entry or class found for '" . Settings::CHANGELOG_ALLOWED_PACKAGES . "'",
                DependencyException::class,
            ],
            [
                [Settings::GITHUB_OAUTH => '123', Settings::CHANGELOG_ALLOWED_PACKAGES => []],
                "No entry or class found for '" . Settings::CHANGELOG_PROJECTS . "'",
                DependencyException::class,
            ],
        ];
    }

    /**
     * @dataProvider requiredArgumentsData
     */
    public function testRequiredArguments(array $input, string $expectedOutput): void
    {
        $container = $this->getContainer([
            Settings::GITHUB_OAUTH => '123',
            Settings::PACKAGES_LIST => [],
            Settings::CHANGELOG_ALLOWED_PACKAGES => [],
            Settings::CHANGELOG_PROJECTS => [],
        ]);
        $application = new Application();
        $application->add($container->get(ReleaseChangelog::class));
        $application->setAutoExit(false);
        $applicationTest = new ApplicationTester($application);
        $applicationTest->run($input, [
            'capture_stderr_separately' => true,
        ]);
        $output = $applicationTest->getErrorOutput();
        $this->assertStringContainsString($expectedOutput, $output);
    }

    public function requiredArgumentsData(): array
    {
        return [
            [
                ['changelog:project-release'],
                'Missing required "project" option.',
            ],
            [
                ['changelog:project-release', '--project' => 'test'],
                'Missing required "base" option.',
            ],
        ];
    }
}

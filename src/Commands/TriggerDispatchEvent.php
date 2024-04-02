<?php

declare(strict_types=1);

namespace App\Commands;

use App\Settings;
use DI\Attribute\Inject;
use Github\AuthMethod;
use Github\Client;
use Github\Exception\ExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:dispatch',
)]
final class TriggerDispatchEvent extends Command
{
    public function __construct(
        private readonly Client $client,
        #[Inject(Settings::DISPATCH_TRIGGER)] private readonly array $triggers,
        #[Inject(Settings::GITHUB_OAUTH)] private readonly string $authToken,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addArgument(
            'workflowId',
            InputArgument::REQUIRED,
            'The workflow to dispatch'
        );
    }

    public function execute(InputInterface $input, OutputInterface $output) : int
    {
        $workflowId = $input->getArgument('workflowId');

        if (!isset($this->triggers[$workflowId])) {
            throw new \InvalidArgumentException('Settings for given workflowId not found.');
        }

        $this->client->authenticate(
            $this->authToken,
            authMethod: AuthMethod::ACCESS_TOKEN
        );

        $exception = null;
        foreach ($this->triggers[$workflowId] as $setting) {
            [
                'username' => $username,
                'repository' => $repository,
            ] = $setting;

            try {
                $this->client->repo()->dispatch($username, $repository, 'config_change', [
                  'time' => time()
                ]);
            } catch (ExceptionInterface $exception) {
                $output->writeln(
                    vsprintf('[Github error] Dispatch failed for: %s/%s. See %s for more information.', [
                      $username,
                      $repository,
                      // phpcs:ignore
                      'https://github.com/City-of-Helsinki/drupal-helfi-platform/blob/main/documentation/automatic-updates.md#automatically-trigger-config-update-on-all-whitelisted-projects'
                    ])
                );
            } catch (\Exception $exception) {
                $output->writeln(
                    sprintf('[General error] Dispatch failed for: %s/%s', $username, $repository)
                );
            }
        }

        // Allow individual repositories to fail, but catch the latest exception and re-throw it.
        if ($exception) {
            throw $exception;
        }
        return Command::SUCCESS;
    }
}

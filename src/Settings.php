<?php

declare(strict_types=1);

namespace App;

final class Settings
{
    public const DISPATCH_TRIGGER = 'dispatch-triggers';
    public const GITHUB_OAUTH = 'GITHUB_OAUTH';
    public const ENV = 'APP_ENV';
    public const CHANGELOG_PROJECTS = 'projects';
    public const CHANGELOG_ALLOWED_PACKAGES = 'packages';

    public function __construct(private array $config)
    {
    }

    public function get(string $name) : array|string
    {
        if (!isset($this->config[$name])) {
            throw new \InvalidArgumentException("Configuration $name does not exist.");
        }
        return $this->config[$name];
    }
}

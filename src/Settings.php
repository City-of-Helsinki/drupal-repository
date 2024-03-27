<?php

declare(strict_types=1);

namespace App;

final class Settings
{
    public const PACKAGE_QUEUE = 'package-queue';
    public const DISPATCH_TRIGGER = 'setting.dispatch-triggers';
    public const GITHUB_OAUTH = 'setting.github_oauth';
    public const ENV = 'setting.env';
    public const CHANGELOG_PROJECTS = 'setting.changelog-projects';
    public const CHANGELOG_ALLOWED_PACKAGES = 'setting.changelog-allowed-packages';
    public const PACKAGES_LIST = 'setting.packages-list';
}

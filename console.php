<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use App\Kernel;
use App\Settings;

// The 'dispatch-triggers' setting should contain all triggers that can be triggered for project.
// For example, add 'config-update', if you wish 'app:dispatch' command to trigger 'config-update'
// event for given project.
// Set 'changelog = true' if you wish to generate automatic release changelogs.
$projects = [
    [
        'username' => 'city-of-helsinki',
        'repository' => 'drupal-helfi-kymp',
        'dispatch-triggers' => ['config-update'],
        'changelog' => true,
    ],
    [
        'username' => 'city-of-helsinki',
        'repository' => 'drupal-helfi-sote',
        'dispatch-triggers' => ['config-update'],
        'changelog' => true,
    ],
    [
        'username' => 'city-of-helsinki',
        'repository' => 'drupal-helfi-strategia',
        'dispatch-triggers' => ['config-update'],
        'changelog' => true,
    ],
    [
        'username' => 'city-of-helsinki',
        'repository' => 'drupal-helfi-tyo-yrittaminen',
        'dispatch-triggers' => ['config-update'],
        'changelog' => true,
    ],
    [
        'username' => 'city-of-helsinki',
        'repository' => 'drupal-helfi-kasvatus-koulutus',
        'dispatch-triggers' => ['config-update'],
        'changelog' => true,
    ],
    [
        'username' => 'city-of-helsinki',
        'repository' => 'drupal-helfi-asuminen',
        'dispatch-triggers' => ['config-update'],
        'changelog' => true,
    ],
    [
        'username' => 'city-of-helsinki',
        'repository' => 'drupal-helfi-etusivu',
        'dispatch-triggers' => ['config-update'],
        'changelog' => true,
    ],
    [
        'username' => 'city-of-helsinki',
        'repository' => 'drupal-helfi-kuva',
        'dispatch-triggers' => ['config-update'],
        'changelog' => true,
    ],
    [
        'username' => 'city-of-helsinki',
        'repository' => 'drupal-helfi-rekry',
        'dispatch-triggers' => ['config-update'],
        'changelog' => true,
    ],
    [
        'username' => 'city-of-helsinki',
        'repository' => 'drupal-helfi-form-tool',
        'dispatch-triggers' => ['config-update'],
        'changelog' => false,
    ],
    [
        'username' => 'city-of-helsinki',
        'repository' => 'hel-fi-drupal-grants',
        'dispatch-triggers' => ['config-update'],
        'changelog' => false,
    ],
    [
        'username' => 'city-of-helsinki',
        'repository' => 'drupal-emergency-site',
        'dispatch-triggers' => ['config-update'],
        'changelog' => false,
    ],
    [
        'username' => 'city-of-helsinki',
        'repository' => 'helsinki-paatokset',
        'dispatch-triggers' => ['config-update'],
        'changelog' => false,
    ],
    [
        'username' => 'city-of-helsinki',
        'repository' => 'drupal-palvelukeskus',
        'dispatch-triggers' => ['config-update'],
        'changelog' => false,
    ],
];

$data = json_decode(file_get_contents('satis.json'));

$packages = [];
foreach ($data->repositories as $item) {
    $packages[$item->name] = $item;
}

$settings = new Settings([
    Settings::ENV => getenv('APP_ENV') ?: 'local',
    Settings::GITHUB_OAUTH => getenv(Settings::GITHUB_OAUTH),
    Settings::CHANGELOG_PROJECTS => array_filter($projects, function (array $item) : bool {
        return isset($item['changelog']) && $item['changelog'] === true;
    }),
    Settings::CHANGELOG_ALLOWED_PACKAGES => array_filter($packages, function (object $package) : bool {
        $isWhitelisted = !empty($package->extra->whitelisted);

        if ($isWhitelisted && !isset($package->extra->username, $package->extra->repository)) {
            throw new \InvalidArgumentException(
                sprintf('Missing required "repository" or "username" for %s', $package->name)
            );
        }
        return $isWhitelisted;
    }),
    Settings::DISPATCH_TRIGGER => [
        'config-update' => array_filter($projects, function (array $item) {
            return in_array('config-update', $item['dispatch-triggers']);
        }),
    ],
]);
$application = Kernel::boot($settings);
$application->run();

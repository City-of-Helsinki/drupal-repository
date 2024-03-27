<?php

declare(strict_types = 1);

namespace App;

use DI\Attribute\Inject;
use Github\AuthMethod;
use Github\Client;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class ReleaseNoteGenerator implements ReleaseNoteGeneratorInterface
{
    use MarkdownProcessorTrait;
    use CacheTrait;

    public function __construct(
        private readonly Client $client,
        private readonly CacheInterface $cache,
        #[Inject(Settings::GITHUB_OAUTH)] private readonly string $authToken,
        #[Inject(Settings::CHANGELOG_ALLOWED_PACKAGES)] private readonly array $allowedPackages,
    ) {
    }

    private function createNote(string $username, string $repository, array $version): ? string
    {
        $key = $this->getCacheKey('create-note', $username, $repository, $version['base'], $version['head']);

        return $this->cache->get($key, function (ItemInterface $item) use ($username, $repository, $version) {
            $this
                ->client
                ->authenticate($this->authToken, authMethod: AuthMethod::ACCESS_TOKEN);

            $note = $this
                ->client
                ->repos()
                ->releases()
                ->generateNotes($username, $repository, [
                    'previous_tag_name' => $version['base'],
                    'target_commitish' => $version['head'],
                    'tag_name' => $version['head'],
                ]);
            // Cache for 60 seconds, so we don't unnecessarily loop the GitHub API when
            // automation updates all our projects at once.
            $item->expiresAfter(60);

            return "## [$username/$repository](https://github.com/$username/$repository): " .
                "{$version['base']} to {$version['head']}\n" .
                // Convert previous h2 to h3.
                str_replace('##', '###', $note['body']) .
                "\n";
        });
    }

    private function getComposerPackageVersions(string $username, $repository, string $reference): array
    {
        $key = $this->getCacheKey('package-versions', $username, $repository, $reference);

        return $this->cache->get($key, function (ItemInterface $item) use ($username, $repository, $reference) {
            $data = $this->client
                ->repos()
                ->contents()
                ->rawDownload($username, $repository, 'composer.lock', $reference);
            $decoded = json_decode($data, true);

            $packages = [];
            foreach ($decoded['packages'] as $package) {
                [
                    'name' => $name,
                    'version' => $version,
                ] = $package + ['name' => null, 'version' => null];

                // Ignore non-whitelisted packages.
                if (!in_array($name, array_keys($this->allowedPackages))) {
                    continue;
                }
                $packages[$name] = $version;
            }
            // Cache for 60 seconds, so we don't unnecessarily loop the GitHub API when
            // automation updates all our projects at once.
            $item->expiresAfter(60);

            return $packages;
        });
    }

    private function getUpdatedDependencies(
        string $username,
        string $repository,
        string $base,
        string $head
    ): array {
        $previousVersions = $this->getComposerPackageVersions($username, $repository, $base);
        $latestVersions = $this->getComposerPackageVersions($username, $repository, $head);

        $diff = array_keys(array_diff($previousVersions, $latestVersions));

        $versions = [];
        foreach ($diff as $name) {
            $versions[$name] = [
                'base' => $previousVersions[$name],
                'head' => $latestVersions[$name],
            ];
        }
        return $versions;
    }

    private function hasChanges(string $username, string $repository, string $base, string $head): bool
    {
        $key = $this->getCacheKey('has-changes', $username, $repository, $base, $head);

        return $this->cache->get($key, function (ItemInterface $item) use ($username, $repository, $base, $head) {
            $compare = $this->client
                ->repos()
                ->commits()
                ->compare($username, $repository, $base, $head);

            $composerLockChanges = array_filter($compare['files'], function (array $file) {
                return $file['filename'] === 'composer.lock';
            });
            // Cache for 60 seconds, so we don't unnecessarily loop the GitHub API when
            // automation updates all our projects at once.
            $item->expiresAfter(60);

            return count($composerLockChanges) > 0;
        });
    }


    private function createChangelog(
        string $username,
        string $repository,
        string $previous,
        string $latest,
        bool $generateProjectChangelog,
    ) : ? string {
        if (!$this->hasChanges($username, $repository, $previous, $latest)) {
            return null;
        }

        $changelog = '';
        // This cannot be generated for automation pull requests because it uses
        // branches.
        if ($generateProjectChangelog) {
            // Create changelog for project repository.
            $changelog = $this->createNote($username, $repository, [
                'base' => $previous,
                'head' => $latest
            ]);
        }
        $changelog .= "\n";

        // Create changelog for each updated dependency.
        $versions = $this->getUpdatedDependencies($username, $repository, $previous, $latest);

        foreach ($versions as $name => $version) {
            $package = $this->allowedPackages[$name];

            $changelog .= $this->createNote(
                $package->extra->username,
                $package->extra->repository,
                $version
            );
        }

        $changelog = $this->processMarkdown($changelog);

        if (mb_strlen($changelog) < 1) {
            return null;
        }
        return $changelog;
    }

    public function updateChangelogForPullRequest(
        string $username,
        string $repository,
        string $base,
        string $head,
        string $pullRequest
    ): void {
        $changelog = $this->createChangelog(
            $username,
            $repository,
            $base,
            $head,
            false
        );

        $this->client
            ->authenticate($this->authToken, authMethod: AuthMethod::ACCESS_TOKEN);

        $this->client
            ->pullRequests()
            ->update($username, $repository, $pullRequest, [
                'body' => $changelog,
            ]);
    }

    public function updateChangelogForRelease(
        string $username,
        string $repository,
        string $base,
    ): void {
        // Releases API returns 30 releases per page. This *will* fail for
        // releases older than that.
        $releases = $this->client->repos()->releases()->all($username, $repository);

        // Generate release notes only if there's more than one release.
        if (count($releases) <= 1) {
            return;
        }

        $latest = $previous = null;

        // Releases are sorted from newest to oldest. Loop releases until we've found the matching
        // release and the release after that.
        foreach ($releases as $release) {
            if ($release['tag_name'] === $base) {
                $latest = $release;

                continue;
            }
            if ($latest) {
                $previous = $release;
                break;
            }
        }

        if (!$latest || !$previous) {
            throw new \InvalidArgumentException('Failed to parse latest or previous release.');
        }
        $changelog = $this
            ->createChangelog(
                $username,
                $repository,
                $previous['tag_name'],
                $latest['tag_name'],
                true
            );

        $this->client
            ->authenticate($this->authToken, authMethod: AuthMethod::ACCESS_TOKEN);

        $this->client
            ->repos()
            ->releases()
            ->edit($username, $repository, $latest['id'], [
                'body' => $changelog,
            ]);
    }
}

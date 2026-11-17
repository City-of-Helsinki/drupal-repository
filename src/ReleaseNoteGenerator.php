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

    /**
     * The maximum length of the changelog.
     *
     * GitHub limits pull request bodies to 65536 characters.
     */
    private const MAX_BODY_LENGTH = 65000;

    public function __construct(
        private readonly Client $client,
        private readonly CacheInterface $cache,
        #[Inject(Settings::GITHUB_OAUTH)] private readonly string $authToken,
        #[Inject(Settings::CHANGELOG_ALLOWED_PACKAGES)] private readonly array $allowedPackages,
    ) {
    }

    /**
     * Creates a changelog entry for given repository and version range.
     *
     * This is built from the 'compare' API instead of the 'generate release
     * notes' API, because the latter requires write access to the repository.
     */
    private function createNote(string $username, string $repository, array $version): ? string
    {
        $key = $this->getCacheKey('create-note', $username, $repository, $version['base'], $version['head']);

        return $this->cache->get($key, function (ItemInterface $item) use ($username, $repository, $version) {
            ['base' => $base, 'head' => $head] = $version;

            $compare = $this
                ->client
                ->repos()
                ->commits()
                ->compare($username, $repository, $base, $head);

            // Cache for 60 seconds, so we don't unnecessarily loop the GitHub API when
            // automation updates all our projects at once.
            $item->expiresAfter(60);

            $url = "https://github.com/$username/$repository";
            $changes = $this->parseChanges($compare['commits'] ?? [], $url);

            $note = "## [$username/$repository]($url): $base to $head\n";

            if ($changes) {
                $note .= "### What's Changed\n" . implode("\n", $changes) . "\n\n";
            }
            return $note . "**Full Changelog**: $url/compare/$base...$head\n";
        });
    }

    /**
     * Parses a list of changes from given commits.
     *
     * Lists pull requests, merged either with a merge commit or squashed. If
     * there are none, falls back to listing non-merge commits.
     *
     * @param array<int, array{commit?: array{message?: string}, parents?: array<mixed>}> $commits
     *   The commits, as returned by the 'compare' API (oldest first).
     * @param string $url
     *   The repository URL.
     *
     * @return string[]
     *   The changes, newest first.
     */
    private function parseChanges(array $commits, string $url): array
    {
        $pullRequests = $commitMessages = [];

        foreach (array_reverse($commits) as $commit) {
            $lines = array_values(array_filter(
                array_map('trim', explode("\n", $commit['commit']['message'] ?? '')),
                fn (string $line) => $line !== '',
            ));

            if (!$lines) {
                continue;
            }
            [$subject] = $lines;

            // Merge commit: "Merge pull request #123 from org/branch\n\nTitle".
            if (preg_match('/^Merge pull request #(\d+) from (\S+)/', $subject, $matches)) {
                $title = $lines[1] ?? $matches[2];
                $pullRequests[] = "* $title in $url/pull/$matches[1]";
                continue;
            }
            // Squash merge: "Title (#123)".
            if (preg_match('/^(.+) \(#(\d+)\)$/', $subject, $matches)) {
                $pullRequests[] = "* $matches[1] in $url/pull/$matches[2]";
                continue;
            }
            // Skip other merge commits, such as "Merge branch 'main' into X".
            if (count($commit['parents'] ?? []) > 1) {
                continue;
            }
            $commitMessages[] = "* $subject";
        }
        return $pullRequests ?: $commitMessages;
    }

    /**
     * @return array<string, mixed>
     *   The decoded composer.lock file.
     */
    private function getComposerLock(string $username, string $repository, string $reference): array
    {
        $key = $this->getCacheKey('composer-lock', $username, $repository, $reference);

        return $this->cache->get($key, function (ItemInterface $item) use ($username, $repository, $reference) {
            $data = $this->client
                ->repos()
                ->contents()
                ->rawDownload($username, $repository, 'composer.lock', $reference);
            // Cache for 60 seconds, so we don't unnecessarily loop the GitHub API when
            // automation updates all our projects at once.
            $item->expiresAfter(60);

            return json_decode($data, true) ?: [];
        });
    }

    /**
     * Gets the whitelisted packages whose version changed.
     *
     * @param array<string, array{from: ?array<string, mixed>, to: ?array<string, mixed>}> $changes
     *   The changes, as returned by ComposerUpdates::diff().
     *
     * @return array<string, array{base: string, head: string}>
     *   The versions, keyed by package name.
     */
    private function getUpdatedDependencies(array $changes): array
    {
        $versions = [];
        foreach ($changes as $name => ['from' => $from, 'to' => $to]) {
            // Ignore non-whitelisted, added and removed packages.
            if (!isset($this->allowedPackages[$name], $from, $to) || $from['version'] === $to['version']) {
                continue;
            }
            $versions[$name] = [
                'base' => $from['version'],
                'head' => $to['version'],
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

        $changes = ComposerUpdates::diff(
            $this->getComposerLock($username, $repository, $previous),
            $this->getComposerLock($username, $repository, $latest),
        );

        // Create changelog for each updated dependency.
        foreach ($this->getUpdatedDependencies($changes) as $name => $version) {
            $package = $this->allowedPackages[$name];

            $changelog .= $this->createNote(
                $package->extra->username,
                $package->extra->repository,
                $version
            );
        }

        $changelog = $this->processMarkdown($changelog);
        // List all updated packages, as long as they fit in the body.
        $changelog .= ComposerUpdates::toMarkdown($changes, self::MAX_BODY_LENGTH - strlen($changelog));

        if (mb_strlen(trim($changelog)) < 1) {
            return null;
        }
        return $changelog;
    }

    /**
     * Authenticates the client.
     *
     * Must be called before any API request, so we don't hit the
     * unauthenticated rate limit (60 requests/hour per IP).
     */
    private function authenticate(): void
    {
        $this->client
            ->authenticate($this->authToken, authMethod: AuthMethod::ACCESS_TOKEN);
    }

    public function updateChangelogForPullRequest(
        string $username,
        string $repository,
        string $base,
        string $head,
        string $pullRequest
    ): void {
        $this->authenticate();

        $changelog = $this->createChangelog(
            $username,
            $repository,
            $base,
            $head,
            false
        );

        // Leave the existing body untouched if there's nothing to report.
        if ($changelog === null) {
            return;
        }

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
        $this->authenticate();

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

        // Keep the existing release notes (e.g. GitHub's auto-generated
        // "What's Changed" section) if there's nothing to report.
        if ($changelog === null) {
            return;
        }

        $this->client
            ->repos()
            ->releases()
            ->edit($username, $repository, $latest['id'], [
                'body' => $changelog,
            ]);
    }
}

<?php

declare(strict_types=1);

namespace App;

/**
 * Lists the packages that changed between two composer.lock files.
 */
final class ComposerUpdates
{
    /**
     * Compares two composer.lock files.
     *
     * A package has changed if its version or source reference changed, so
     * dev versions are listed when their commit changes.
     *
     * @param array<string, mixed> $previous
     *   The previous composer.lock file.
     * @param array<string, mixed> $latest
     *   The latest composer.lock file.
     *
     * @return array<string, array{from: ?array<string, mixed>, to: ?array<string, mixed>}>
     *   The changed packages, keyed and sorted by name. 'from' is null for
     *   added packages, and 'to' is null for removed packages.
     */
    public static function diff(array $previous, array $latest): array
    {
        $previousPackages = self::getPackages($previous);
        $latestPackages = self::getPackages($latest);

        $changes = [];
        foreach ($previousPackages + $latestPackages as $name => $package) {
            $from = $previousPackages[$name] ?? null;
            $to = $latestPackages[$name] ?? null;

            $unchanged = $from && $to &&
                $from['version'] === $to['version'] &&
                self::getReference($from) === self::getReference($to);

            if ($unchanged) {
                continue;
            }
            $changes[$name] = ['from' => $from, 'to' => $to];
        }
        ksort($changes);

        return $changes;
    }

    /**
     * Renders the changes as a collapsed Markdown table.
     *
     * @param array<string, array{from: ?array<string, mixed>, to: ?array<string, mixed>}> $changes
     *   The changes, as returned by ::diff().
     * @param int $maxLength
     *   The maximum length of the output. Rows that don't fit are left out.
     */
    public static function toMarkdown(array $changes, int $maxLength): string
    {
        if (!$changes) {
            return '';
        }
        $header = sprintf("\n<details>\n<summary>All Composer updates (%d)</summary>\n\n", count($changes)) .
            "| Package | From | To | Diff |\n|---|---|---|---|\n";
        $footer = "</details>\n";
        // Reserve room for the 'not shown' line.
        $budget = $maxLength - strlen($header . $footer) - 100;

        $rows = '';
        $shown = 0;
        foreach ($changes as $name => ['from' => $from, 'to' => $to]) {
            $row = sprintf(
                "| %s | %s | %s | %s |\n",
                $name,
                $from['version'] ?? '–',
                $to['version'] ?? '–',
                $from && $to ? self::getDiffLink($from, $to) : '',
            );

            if (strlen($rows . $row) > $budget) {
                break;
            }
            $rows .= $row;
            $shown++;
        }

        if ($shown < count($changes)) {
            $rows .= sprintf("\n%d more updates not shown.\n", count($changes) - $shown);
        }
        return $header . $rows . $footer;
    }

    /**
     * Gets a Markdown link to the diff between two versions of a package.
     *
     * @param array<string, mixed> $from
     *   The previous package.
     * @param array<string, mixed> $to
     *   The latest package.
     *
     * @return string
     *   The link, or an empty string if the package isn't hosted on GitHub or
     *   git.drupalcode.org.
     */
    public static function getDiffLink(array $from, array $to): string
    {
        $url = $to['source']['url'] ?? null;
        $old = self::getReference($from);
        $new = self::getReference($to);

        // A link can't be built if the package moved to another repository.
        if (!$url || !$old || !$new || $url !== ($from['source']['url'] ?? null)) {
            return '';
        }

        if (preg_match('#^(?:https://|git@)github\.com[/:]([^/]+/[^/]+?)(?:\.git)?$#', $url, $matches)) {
            $diffUrl = "https://github.com/$matches[1]/compare/$old...$new";
        } elseif (preg_match('#^https://git\.drupalcode\.org/(.+?)(?:\.git)?$#', $url, $matches)) {
            $diffUrl = "https://git.drupalcode.org/$matches[1]/-/compare/$old...$new";
        } else {
            return '';
        }
        return sprintf('[%s...%s](%s)', self::shorten($old), self::shorten($new), $diffUrl);
    }

    /**
     * @param array<string, mixed> $lock
     *
     * @return array<string, array<string, mixed>>
     */
    private static function getPackages(array $lock): array
    {
        $packages = [];
        foreach (['packages', 'packages-dev'] as $section) {
            foreach ($lock[$section] ?? [] as $package) {
                if (isset($package['name'], $package['version'])) {
                    $packages[$package['name']] = $package;
                }
            }
        }
        return $packages;
    }

    /**
     * @param array<string, mixed> $package
     */
    private static function getReference(array $package): ?string
    {
        return $package['source']['reference'] ?? $package['dist']['reference'] ?? null;
    }

    /**
     * Shortens commit hashes to 7 characters, like GitHub does.
     */
    private static function shorten(string $reference): string
    {
        return preg_match('/^[0-9a-f]{40}$/', $reference) ? substr($reference, 0, 7) : $reference;
    }
}

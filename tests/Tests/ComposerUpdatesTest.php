<?php

declare(strict_types=1);

namespace App\Tests;

use App\ComposerUpdates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ComposerUpdatesTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function package(string $name, string $version, ?string $reference = null, ?string $url = null): array
    {
        $package = ['name' => $name, 'version' => $version];

        if ($reference) {
            $package['source'] = ['url' => $url ?? "https://github.com/$name.git", 'reference' => $reference];
        }
        return $package;
    }

    public function testDiff(): void
    {
        $previous = [
            'packages' => [
                $this->package('vendor/unchanged', '1.0.0', 'aaa'),
                $this->package('vendor/updated', '1.0.0', 'aaa'),
                $this->package('vendor/dev', 'dev-main', 'aaa'),
                $this->package('vendor/removed', '1.0.0'),
            ],
            'packages-dev' => [
                $this->package('vendor/dev-dependency', '1.0.0'),
            ],
        ];
        $latest = [
            'packages' => [
                $this->package('vendor/unchanged', '1.0.0', 'aaa'),
                $this->package('vendor/updated', '1.1.0', 'bbb'),
                $this->package('vendor/dev', 'dev-main', 'bbb'),
                $this->package('vendor/added', '1.0.0'),
            ],
            'packages-dev' => [
                $this->package('vendor/dev-dependency', '2.0.0'),
            ],
        ];

        $changes = ComposerUpdates::diff($previous, $latest);

        $this->assertSame(
            ['vendor/added', 'vendor/dev', 'vendor/dev-dependency', 'vendor/removed', 'vendor/updated'],
            array_keys($changes),
        );
        $this->assertNull($changes['vendor/added']['from']);
        $this->assertNull($changes['vendor/removed']['to']);
        $this->assertSame('1.1.0', $changes['vendor/updated']['to']['version']);
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function diffLinkProvider(): array
    {
        $old = str_repeat('a', 40);
        $new = str_repeat('b', 40);

        return [
            'github https' => [
                'https://github.com/composer/semver.git',
                $old,
                $new,
                "[aaaaaaa...bbbbbbb](https://github.com/composer/semver/compare/$old...$new)",
            ],
            'github ssh' => [
                'git@github.com:City-of-Helsinki/drupal-hdbt.git',
                $old,
                $new,
                "[aaaaaaa...bbbbbbb](https://github.com/City-of-Helsinki/drupal-hdbt/compare/$old...$new)",
            ],
            'drupal.org' => [
                'https://git.drupalcode.org/project/key.git',
                '8.x-1.22',
                '8.x-1.23',
                '[8.x-1.22...8.x-1.23](https://git.drupalcode.org/project/key/-/compare/8.x-1.22...8.x-1.23)',
            ],
            'unknown host' => [
                'https://example.com/vendor/package.git',
                '1.0.0',
                '1.1.0',
                '',
            ],
        ];
    }

    #[DataProvider('diffLinkProvider')]
    public function testGetDiffLink(string $url, string $old, string $new, string $expected): void
    {
        $this->assertSame($expected, ComposerUpdates::getDiffLink(
            $this->package('vendor/package', '1.0.0', $old, $url),
            $this->package('vendor/package', '1.1.0', $new, $url),
        ));
    }

    public function testGetDiffLinkMovedRepository(): void
    {
        $this->assertSame('', ComposerUpdates::getDiffLink(
            $this->package('vendor/package', '1.0.0', 'aaa', 'https://github.com/old/package.git'),
            $this->package('vendor/package', '1.1.0', 'bbb', 'https://github.com/new/package.git'),
        ));
    }

    public function testToMarkdown(): void
    {
        $this->assertSame('', ComposerUpdates::toMarkdown([], 1000));

        $markdown = ComposerUpdates::toMarkdown([
            'vendor/added' => ['from' => null, 'to' => $this->package('vendor/added', '1.0.0')],
            'vendor/removed' => ['from' => $this->package('vendor/removed', '1.0.0'), 'to' => null],
            'vendor/updated' => [
                'from' => $this->package('vendor/updated', '1.0.0', '1.0.0'),
                'to' => $this->package('vendor/updated', '1.1.0', '1.1.0'),
            ],
        ], 1000);

        $this->assertSame(
            "\n<details>\n<summary>All Composer updates (3)</summary>\n\n" .
            "| Package | From | To | Diff |\n|---|---|---|---|\n" .
            "| vendor/added | – | 1.0.0 |  |\n" .
            "| vendor/removed | 1.0.0 | – |  |\n" .
            "| vendor/updated | 1.0.0 | 1.1.0 | " .
            "[1.0.0...1.1.0](https://github.com/vendor/updated/compare/1.0.0...1.1.0) |\n" .
            "</details>\n",
            $markdown,
        );
    }

    public function testToMarkdownMaxLength(): void
    {
        $changes = [];
        for ($i = 0; $i < 100; $i++) {
            $changes["vendor/package-$i"] = ['from' => null, 'to' => $this->package("vendor/package-$i", '1.0.0')];
        }
        $markdown = ComposerUpdates::toMarkdown($changes, 1000);

        $this->assertLessThanOrEqual(1000, strlen($markdown));
        $this->assertStringContainsString('All Composer updates (100)', $markdown);
        $this->assertMatchesRegularExpression('/\n\d+ more updates not shown\.\n<\/details>\n$/', $markdown);
    }
}

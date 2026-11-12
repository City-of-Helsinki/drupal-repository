<?php

namespace App\Tests;

use App\CacheTrait;
use App\ReleaseNoteGenerator;
use Github\Api\PullRequest;
use Github\Api\Repo;
use Github\Api\Repository\Commits;
use Github\Api\Repository\Contents;
use Github\Api\Repository\Releases;
use Github\Client;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\CacheInterface;

class ReleaseNoteGeneratorTest extends TestCase
{
    use ProphecyTrait;
    use TestKernelTrait;
    use CacheTrait;

    private function getSut(Client $client, ?CacheInterface $cache = null) : ReleaseNoteGenerator
    {
        if (!$cache) {
            $cache = $this->prophesize(CacheInterface::class)->reveal();
        }
        return new ReleaseNoteGenerator(
            $client,
            $cache,
            'authToken',
            ['drupal/helfi_api_base'],
        );
    }

    /**
     * Tests updateChangelogForRelease when there are not enough releases.
     */
    public function testSkipUpdateChangelogForRelease(): void
    {
        $client = $this->prophesize(Client::class);
        $client->authenticate('authToken', null, Argument::any())
            ->shouldBeCalled();
        $releases = $this->prophesize(Releases::class);
        $releases->all('test', 'test')
            ->shouldBeCalled()
            ->willReturn([
                ['id' => 123, 'name' => 'dummy'],
            ]);
        $releases->edit(Argument::any(), Argument::any(), Argument::any())
            ->shouldNotBeCalled();
        $repo = $this->prophesize(Repo::class);
        $repo->releases()->willReturn($releases->reveal());
        $client->repos()->willReturn($repo->reveal());

        $sut = $this->getSut($client->reveal());
        $sut->updateChangelogForRelease('test', 'test', '123');
    }

    /**
     * Tests updateChangelogForRelease when we fail to parse previous/latest release.
     */
    public function testUpdateChangelogForReleaseReleaseException(): void
    {
        $client = $this->prophesize(Client::class);
        $client->authenticate('authToken', null, Argument::any())
            ->shouldBeCalled();
        $releases = $this->prophesize(Releases::class);
        $releases->all('test', 'test')
            ->shouldBeCalled()
            ->willReturn([
                ['id' => 123, 'name' => 'dummy', 'tag_name' => 'head'],
                ['id' => 123, 'name' => 'dummy', 'tag_name' => 'base'],
            ]);
        $releases->edit(Argument::any(), Argument::any(), Argument::any())
            ->shouldNotBeCalled();
        $repo = $this->prophesize(Repo::class);
        $repo->releases()->willReturn($releases->reveal());
        $client->repos()->willReturn($repo->reveal());

        $sut = $this->getSut($client->reveal());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to parse latest or previous release.');
        $sut->updateChangelogForRelease('test', 'test', '');
    }

    public function testUpdateChangelogForRelease(): void
    {
        $client = $this->prophesize(Client::class);
        $client->authenticate('authToken', null, Argument::any())
            ->shouldBeCalled();
        $releases = $this->prophesize(Releases::class);
        $releases->all('test', 'test')
            ->shouldBeCalled()
            ->willReturn([
                ['id' => 1, 'name' => 'dummy', 'tag_name' => 'head'],
                ['id' => 2, 'name' => 'dummy', 'tag_name' => 'base'],
            ]);
        $releases->edit('test', 'test', 1, Argument::any())
            ->shouldBeCalled();
        $releases->generateNotes(Argument::cetera())
            ->shouldNotBeCalled();

        $commits = $this->prophesize(Commits::class);
        $commits->compare('test', 'test', 'base', 'head')
            ->shouldBeCalled()
            ->willReturn([
                'files' => [
                    ['filename' => 'composer.lock'],
                ],
                'commits' => [],
            ]);
        $repo = $this->prophesize(Repo::class);
        $repo->commits()
            ->shouldBeCalled()
            ->willReturn($commits->reveal());
        $repo->releases()->willReturn($releases->reveal());
        $contents = $this->prophesize(Contents::class);
        $contents
            ->rawDownload('test', 'test', 'composer.lock', 'base')
            ->shouldBeCalled()
            ->willReturn(json_encode([
                'packages' => [
                    [
                        'name' => 'drupal/helfi_api_base',
                        'version' => '1.2.3'
                    ],
                ],
            ]));
        $contents
            ->rawDownload('test', 'test', 'composer.lock', 'head')
            ->shouldBeCalled()
            ->willReturn(json_encode([
                'packages' => [
                    [
                        'name' => 'drupal/helfi_api_base',
                        'version' => '1.2.4'
                    ],
                ],
            ]));
        $repo->contents()
            ->shouldBeCalled()
            ->willReturn($contents->reveal());
        $client->repos()->willReturn($repo->reveal());

        $cache = new ArrayAdapter();
        $sut = $this->getSut($client->reveal(), $cache);
        $sut->updateChangelogForRelease('test', 'test', 'head');
    }

    /**
     * Tests that release notes are left untouched when composer.lock is unchanged.
     */
    public function testUpdateChangelogForReleaseNoChanges(): void
    {
        $client = $this->prophesize(Client::class);
        $client->authenticate('authToken', null, Argument::any())
            ->shouldBeCalled();
        $releases = $this->prophesize(Releases::class);
        $releases->all('test', 'test')
            ->shouldBeCalled()
            ->willReturn([
                ['id' => 1, 'name' => 'dummy', 'tag_name' => 'head'],
                ['id' => 2, 'name' => 'dummy', 'tag_name' => 'base'],
            ]);
        $releases->edit(Argument::cetera())
            ->shouldNotBeCalled();
        $commits = $this->prophesize(Commits::class);
        $commits->compare('test', 'test', 'base', 'head')
            ->shouldBeCalled()
            ->willReturn([
                'files' => [
                    ['filename' => 'README.md'],
                ],
            ]);
        $repo = $this->prophesize(Repo::class);
        $repo->commits()->willReturn($commits->reveal());
        $repo->releases()->willReturn($releases->reveal());
        $client->repos()->willReturn($repo->reveal());

        $sut = $this->getSut($client->reveal(), new ArrayAdapter());
        $sut->updateChangelogForRelease('test', 'test', 'head');
    }

    /**
     * Tests that pull request body is left untouched when composer.lock is unchanged.
     */
    public function testUpdateChangelogForPullRequestNoChanges(): void
    {
        $client = $this->prophesize(Client::class);
        $client->authenticate('authToken', null, Argument::any())
            ->shouldBeCalled();
        $commits = $this->prophesize(Commits::class);
        $commits->compare('test', 'test', 'dev', 'update-configuration')
            ->shouldBeCalled()
            ->willReturn(['files' => []]);
        $repo = $this->prophesize(Repo::class);
        $repo->commits()->willReturn($commits->reveal());
        $client->repos()->willReturn($repo->reveal());
        $pullRequest = $this->prophesize(PullRequest::class);
        $pullRequest->update(Argument::cetera())
            ->shouldNotBeCalled();
        $client->pullRequests()->willReturn($pullRequest->reveal());

        $sut = $this->getSut($client->reveal(), new ArrayAdapter());
        $sut->updateChangelogForPullRequest('test', 'test', 'dev', 'update-configuration', '1');
    }

    /**
     * Tests that changelog is built from pull requests between package versions.
     */
    public function testUpdateChangelogForPullRequest(): void
    {
        $package = (object) [
            'name' => 'drupal/hdbt',
            'extra' => (object) ['username' => 'org', 'repository' => 'hdbt'],
        ];
        $commit = fn (string $message, int $parents = 1) => [
            'commit' => ['message' => $message],
            'parents' => array_fill(0, $parents, []),
        ];

        $client = $this->prophesize(Client::class);
        $client->authenticate('authToken', null, Argument::any())
            ->shouldBeCalled();

        $commits = $this->prophesize(Commits::class);
        $commits->compare('test', 'test', 'dev', 'update-configuration')
            ->shouldBeCalled()
            ->willReturn(['files' => [['filename' => 'composer.lock']]]);
        $commits->compare('org', 'hdbt', '1.0.0', '1.1.0')
            ->shouldBeCalled()
            ->willReturn([
                'commits' => [
                    $commit('UHF-1: Work in progress'),
                    $commit("Merge branch 'main' into UHF-1", 2),
                    $commit("Merge pull request #10 from org/UHF-1\n\nUHF-1: Add feature", 2),
                    $commit('Fix typo (#11)'),
                ],
            ]);
        $contents = $this->prophesize(Contents::class);
        $contents->rawDownload('test', 'test', 'composer.lock', 'dev')
            ->willReturn(json_encode(['packages' => [['name' => 'drupal/hdbt', 'version' => '1.0.0']]]));
        $contents->rawDownload('test', 'test', 'composer.lock', 'update-configuration')
            ->willReturn(json_encode(['packages' => [['name' => 'drupal/hdbt', 'version' => '1.1.0']]]));

        $repo = $this->prophesize(Repo::class);
        $repo->commits()->willReturn($commits->reveal());
        $repo->contents()->willReturn($contents->reveal());
        $client->repos()->willReturn($repo->reveal());

        $expected = "\n## [org/hdbt](https://github.com/org/hdbt): 1.0.0 to 1.1.0\n" .
            "### What's Changed\n" .
            "* Fix typo in https://github.com/org/hdbt/pull/11\n" .
            "* [UHF-1](https://helsinkisolutionoffice.atlassian.net/browse/UHF-1): " .
            "Add feature in https://github.com/org/hdbt/pull/10\n" .
            "\n" .
            "**Full Changelog**: https://github.com/org/hdbt/compare/1.0.0...1.1.0\n";

        $pullRequest = $this->prophesize(PullRequest::class);
        $pullRequest->update('test', 'test', '1', ['body' => $expected])
            ->shouldBeCalled();
        $client->pullRequests()->willReturn($pullRequest->reveal());

        $sut = new ReleaseNoteGenerator(
            $client->reveal(),
            new ArrayAdapter(),
            'authToken',
            ['drupal/hdbt' => $package],
        );
        $sut->updateChangelogForPullRequest('test', 'test', 'dev', 'update-configuration', '1');
    }

    /**
     * Tests that commits are listed when there are no pull requests.
     */
    public function testParseChangesFallsBackToCommits(): void
    {
        $sut = $this->getSut($this->prophesize(Client::class)->reveal());
        $method = new \ReflectionMethod($sut, 'parseChanges');
        $this->assertSame(
            ['* Second', '* First'],
            $method->invoke($sut, [
                ['commit' => ['message' => "First\n\nbody"], 'parents' => [[]]],
                ['commit' => ['message' => "Merge branch 'a' into b"], 'parents' => [[], []]],
                ['commit' => ['message' => 'Second'], 'parents' => [[]]],
            ], 'https://github.com/org/repo'),
        );
    }
}

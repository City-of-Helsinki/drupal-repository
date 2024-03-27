<?php

namespace App\Tests;

use App\CacheTrait;
use App\ReleaseNoteGenerator;
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
        $releases->generateNotes('test', 'test', [
            'previous_tag_name' => 'base',
            'target_commitish' => 'head',
            'tag_name' => 'head',
        ])
            ->shouldBeCalled()
            ->willReturn(['body' => '']);

        $commits = $this->prophesize(Commits::class);
        $commits->compare('test', 'test', 'base', 'head')
            ->shouldBeCalled()
            ->willReturn([
                'files' => [
                    ['filename' => 'composer.lock'],
                ],
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

}

<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Tests hooks.json against a real webhook server.
 *
 * Commands are replaced with a script that prints the working directory and
 * the arguments, so each test can check exactly what would be run.
 *
 * Requires the webhook binary, so run this in the app container.
 */
final class HooksTest extends TestCase
{
    /**
     * The commands each hook runs. HookCommandsTest runs these too.
     *
     * @var list<string>
     */
    public const CHANGELOG_COMMAND = [
        'console.php',
        'changelog:automation-pull-request',
        '--project', 'City-of-Helsinki/drupal-helfi-kymp',
        '--base', 'dev',
        '--head', 'update-configuration',
        '--number', '123',
    ];

    /**
     * @var list<string>
     */
    public const RELEASE_NOTE_COMMAND = [
        'console.php',
        'changelog:project-release',
        '--project', 'City-of-Helsinki/drupal-helfi-kymp',
        '--base', '2026-11-05.1',
    ];

    /**
     * @var list<string>
     */
    public const DISPATCH_COMMAND = ['console.php', 'app:dispatch', 'config-update'];

    /**
     * @var list<string>
     */
    public const INDEX_COMMAND = ['console.php', 'queue:package', 'City-of-Helsinki/drupal-module-helfi-api-base'];

    private const SECRET = 'webhook-secret';
    private const UPDATE_SECRET = 'webhook-update-secret';
    private const NOT_EXECUTED = [
        'Hook rules were not satisfied.',
        'Error occurred while evaluating hook rules.',
    ];

    private static ?Process $server = null;
    private static string $directory;
    private static int $port;

    public static function setUpBeforeClass(): void
    {
        $binary = (new ExecutableFinder())->find('webhook');

        if (!$binary) {
            self::markTestSkipped('The webhook binary was not found. Run the tests in the app container.');
        }
        self::$directory = sys_get_temp_dir() . '/hooks-test-' . bin2hex(random_bytes(4));
        mkdir(self::$directory);

        $recorder = self::$directory . '/record.sh';
        file_put_contents($recorder, "#!/bin/sh\npwd\nprintf '%s\\n' \"\$@\"\n");
        chmod($recorder, 0755);

        $hooks = file_get_contents(__DIR__ . '/../../hooks.json');

        if ($hooks === false) {
            self::fail('Failed to read hooks.json.');
        }
        $hooks = str_replace(
            '"execute-command": "/usr/bin/php",',
            sprintf('"execute-command": "%s", "include-command-output-in-response": true,', $recorder),
            $hooks,
            $count,
        );
        self::assertSame(4, $count, 'Every hook should run /usr/bin/php.');
        file_put_contents(self::$directory . '/hooks.json', $hooks);

        // Find a free port.
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = $socket ? stream_socket_get_name($socket, false) : false;

        if (!$socket || !$address) {
            self::fail('Failed to find a free port.');
        }
        self::$port = (int) parse_url("tcp://$address", PHP_URL_PORT);
        fclose($socket);

        self::$server = new Process(
            [
                $binary,
                '-hooks', self::$directory . '/hooks.json',
                '-template',
                '-ip', '127.0.0.1',
                '-port', (string) self::$port,
            ],
            env: [
                'WEBHOOK_SECRET' => self::SECRET,
                'WEBHOOK_UPDATE_SECRET' => self::UPDATE_SECRET,
                'PROJECT_DIR' => self::$directory,
            ],
        );
        self::$server->start();

        for ($i = 0; $i < 50 && self::$server->isRunning(); $i++) {
            if ($connection = @fsockopen('127.0.0.1', self::$port)) {
                fclose($connection);
                return;
            }
            usleep(100000);
        }
        self::fail(
            "The webhook server did not start:\n" . self::$server->getOutput() . self::$server->getErrorOutput()
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();

        if (isset(self::$directory) && is_dir(self::$directory)) {
            array_map('unlink', glob(self::$directory . '/*') ?: []);
            rmdir(self::$directory);
        }
    }

    /**
     * Sends a signed request to the given hook.
     *
     * @param array<string, mixed> $payload
     *
     * @return array{int, string}
     *   The status code and the response body.
     */
    private function send(string $hook, array $payload, string $secret, ?string $algorithm): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $headers = ['Content-Type: application/json'];

        if ($algorithm) {
            $header = $algorithm === 'sha256' ? 'X-Hub-Signature-256' : 'X-Hub-Signature';
            $headers[] = sprintf('%s: %s=%s', $header, $algorithm, hash_hmac($algorithm, $body, $secret));
        }
        $response = file_get_contents(
            sprintf('http://127.0.0.1:%d/hooks/%s', self::$port, $hook),
            context: stream_context_create(['http' => [
                'method' => 'POST',
                'header' => $headers,
                'content' => $body,
                'ignore_errors' => true,
            ]]),
        );
        preg_match('/^HTTP\/\S+ (\d+)/', http_get_last_response_headers()[0] ?? '', $matches);

        return [(int) ($matches[1] ?? 0), trim((string) $response)];
    }

    /**
     * @param array<string, mixed> $pullRequest
     *
     * @return array<string, mixed>
     */
    private static function pullRequest(string $action, array $pullRequest = []): array
    {
        return [
            'action' => $action,
            'repository' => ['full_name' => 'City-of-Helsinki/drupal-helfi-kymp'],
            'pull_request' => $pullRequest + [
                'number' => 123,
                'body' => 'Automatic update',
                'head' => ['ref' => 'update-configuration', 'repo' => ['fork' => false]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function release(string $action): array
    {
        return [
            'action' => $action,
            'repository' => ['full_name' => 'City-of-Helsinki/drupal-helfi-kymp'],
            'release' => ['tag_name' => '2026-11-05.1'],
        ];
    }

    /**
     * @return array<string, array{string, array<string, mixed>, string, ?string, ?string[]}>
     */
    public static function hookProvider(): array
    {
        $index = ['repository' => ['full_name' => 'City-of-Helsinki/drupal-module-helfi-api-base']];
        $pr = 'update-automation-pull-request';

        return [
            // Automation pull request changelog.
            'pr opened' => [
                $pr,
                self::pullRequest('opened'),
                self::UPDATE_SECRET,
                'sha256',
                self::CHANGELOG_COMMAND,
            ],
            'pr synchronize' => [
                $pr,
                self::pullRequest('synchronize'),
                self::UPDATE_SECRET,
                'sha256',
                self::CHANGELOG_COMMAND,
            ],
            'pr body reset' => [
                $pr,
                self::pullRequest('edited'),
                self::UPDATE_SECRET,
                'sha256',
                self::CHANGELOG_COMMAND,
            ],
            'pr changelog written' => [
                $pr,
                self::pullRequest('edited', ['body' => "\n## [City-of-Helsinki/drupal-hdbt](https://github.com)"]),
                self::UPDATE_SECRET,
                'sha256',
                null,
            ],
            'pr closed' => [$pr, self::pullRequest('closed'), self::UPDATE_SECRET, 'sha256', null],
            'pr other branch' => [
                $pr,
                self::pullRequest('opened', ['head' => ['ref' => 'main', 'repo' => ['fork' => false]]]),
                self::UPDATE_SECRET,
                'sha256',
                null,
            ],
            'pr from fork' => [
                $pr,
                self::pullRequest('opened', ['head' => ['ref' => 'update-configuration', 'repo' => ['fork' => true]]]),
                self::UPDATE_SECRET,
                'sha256',
                null,
            ],
            'pr wrong secret' => [$pr, self::pullRequest('opened'), self::SECRET, 'sha256', null],
            'pr sha1 signature' => [$pr, self::pullRequest('opened'), self::UPDATE_SECRET, 'sha1', null],
            'pr no signature' => [$pr, self::pullRequest('opened'), self::UPDATE_SECRET, null, null],
            // Release notes.
            'release published' => [
                'update-release-note',
                self::release('published'),
                self::UPDATE_SECRET,
                'sha256',
                self::RELEASE_NOTE_COMMAND,
            ],
            'release created' => ['update-release-note', self::release('created'), self::UPDATE_SECRET, 'sha256', null],
            'release wrong secret' => ['update-release-note', self::release('published'), self::SECRET, 'sha256', null],
            // Config update dispatch.
            'dispatch published' => [
                'trigger-dispatch',
                self::release('published'),
                self::UPDATE_SECRET,
                'sha256',
                self::DISPATCH_COMMAND,
            ],
            'dispatch created' => ['trigger-dispatch', self::release('created'), self::UPDATE_SECRET, 'sha256', null],
            'dispatch wrong secret' => ['trigger-dispatch', self::release('published'), self::SECRET, 'sha256', null],
            // Package index.
            'index push' => [
                'update-index',
                $index,
                self::SECRET,
                'sha256',
                self::INDEX_COMMAND,
            ],
            'index wrong secret' => ['update-index', $index, self::UPDATE_SECRET, 'sha256', null],
            'index sha1 signature' => ['update-index', $index, self::SECRET, 'sha1', null],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param string[]|null $expected
     *   The expected arguments, or null if the command shouldn't run.
     */
    #[DataProvider('hookProvider')]
    public function testHook(string $hook, array $payload, string $secret, ?string $algorithm, ?array $expected): void
    {
        [$status, $body] = $this->send($hook, $payload, $secret, $algorithm);

        if ($expected === null) {
            $this->assertContains($body, self::NOT_EXECUTED);
            return;
        }
        $this->assertSame(200, $status, $body);
        $this->assertSame([realpath(self::$directory), ...$expected], explode("\n", $body));
    }
}

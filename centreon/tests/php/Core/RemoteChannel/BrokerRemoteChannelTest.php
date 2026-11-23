<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Tests\Core\RemoteChannel;

use Centreon\Domain\Engine\EngineException;
use Centreon\Infrastructure\Engine\EngineRepositoryRemoteChannel;
use Core\RemoteChannel\Application\PollerCommandSenderInterface;
use Core\RemoteChannel\Application\Repository\ReadPollerTargetRepositoryInterface;
use Core\RemoteChannel\Domain\Exception\RemoteChannelException;
use Core\RemoteChannel\Domain\Model\DeploymentAction;
use Core\RemoteChannel\Domain\Model\DispatchStatus;
use Core\RemoteChannel\Domain\Model\PollerTarget;
use Core\RemoteChannel\Domain\Model\RemoteChannel;
use Core\RemoteChannel\Infrastructure\Broker\BrokerApiClient;
use Core\RemoteChannel\Infrastructure\Broker\BrokerConfigurationDeployer;
use Core\RemoteChannel\Infrastructure\Broker\BrokerPollerCommandSender;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Broker adapters against a stub of the central broker remote API (contracts.md § 1).
 */
#[CoversClass(BrokerPollerCommandSender::class)]
#[CoversClass(BrokerConfigurationDeployer::class)]
#[CoversClass(BrokerApiClient::class)]
#[CoversClass(EngineRepositoryRemoteChannel::class)]
class BrokerRemoteChannelTest extends TestCase
{
    /** @var list<array{method: string, url: string, headers: list<string>, body: array<mixed>|null}> */
    private array $requests = [];

    private string $cacheDir = '';

    protected function setUp(): void
    {
        putenv('CENTREON_BROKER_API_URL=http://cbd:8099/');
        putenv('CENTREON_BROKER_API_TOKEN=secret');
        $this->cacheDir = sys_get_temp_dir() . '/remote-channel-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        putenv('CENTREON_BROKER_API_URL');
        putenv('CENTREON_BROKER_API_TOKEN');
        putenv('CENTREON_BROKER_API_RESULT_TIMEOUT');
        if (is_dir($this->cacheDir)) {
            exec('rm -rf ' . escapeshellarg($this->cacheDir));
        }
    }

    public function testCommandsAreSanitisedAndSentByUid(): void
    {
        $sender = new BrokerPollerCommandSender(
            $this->client([
                'POST /v1/commands' => [200, ['items' => [['poller_id' => '900001', 'status' => 'APPLIED']]]],
            ]),
            $this->repository('`$'),
        );

        $sender->send([[
            'target' => $this->target(2, '900001'),
            'commands' => ["[1700000000] ADD_HOST_COMMENT;h1;1;admin;rm `x` \$y\nnext"],
        ]]);

        $this->assertCount(1, $this->requests);
        $request = $this->requests[0];
        $this->assertSame('http://cbd:8099/v1/commands', $request['url']);
        $this->assertContains('Authorization: Bearer secret', $request['headers']);
        $this->assertSame('900001', $request['body']['items'][0]['poller_id']);
        $this->assertSame(
            ['[1700000000] ADD_HOST_COMMENT;h1;1;admin;rm x ynext'],
            $request['body']['items'][0]['commands']
        );
    }

    public function testNotConnectedPollerIsAnExplicitError(): void
    {
        $sender = new BrokerPollerCommandSender(
            $this->client([
                'POST /v1/commands' => [200, ['items' => [['poller_id' => '900001', 'status' => 'NOT_CONNECTED']]]],
            ]),
            $this->repository(),
        );

        try {
            $sender->send([['target' => $this->target(2, '900001'), 'commands' => ['[1] SCHEDULE_FORCED_HOST_CHECK;h1;1']]]);
            $this->fail('An exception was expected');
        } catch (RemoteChannelException $ex) {
            $this->assertSame(503, $ex->getCode());
            $this->assertStringContainsString('"poller-2"', $ex->getMessage());
            $this->assertStringContainsString('not connected', $ex->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    #[DataProvider('provideNotConfirmedStatuses')]
    public function testOnlyAppliedConfirmsACommand(array $item, string $expectedMessage): void
    {
        $sender = new BrokerPollerCommandSender(
            $this->client(['POST /v1/commands' => [200, ['items' => $item === [] ? [] : [$item]]]]),
            $this->repository(),
        );

        try {
            $sender->send([['target' => $this->target(2, '900001'), 'commands' => ['[1] SCHEDULE_FORCED_HOST_CHECK;h1;1']]]);
            $this->fail('An exception was expected');
        } catch (RemoteChannelException $ex) {
            $this->assertSame(504, $ex->getCode());
            $this->assertStringContainsString($expectedMessage, $ex->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideNotConfirmedStatuses(): iterable
    {
        yield 'accepted' => [['poller_id' => '900001', 'status' => 'ACCEPTED'], 'status ACCEPTED'];

        yield 'delivered' => [['poller_id' => '900001', 'status' => 'DELIVERED'], 'status DELIVERED'];

        yield 'unknown status' => [['poller_id' => '900001', 'status' => 'WHATEVER'], 'status WHATEVER'];

        yield 'no status' => [['poller_id' => '900001'], 'status (none)'];

        yield 'missing item' => [[], 'no answer'];
    }

    public function testRejectedCommandCarriesTheEngineMessage(): void
    {
        $sender = new BrokerPollerCommandSender(
            $this->client([
                'POST /v1/commands' => [200, ['items' => [[
                    'poller_id' => '900001',
                    'status' => 'REJECTED',
                    'results' => [['index' => 0, 'status' => 'REJECTED', 'message' => 'unknown host h9']],
                ]]]],
            ]),
            $this->repository(),
        );

        $this->expectException(RemoteChannelException::class);
        $this->expectExceptionCode(422);
        $this->expectExceptionMessage('unknown host h9');
        $sender->send([['target' => $this->target(2, '900001'), 'commands' => ['[1] SCHEDULE_FORCED_HOST_CHECK;h9;1']]]);
    }

    public function testBrokerApiAuthErrorIsABadGateway(): void
    {
        $sender = new BrokerPollerCommandSender(
            $this->client(['POST /v1/commands' => [401, ['error' => 'bad token']]]),
            $this->repository(),
        );

        $this->expectException(RemoteChannelException::class);
        $this->expectExceptionCode(502);
        $this->expectExceptionMessage('bad token');
        $sender->send([['target' => $this->target(2, '900001'), 'commands' => ['[1] X']]]);
    }

    public function testDeploymentSendsTheGeneratedFilesThenAwaitsApplied(): void
    {
        $this->writeFile('engine/2/hosts.cfg', 'define host{}');
        $this->writeFile('engine/2/plugins.json', '{"centreon-plugin-Foo":"20260300"}');
        $this->writeFile('engine/2/centengine.DEBUG', 'not deployed');
        $this->writeFile('broker/2/poller-2-module.json', '{}');
        $this->writeFile('vmware/2/centreon_vmware.json', '{}');

        $deployer = $this->deployer([
            'POST /v1/deployments' => [202, ['request_id' => 'r1', 'status' => 'ACCEPTED']],
            'GET /v1/deployments/r1' => [
                [200, ['request_id' => 'r1', 'status' => 'TRANSFERRING']],
                [200, ['request_id' => 'r1', 'status' => 'APPLIED']],
            ],
        ]);

        $failures = $deployer->deploy([$this->target(2, '900001')], DeploymentAction::None, true);

        $this->assertSame([], $failures);
        $body = $this->requests[0]['body'];
        $this->assertSame('900001', $body['poller_id']);
        $this->assertSame('none', $body['action']);
        $this->assertSame(
            [['broker', 'poller-2-module.json'], ['engine', 'hosts.cfg'], ['engine', 'plugins.json']],
            array_map(static fn (array $file): array => [$file['target'], $file['path']], $this->sortFiles($body['files']))
        );
        $this->assertSame(hash('sha256', 'define host{}'), $this->sortFiles($body['files'])[1]['sha256']);
        $this->assertCount(3, $this->requests);
    }

    public function testReloadWithoutFilesAndFailingPollersDoNotStopTheOthers(): void
    {
        $deployer = $this->deployer([
            'POST /v1/deployments' => [
                [200, ['status' => 'NOT_CONNECTED', 'message' => '']],
                [202, ['request_id' => 'r3', 'status' => 'ACCEPTED']],
            ],
            'GET /v1/deployments/r3' => [200, ['request_id' => 'r3', 'status' => 'APPLIED']],
        ]);

        $failures = $deployer->deploy(
            [$this->target(2, '900002'), $this->target(3, '900003')],
            DeploymentAction::Reload,
            false
        );

        $this->assertSame([2], array_keys($failures));
        $this->assertSame(503, $failures[2]->getCode());
        $this->assertSame([], $this->requests[1]['body']['files']);
        $this->assertSame('reload', $this->requests[1]['body']['action']);
    }

    public function testRestartEndsAtApplied(): void
    {
        $deployer = $this->deployer([
            'POST /v1/deployments' => [202, ['request_id' => 'r1', 'status' => 'ACCEPTED']],
            'GET /v1/deployments/r1' => [
                [200, ['request_id' => 'r1', 'status' => 'DELIVERED']],
                [200, ['request_id' => 'r1', 'status' => 'APPLIED', 'message' => 'restart requested']],
            ],
        ]);

        $this->assertSame([], $deployer->deploy([$this->target(2, '900001')], DeploymentAction::Restart, false));
        $this->assertCount(3, $this->requests);
    }

    public function testNoFinalStatusInTimeIsATimeout(): void
    {
        putenv('CENTREON_BROKER_API_RESULT_TIMEOUT=1');
        $deployer = $this->deployer([
            'POST /v1/deployments' => [202, ['request_id' => 'r1', 'status' => 'ACCEPTED']],
            'GET /v1/deployments/r1' => [200, ['request_id' => 'r1', 'status' => 'DELIVERED']],
        ]);

        $failures = $deployer->deploy([$this->target(2, '900001')], DeploymentAction::Reload, false);

        $this->assertSame(504, $failures[2]->getCode());
    }

    public function testRealTimeCommandsAreGroupedByPollerAndErrorsKeepTheirHttpStatus(): void
    {
        $commandSender = new class () implements PollerCommandSenderInterface {
            /** @var list<array{target: PollerTarget, commands: non-empty-list<string>}> */
            public array $sent = [];

            public function send(array $batches): void
            {
                $this->sent = $batches;

                throw new RemoteChannelException('poller-2 down', DispatchStatus::NotConnected);
            }
        };
        $repository = $this->repository();
        $engineRepository = new EngineRepositoryRemoteChannel($commandSender, $repository);

        try {
            $engineRepository->sendExternalCommands([
                'EXTERNALCMD:900002:[1] ACKNOWLEDGE_HOST_PROBLEM;h1;2;1;1;admin;a:b',
                'EXTERNALCMD:2:[1] SCHEDULE_FORCED_HOST_CHECK;h1;1',
            ]);
            $this->fail('An exception was expected');
        } catch (EngineException $ex) {
            $this->assertSame(503, $ex->getCode());
        }
        $sent = $commandSender->sent;
        $this->assertCount(1, $sent);
        $this->assertSame(
            ['[1] ACKNOWLEDGE_HOST_PROBLEM;h1;2;1;1;admin;a:b', '[1] SCHEDULE_FORCED_HOST_CHECK;h1;1'],
            $sent[0]['commands']
        );
    }

    private function target(int $id, string $uid): PollerTarget
    {
        return new PollerTarget($id, $uid, 'poller-' . $id, RemoteChannel::Broker);
    }

    private function repository(string $illegalCharacters = ''): ReadPollerTargetRepositoryInterface
    {
        return new class ($illegalCharacters) implements ReadPollerTargetRepositoryInterface {
            public function __construct(private readonly string $illegalCharacters)
            {
            }

            public function findByIdOrUid(int|string $key): ?PollerTarget
            {
                return in_array((string) $key, ['2', '900002'], true)
                    ? new PollerTarget(2, '900002', 'poller-2', RemoteChannel::Broker)
                    : null;
            }

            public function findIllegalCharacters(): string
            {
                return $this->illegalCharacters;
            }
        };
    }

    /**
     * @param array<string, mixed> $routes
     */
    private function deployer(array $routes): BrokerConfigurationDeployer
    {
        return new BrokerConfigurationDeployer(
            $this->client($routes),
            $this->cacheDir,
            static function (int $microseconds): void {
                usleep(min($microseconds, 50000));
            }
        );
    }

    /**
     * @param array<string, array{int, array<mixed>}|list<array{int, array<mixed>}>> $routes by "METHOD path":
     *                                                                                       one answer, or answers in turn (the last one repeats)
     */
    private function client(array $routes): BrokerApiClient
    {
        $calls = [];
        $callback = function (string $method, string $url, array $options) use ($routes, &$calls): MockResponse {
            $path = (string) parse_url($url, PHP_URL_PATH);
            $body = isset($options['body']) && is_string($options['body']) && $options['body'] !== ''
                ? json_decode($options['body'], true)
                : null;
            $this->requests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $options['headers'] ?? [],
                'body' => $body,
            ];
            $key = $method . ' ' . $path;
            $answers = $routes[$key] ?? [404, ['error' => 'no route ' . $key]];
            if (is_array($answers[0])) {
                $index = min($calls[$key] ?? 0, count($answers) - 1);
                $calls[$key] = ($calls[$key] ?? 0) + 1;
                $answers = $answers[$index];
            }
            [$status, $json] = $answers;

            return new MockResponse((string) json_encode($json), ['http_code' => $status]);
        };

        return new BrokerApiClient(new MockHttpClient($callback));
    }

    private function writeFile(string $relativePath, string $content): void
    {
        $path = $this->cacheDir . '/config/' . $relativePath;
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $content);
    }

    /**
     * @param list<array<string, string>> $files
     *
     * @return list<array<string, string>>
     */
    private function sortFiles(array $files): array
    {
        usort(
            $files,
            static fn (array $first, array $second): int => [$first['target'], $first['path']] <=> [$second['target'], $second['path']]
        );

        return $files;
    }
}

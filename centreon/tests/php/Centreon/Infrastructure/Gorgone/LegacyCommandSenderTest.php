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

namespace Tests\Centreon\Infrastructure\Gorgone;

use Adaptation\Database\Connection\ConnectionInterface;
use Centreon\Domain\MonitoringServer\Exception\GorgoneDeliveryException;
use Centreon\Infrastructure\Gorgone\Interfaces\ConfigurationLoaderApiInterface;
use Centreon\Infrastructure\Gorgone\LegacyCommandException;
use Centreon\Infrastructure\Gorgone\LegacyCommandSender;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @covers \Centreon\Infrastructure\Gorgone\LegacyCommandSender
 */
class LegacyCommandSenderTest extends TestCase
{
    private const TOKEN = 'abc123';
    private const CONNECTED = ['ping_ok' => 3, 'last_ping_recv' => 1700000000];
    private const FINAL = ['data' => [['code' => 2, 'data' => '{"legacycmd_final":1}']]];

    /** @var list<string> "METHOD path" of every request sent to Gorgone */
    private array $requests = [];

    protected function tearDown(): void
    {
        putenv('CENTREON_GORGONE_RESULT_TIMEOUT');
        putenv('CENTREON_GORGONE_DELIVERY_CONCURRENCY');
    }

    public function testEveryPollerIsSentBeforeAnyResultIsAwaited(): void
    {
        $sender = $this->createSender([
            'internal/constatus' => ['data' => ['2' => self::CONNECTED, '3' => self::CONNECTED]],
            'log/token-2' => self::FINAL,
            'log/token-3' => self::FINAL,
        ], tokenPerPoller: true);

        $failures = $sender->deliverToPollers([
            2 => static fn () => $sender->sendCommand('RELOAD', 2),
            3 => static fn () => $sender->sendCommand('RELOAD', 3),
        ]);

        $this->assertSame([], $failures);
        $this->assertSame(
            [
                // the connection status is read once for every poller
                'GET internal/constatus',
                'POST centreon/legacycmd/command',
                'POST centreon/legacycmd/command',
                'GET log/token-2',
                'GET log/token-3',
            ],
            $this->requests
        );
    }

    public function testFailingPollersDoNotStopTheOthers(): void
    {
        $sender = $this->createSender([
            'internal/constatus' => ['data' => ['3' => self::CONNECTED, '4' => self::CONNECTED]],
            'log/token-3' => ['data' => [['code' => 1, 'data' => '{"message":"no pathway for target 3"}']]],
            'log/token-4' => self::FINAL,
        ], tokenPerPoller: true);

        $failures = $sender->deliverToPollers([
            2 => static fn () => $sender->sendCommand('RELOAD', 2),
            3 => static fn () => $sender->sendCommand('RELOAD', 3),
            4 => static fn () => $sender->sendCommand('RELOAD', 4),
        ]);

        $this->assertSame([2, 3], array_keys($failures));
        $this->assertSame(GorgoneDeliveryException::NODE_NOT_CONNECTED, $failures[2]->getCode());
        $this->assertSame(GorgoneDeliveryException::DELIVERY_FAILED, $failures[3]->getCode());
        $this->assertStringContainsString('no pathway for target 3', $failures[3]->getMessage());
        // nothing sent to the poller which is not connected, the others are
        $this->assertCount(2, array_keys($this->requests, 'POST centreon/legacycmd/command', true));
        $this->assertContains('GET log/token-4', $this->requests);
    }

    public function testResultsAreAwaitedTogether(): void
    {
        putenv('CENTREON_GORGONE_RESULT_TIMEOUT=1');
        $sender = $this->createSender([
            'internal/constatus' => ['data' => ['2' => self::CONNECTED, '3' => self::CONNECTED, '4' => self::CONNECTED]],
        ], tokenPerPoller: true);

        $start = microtime(true);
        $failures = $sender->deliverToPollers([
            2 => static fn () => $sender->sendCommand('RELOAD', 2),
            3 => static fn () => $sender->sendCommand('RELOAD', 3),
            4 => static fn () => $sender->sendCommand('RELOAD', 4),
        ]);

        // close to one timeout, not the sum of three
        $this->assertLessThan(2.5, microtime(true) - $start);
        $this->assertSame([2, 3, 4], array_keys($failures));
        foreach ($failures as $failure) {
            $this->assertSame(GorgoneDeliveryException::RESULT_TIMEOUT, $failure->getCode());
        }
    }

    public function testConcurrencyIsBounded(): void
    {
        putenv('CENTREON_GORGONE_DELIVERY_CONCURRENCY=1');
        $sender = $this->createSender([
            'internal/constatus' => ['data' => ['2' => self::CONNECTED, '3' => self::CONNECTED]],
            'log/token-2' => self::FINAL,
            'log/token-3' => self::FINAL,
        ], tokenPerPoller: true);

        $sender->deliverToPollers([
            2 => static fn () => $sender->sendCommand('RELOAD', 2),
            3 => static fn () => $sender->sendCommand('RELOAD', 3),
        ]);

        $this->assertSame(
            [
                'GET internal/constatus',
                'POST centreon/legacycmd/command',
                'GET log/token-2',
                // a poller sent later is checked against a fresh connection status
                'GET internal/constatus',
                'POST centreon/legacycmd/command',
                'GET log/token-3',
            ],
            $this->requests
        );
    }

    /**
     * @param array<int, int> $reasons failure reason, by poller id
     */
    #[DataProvider('provideFailures')]
    public function testFailuresAreCombinedPerPoller(array $reasons, int $expectedReason): void
    {
        $failures = array_map(
            static fn (int $reason): LegacyCommandException => new LegacyCommandException('reason ' . $reason, $reason),
            $reasons
        );

        $error = LegacyCommandSender::combineFailures($failures, [2 => 'Poller-2', 3 => 'Poller-3'], 5);

        $this->assertSame($expectedReason, $error->getCode());
        $this->assertStringStartsWith(count($reasons) . ' of 5 monitoring servers', $error->getMessage());
        foreach ($reasons as $pollerId => $reason) {
            $this->assertStringContainsString('Poller-' . $pollerId . ': reason ' . $reason, $error->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<int, int>, int}>
     */
    public static function provideFailures(): iterable
    {
        yield 'timeouts only: the deliveries may still succeed (504)' => [
            [2 => GorgoneDeliveryException::RESULT_TIMEOUT, 3 => GorgoneDeliveryException::RESULT_TIMEOUT],
            GorgoneDeliveryException::RESULT_TIMEOUT,
        ];

        yield 'not connected only (409)' => [
            [2 => GorgoneDeliveryException::NODE_NOT_CONNECTED],
            GorgoneDeliveryException::NODE_NOT_CONNECTED,
        ];

        yield 'not connected and timeout (409)' => [
            [2 => GorgoneDeliveryException::NODE_NOT_CONNECTED, 3 => GorgoneDeliveryException::RESULT_TIMEOUT],
            GorgoneDeliveryException::DELIVERY_FAILED,
        ];
    }

    public function testSinglePollerFailureIsKeptAsIs(): void
    {
        $failure = new LegacyCommandException(
            'Gorgone node 2 is not connected',
            GorgoneDeliveryException::NODE_NOT_CONNECTED
        );

        $this->assertSame($failure, LegacyCommandSender::combineFailures([2 => $failure], [2 => 'Poller-2'], 1));
    }

    public function testDeliveryToDisconnectedNodeFailsWithoutSendingAnything(): void
    {
        $sender = $this->createSender([
            'internal/constatus' => ['data' => ['2' => ['ping_ok' => 0, 'last_ping_recv' => 0]]],
        ]);

        $this->expectException(LegacyCommandException::class);
        $this->expectExceptionMessage('Gorgone node 2 is not connected');
        $this->expectExceptionCode(GorgoneDeliveryException::NODE_NOT_CONNECTED);
        try {
            $sender->sendCommand('RELOAD', 2);
        } finally {
            $this->assertSame(['GET internal/constatus'], $this->requests);
        }
    }

    public function testDeliveryFailsOnFailureLoggedUnderTheToken(): void
    {
        $sender = $this->createSender([
            'internal/constatus' => ['data' => ['2' => ['ping_ok' => 3, 'last_ping_recv' => 1700000000]]],
            'log/' . self::TOKEN => ['data' => [
                ['code' => 0, 'data' => '{"message":"action centreoncommand proceed"}'],
                ['code' => 1, 'data' => '{"message":"proxy - no pathway for target 2"}'],
            ]],
        ]);

        $this->expectException(LegacyCommandException::class);
        $this->expectExceptionMessage('proxy - no pathway for target 2');
        $this->expectExceptionCode(GorgoneDeliveryException::DELIVERY_FAILED);
        $sender->sendCommand('RELOAD', 2);
    }

    public function testDeliverySucceedsOnlyOnTheFinalResult(): void
    {
        $sender = $this->createSender([
            'internal/constatus' => ['data' => ['2' => ['ping_ok' => 3, 'last_ping_recv' => 1700000000]]],
            'log/' . self::TOKEN => ['data' => [
                ['code' => 0, 'data' => '{"message":"action centreoncommand proceed"}'],
                // a sub-action result is not the end of the batch
                ['code' => 2, 'data' => '{"message":"process copy finished"}'],
                ['code' => 2, 'data' => '{"message":"done","legacycmd_final":1,"results":[]}'],
            ]],
        ]);

        $this->assertSame(self::TOKEN, $sender->sendCommand('RELOAD', 2));
        $this->assertSame(
            ['GET internal/constatus', 'POST centreon/legacycmd/command', 'GET log/' . self::TOKEN],
            $this->requests
        );
    }

    public function testDeliveryFailsWithoutFinalResultWithinTheTimeout(): void
    {
        putenv('CENTREON_GORGONE_RESULT_TIMEOUT=1');
        $sender = $this->createSender([
            'internal/constatus' => ['data' => ['2' => ['ping_ok' => 3, 'last_ping_recv' => 1700000000]]],
            'log/' . self::TOKEN => ['data' => [
                ['code' => 2, 'data' => '{"message":"process copy finished"}'],
            ]],
        ]);

        $this->expectException(LegacyCommandException::class);
        $this->expectExceptionMessage('did not confirm the delivery within 1 seconds');
        $this->expectExceptionCode(GorgoneDeliveryException::RESULT_TIMEOUT);
        $sender->sendCommand('RELOAD', 2);
    }

    public function testContainerizedCentralIsCheckedLikeAnyOtherNode(): void
    {
        // The Central poller (localhost = 1, poller_type 'docker') is a pullwss node too
        $sender = $this->createSender([
            'internal/constatus' => ['data' => ['2' => ['ping_ok' => 3, 'last_ping_recv' => 1700000000]]],
        ]);

        $this->expectException(LegacyCommandException::class);
        $this->expectExceptionMessage('Gorgone node 1 is not connected');
        $this->expectExceptionCode(GorgoneDeliveryException::NODE_NOT_CONNECTED);
        try {
            $sender->sendConfigurationFiles(1);
        } finally {
            $this->assertSame(['GET internal/constatus'], $this->requests);
        }
    }

    public function testUntrackedFinalResultIsAccepted(): void
    {
        // gorgone does not follow SENDEXPORTFILE: immediate final result with tracked = 0
        $sender = $this->createSender([
            'internal/constatus' => ['data' => ['2' => ['ping_ok' => 3, 'last_ping_recv' => 1700000000]]],
            'log/' . self::TOKEN => ['data' => [
                ['code' => 2, 'data' => '{"message":"done","legacycmd_final":1,"tracked":0,"results":[]}'],
            ]],
        ]);

        $this->assertSame(self::TOKEN, $sender->sendCommand('SENDEXPORTFILE', 2, '12'));
    }

    public function testExternalCommandIsNotTracked(): void
    {
        $sender = $this->createSender([]);

        $this->assertSame(self::TOKEN, $sender->sendLines('EXTERNALCMD:2:[1700000000] SCHEDULE_FORCED_HOST_CHECK;h;1'));
        $this->assertSame(['POST centreon/legacycmd/command'], $this->requests);
    }

    /**
     * @param array<string, array<string, mixed>> $getResponses JSON answered to each GET, by API path
     * @param string $pollerType poller_type of every poller
     * @param bool $tokenPerPoller each POST answers "token-<target>" instead of the same token
     */
    private function createSender(
        array $getResponses,
        string $pollerType = 'docker',
        bool $tokenPerPoller = false,
    ): LegacyCommandSender {
        $configuration = $this->createMock(ConfigurationLoaderApiInterface::class);
        $configuration->method('isSecureConnectionSelfSigned')->willReturn(false);
        $configuration->method('isApiConnectionSecure')->willReturn(false);
        $configuration->method('getApiIpAddress')->willReturn('gorgone');
        $configuration->method('getApiPort')->willReturn(8085);
        $configuration->method('getApiUsername')->willReturn(null);
        $configuration->method('getCommandTimeout')->willReturn(2);

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('fetchOne')->willReturn($pollerType);

        $client = new MockHttpClient(function (string $method, string $url, array $options) use (
            $getResponses,
            $tokenPerPoller
        ): MockResponse {
            $path = (string) preg_replace('#^http://gorgone:8085/api/#', '', $url);
            $this->requests[] = $method . ' ' . $path;
            if ($method === 'POST') {
                $commands = json_decode((string) ($options['body'] ?? ''), true);
                $token = $tokenPerPoller && is_array($commands) ? 'token-' . $commands[0]['target'] : self::TOKEN;

                return new MockResponse((string) json_encode(['token' => $token]));
            }

            return new MockResponse((string) json_encode($getResponses[$path] ?? ['data' => []]));
        });

        $sender = new LegacyCommandSender($configuration, $connection);
        (new \ReflectionProperty(LegacyCommandSender::class, 'client'))->setValue($sender, $client);

        return $sender;
    }
}

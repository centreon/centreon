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
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @covers \Centreon\Infrastructure\Gorgone\LegacyCommandSender
 */
class LegacyCommandSenderTest extends TestCase
{
    private const TOKEN = 'abc123';

    /** @var list<string> "METHOD path" of every request sent to Gorgone */
    private array $requests = [];

    protected function tearDown(): void
    {
        putenv('CENTREON_GORGONE_RESULT_TIMEOUT');
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
     */
    private function createSender(array $getResponses, string $pollerType = 'docker'): LegacyCommandSender
    {
        $configuration = $this->createMock(ConfigurationLoaderApiInterface::class);
        $configuration->method('isSecureConnectionSelfSigned')->willReturn(false);
        $configuration->method('isApiConnectionSecure')->willReturn(false);
        $configuration->method('getApiIpAddress')->willReturn('gorgone');
        $configuration->method('getApiPort')->willReturn(8085);
        $configuration->method('getApiUsername')->willReturn(null);
        $configuration->method('getCommandTimeout')->willReturn(2);

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('fetchOne')->willReturn($pollerType);

        $client = new MockHttpClient(function (string $method, string $url) use ($getResponses): MockResponse {
            $path = (string) preg_replace('#^http://gorgone:8085/api/#', '', $url);
            $this->requests[] = $method . ' ' . $path;
            if ($method === 'POST') {
                return new MockResponse((string) json_encode(['token' => self::TOKEN]));
            }

            return new MockResponse((string) json_encode($getResponses[$path] ?? ['data' => []]));
        });

        $sender = new LegacyCommandSender($configuration, $connection);
        (new \ReflectionProperty(LegacyCommandSender::class, 'client'))->setValue($sender, $client);

        return $sender;
    }
}

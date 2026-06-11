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

namespace Tests\Centreon\Infrastructure\MonitoringServer\Repository;

use Centreon\Domain\Gorgone\ActionLog;
use Centreon\Domain\Gorgone\GorgoneException;
use Centreon\Domain\Gorgone\Interfaces\CommandInterface;
use Centreon\Domain\Gorgone\Interfaces\GorgoneServiceInterface;
use Centreon\Domain\Gorgone\Interfaces\ResponseInterface;
use Centreon\Infrastructure\MonitoringServer\Repository\BrokerStatsRepositoryGorgone;
use PHPUnit\Framework\TestCase;

class BrokerStatsRepositoryGorgoneTest extends TestCase
{
    private const FILE = '/var/lib/centreon-broker/central-broker-master-stats.json';

    /**
     * @param ActionLog[] $logs action logs returned by the token-log endpoint
     */
    private function gorgoneService(string $token, array $logs): GorgoneServiceInterface
    {
        $command = $this->createMock(CommandInterface::class);
        $command->method('getToken')->willReturn($token);

        $sendResponse = $this->createMock(ResponseInterface::class);
        $sendResponse->method('getCommand')->willReturn($command);

        $logResponse = $this->createMock(ResponseInterface::class);
        $logResponse->method('getActionLogs')->willReturn($logs);

        $service = $this->createMock(GorgoneServiceInterface::class);
        $service->method('send')->willReturn($sendResponse);
        $service->method('getResponseFromToken')->willReturn($logResponse);

        return $service;
    }

    private function resultLog(int $exitCode, mixed $stdout): ActionLog
    {
        return (new ActionLog('token'))
            ->setCode(100)
            ->setData((string) json_encode(['result' => ['exit_code' => $exitCode, 'stdout' => $stdout]]));
    }

    private function repository(GorgoneServiceInterface $service): BrokerStatsRepositoryGorgone
    {
        // Fast polling for the timeout test (2 polls, no delay).
        return new BrokerStatsRepositoryGorgone($service, 2, 0);
    }

    public function testReturnsStdoutFromTheCommandResult(): void
    {
        $json = '{"now":123,"endpoint central-broker-master-output":{}}';
        $repository = $this->repository($this->gorgoneService('a-token', [$this->resultLog(0, $json)]));

        $this->assertSame($json, $repository->getStatsContent(1, self::FILE));
    }

    public function testThrowsWhenGorgoneReturnsNoToken(): void
    {
        $repository = $this->repository($this->gorgoneService('', []));

        $this->expectException(GorgoneException::class);
        $repository->getStatsContent(1, self::FILE);
    }

    public function testThrowsWhenRemoteCatExitsNonZero(): void
    {
        $repository = $this->repository($this->gorgoneService('a-token', [$this->resultLog(1, '')]));

        $this->expectException(GorgoneException::class);
        $this->expectExceptionMessageMatches('/Could not read broker statistics/');
        $repository->getStatsContent(1, self::FILE);
    }

    public function testThrowsWhenStdoutIsNotAString(): void
    {
        $repository = $this->repository($this->gorgoneService('a-token', [$this->resultLog(0, null)]));

        $this->expectException(GorgoneException::class);
        $repository->getStatsContent(1, self::FILE);
    }

    public function testThrowsOnTimeoutWhenNoResultLogArrives(): void
    {
        // Only non-result logs (code != 100) → the polling window is exhausted.
        $nonResult = (new ActionLog('a-token'))->setCode(0)->setData('{}');
        $repository = $this->repository($this->gorgoneService('a-token', [$nonResult]));

        $this->expectException(GorgoneException::class);
        $this->expectExceptionMessageMatches('/Timed out/');
        $repository->getStatsContent(1, self::FILE);
    }

    public function testWrapsGenericFailuresInGorgoneException(): void
    {
        $service = $this->createMock(GorgoneServiceInterface::class);
        $service->method('send')->willThrowException(new \RuntimeException('boom'));

        $this->expectException(GorgoneException::class);
        $this->expectExceptionMessageMatches('/through Gorgone: boom/');
        $this->repository($service)->getStatsContent(1, self::FILE);
    }

    public function testRethrowsGorgoneExceptionUnchanged(): void
    {
        $service = $this->createMock(GorgoneServiceInterface::class);
        $service->method('send')->willThrowException(new GorgoneException('already typed'));

        $this->expectException(GorgoneException::class);
        $this->expectExceptionMessage('already typed');
        $this->repository($service)->getStatsContent(1, self::FILE);
    }
}

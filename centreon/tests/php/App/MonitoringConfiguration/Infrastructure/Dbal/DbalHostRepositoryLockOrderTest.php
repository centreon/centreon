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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalHostRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalHostTransformer;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalNotificationsTransformer;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Domain\Collection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;

/**
 * The poller lock only prevents the deadlock when it is taken before the host row is written. Nothing else
 * would turn red if it moved after the insert, and a deadlock needs two connections, which the integration
 * suite does not give: the order of the statements is pinned here instead.
 */
final class DbalHostRepositoryLockOrderTest extends TestCase
{
    public function testAddLocksThePollerBeforeInsertingTheHost(): void
    {
        $statements = [];

        $connection = $this->createMock(Connection::class);
        $connection->method('executeQuery')->willReturnCallback(
            function (string $sql) use (&$statements): Result {
                $statements[] = $sql;

                return $this->createMock(Result::class);
            }
        );
        // The host insert is the first thing built after the lock: stopping there is enough to see the order.
        $connection->method('createQueryBuilder')->willReturnCallback(
            function () use (&$statements): never {
                $statements[] = 'INSERT INTO host';

                throw new \RuntimeException('stop at the host insert');
            }
        );

        $repository = new DbalHostRepository(
            $connection,
            new DbalHostTransformer(),
            new DbalNotificationsTransformer(),
            $this->createMock(ResourceAccessRepository::class),
        );

        try {
            $repository->add($this->newHost());
            self::fail('The insert of the host was expected to be reached.');
        } catch (\RuntimeException) {
        }

        self::assertCount(2, $statements);
        self::assertStringContainsString('FROM nagios_server', $statements[0]);
        self::assertStringContainsString('FOR UPDATE', $statements[0]);
        self::assertSame('INSERT INTO host', $statements[1]);
    }

    private function newHost(): Host
    {
        return new Host(
            id: null,
            name: new HostName('server-01'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
        );
    }
}

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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalServiceRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\ServiceNotificationColumnsTransformer;
use App\MonitoringConfiguration\Infrastructure\Dbal\ServiceStalkingOptionsColumnTransformer;
use App\MonitoringConfiguration\Infrastructure\Dbal\ServiceTransformer;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Security\Domain\Service\AccessScopeResolver;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;

/**
 * The poller lock only prevents the deadlock when it is taken before the service row is written. Nothing else
 * would turn red if it moved after the insert, and a deadlock needs two connections, which the integration
 * suite does not give: the order of the statements is pinned here instead.
 */
final class DbalServiceRepositoryLockOrderTest extends TestCase
{
    public function testAddLocksThePollerOfTheHostBeforeInsertingTheService(): void
    {
        $statements = $this->statementsUntilTheServiceInsert(new PollerId(1));

        self::assertCount(2, $statements);
        self::assertStringContainsString('FROM nagios_server', $statements[0]);
        self::assertStringContainsString('FOR UPDATE', $statements[0]);
        self::assertSame('INSERT INTO service', $statements[1]);
    }

    /**
     * A host without poller has no poller to flag: there is nothing to lock.
     */
    public function testAddLocksNothingForAHostWithoutPoller(): void
    {
        self::assertSame(['INSERT INTO service'], $this->statementsUntilTheServiceInsert(null));
    }

    /**
     * @return list<string>
     */
    private function statementsUntilTheServiceInsert(?PollerId $pollerId): array
    {
        $statements = [];

        $connection = $this->createMock(Connection::class);
        $connection->method('executeQuery')->willReturnCallback(
            function (string $sql) use (&$statements): Result {
                $statements[] = $sql;

                return $this->createMock(Result::class);
            }
        );
        // The service insert is the first thing built after the lock: stopping there is enough to see the order.
        $connection->method('createQueryBuilder')->willReturnCallback(
            function () use (&$statements): never {
                $statements[] = 'INSERT INTO service';

                throw new \RuntimeException('stop at the service insert');
            }
        );

        $repository = new DbalServiceRepository(
            $connection,
            new ServiceTransformer(),
            new ServiceNotificationColumnsTransformer(),
            new ServiceStalkingOptionsColumnTransformer(),
            new AccessScopeResolver($this->createMock(ResourceAccessRepository::class)),
            'centreon_storage',
        );

        try {
            $repository->add(new Service(
                id: null,
                name: new ServiceName('ping'),
                hostId: new HostId(1),
                hostActivated: true,
                hostPollerId: $pollerId,
            ));
            self::fail('The insert of the service was expected to be reached.');
        } catch (\RuntimeException) {
        }

        return $statements;
    }
}

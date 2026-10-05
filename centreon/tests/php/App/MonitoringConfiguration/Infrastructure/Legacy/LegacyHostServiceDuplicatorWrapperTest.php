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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Legacy;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Exception\ServiceDuplicationFailedException;
use App\MonitoringConfiguration\Infrastructure\Legacy\LegacyHostServiceDuplicatorWrapper;
use App\MonitoringConfiguration\Infrastructure\Legacy\LegacyServiceCloner;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LegacyHostServiceDuplicatorWrapperTest extends KernelTestCase
{
    private Connection $connection;

    private LegacyHostServiceDuplicatorWrapper $wrapper;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // Real cloner: the exclusive-clone path needs a legacy session, which the integration context
        // has none of. Tests that reach it assert the resulting "no session" verdict; the re-link path
        // runs on this connection and needs no session.
        $this->wrapper = new LegacyHostServiceDuplicatorWrapper($this->connection, new LegacyServiceCloner());
    }

    protected function tearDown(): void
    {
        unset($_SESSION['centreon']);
    }

    public function testASharedServiceIsRelinkedOntoTheCopyNotCloned(): void
    {
        $this->insertHost(9501, 'wrapper-source');
        $this->insertHost(9502, 'wrapper-copy');
        $this->insertHost(9503, 'wrapper-other');
        $this->insertService(8801, 'shared-service');
        // The service is linked to the source and to another host, so it is shared (host_count = 2).
        $this->linkService(9501, 8801);
        $this->linkService(9503, 8801);

        // No exclusive service, so the cloner is called with an empty list and needs no session.
        $this->wrapper->duplicate(sourceHostId: new HostId(9501), newHostId: new HostId(9502));

        self::assertSame(
            1,
            $this->countServiceLinks(9502, 8801),
            'the shared service is re-linked onto the copy, keeping its identity',
        );
    }

    public function testASourceWithNoServiceInsertsNothing(): void
    {
        $this->insertHost(9601, 'wrapper-empty-source');
        $this->insertHost(9602, 'wrapper-empty-copy');

        $this->wrapper->duplicate(sourceHostId: new HostId(9601), newHostId: new HostId(9602));

        self::assertSame(0, $this->countServiceLinks(9602), 'nothing is linked onto a copy whose source has no service');
    }

    public function testAnExclusiveServiceIsRoutedToTheCloner(): void
    {
        $this->insertHost(9701, 'wrapper-exclusive-source');
        $this->insertHost(9702, 'wrapper-exclusive-copy');
        $this->insertService(8901, 'exclusive-service');
        // Linked to the source only (host_count = 1): it must be cloned, not re-linked.
        $this->linkService(9701, 8901);
        unset($_SESSION['centreon']);

        try {
            $this->wrapper->duplicate(sourceHostId: new HostId(9701), newHostId: new HostId(9702));
            self::fail('expected the exclusive service to reach the session-bound cloner');
        } catch (ServiceDuplicationFailedException $exception) {
            // Reaching the cloner with no legacy session yields the expected verdict — proof the
            // exclusive service was routed to it rather than silently re-linked.
            self::assertTrue($exception->expected);
        }

        self::assertSame(0, $this->countServiceLinks(9702, 8901), 'an exclusive service is never re-linked');
    }

    private function insertHost(int $id, string $name): void
    {
        $this->connection->insert('host', ['host_id' => $id, 'host_name' => $name, 'host_register' => '1']);
    }

    private function insertService(int $id, string $description): void
    {
        $this->connection->insert('service', ['service_id' => $id, 'service_description' => $description, 'service_register' => '1']);
    }

    private function linkService(int $hostId, int $serviceId): void
    {
        $this->connection->insert('host_service_relation', ['host_host_id' => $hostId, 'service_service_id' => $serviceId]);
    }

    private function countServiceLinks(int $hostId, ?int $serviceId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM host_service_relation WHERE host_host_id = :hostId';
        $parameters = ['hostId' => $hostId];
        if ($serviceId !== null) {
            $sql .= ' AND service_service_id = :serviceId';
            $parameters['serviceId'] = $serviceId;
        }

        $count = $this->connection->fetchOne($sql, $parameters);
        self::assertIsScalar($count);

        return (int) $count;
    }
}

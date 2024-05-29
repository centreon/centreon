<?php

/*
 * Copyright 2005 - 2021 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Tests\Centreon\Domain\MonitoringServer\UseCase;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\MonitoringServer\Model\RealTimeMonitoringServer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\MonitoringServer\MonitoringServer;
use Centreon\Domain\MonitoringServer\UseCase\RealTimeMonitoringServer\FindRealTimeMonitoringServers;
use Centreon\Infrastructure\MonitoringServer\Repository\RealTimeMonitoringServerRepositoryRDB;
use Tests\Centreon\Domain\MonitoringServer\Model\RealTimeMonitoringServerTest;

/**
 * @package Tests\Centreon\Domain\MonitoringServer\UseCase
 */
class FindRealTimeMonitoringServersTest extends TestCase
{
    private RealTimeMonitoringServerRepositoryRDB&MockObject $realTimeMonitoringServerRepository;
    private RealTimeMonitoringServer $realTimeMonitoringServer;
    private MonitoringServer $monitoringServer;
    private ContactInterface|MockObject $contact;

    protected function setUp(): void
    {
        $this->realTimeMonitoringServerRepository = $this->createMock(RealTimeMonitoringServerRepositoryRDB::class);
        $this->realTimeMonitoringServer = RealTimeMonitoringServerTest::createEntity();
        $this->monitoringServer = (new MonitoringServer())->setId(1);
        $this->contact = $this->createMock(ContactInterface::class);
    }

    /**
     * Test as admin user
     */
    public function testExecuteAsAdmin(): void
    {
        $this->realTimeMonitoringServerRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$this->realTimeMonitoringServer]);

        $this->contact
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->with(Contact::ROLE_MONITORING_RESOURCES_STATUS_RW)
            ->willReturn(true);
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $findRealTimeMonitoringServers = new FindRealTimeMonitoringServers(
            $this->realTimeMonitoringServerRepository,
            $this->contact
        );
        $response = $findRealTimeMonitoringServers->execute();
        $this->assertCount(1, $response->getRealTimeMonitoringServers());
    }

    /**
    * Test as non admin user
    */
    public function testExecuteAsNonAdmin(): void
    {
        $this->realTimeMonitoringServerRepository
            ->expects($this->once())
            ->method('findAllowedMonitoringServers')
            ->willReturn([$this->monitoringServer]);

        $this->realTimeMonitoringServerRepository
            ->expects($this->once())
            ->method('findByIds')
            ->willReturn([$this->realTimeMonitoringServer]);

        $this->contact
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->with(Contact::ROLE_MONITORING_RESOURCES_STATUS_RW)
            ->willReturn(true);
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);

        $findRealTimeMonitoringServers = new FindRealTimeMonitoringServers(
            $this->realTimeMonitoringServerRepository,
            $this->contact
        );
        $response = $findRealTimeMonitoringServers->execute();
        $this->assertCount(1, $response->getRealTimeMonitoringServers());
    }
}

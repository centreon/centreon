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
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Domain\Exception\EntityNotFoundException;
use Centreon\Domain\MonitoringServer\MonitoringServer;
use Centreon\Domain\MonitoringServer\UseCase\GenerateConfiguration;
use Centreon\Domain\MonitoringServer\Interfaces\MonitoringServerRepositoryInterface;
use Centreon\Domain\MonitoringServer\Exception\ConfigurationMonitoringServerException;
use Centreon\Domain\MonitoringServer\Interfaces\MonitoringServerConfigurationRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class GenerateConfigurationTest extends TestCase
{
    /** @var MonitoringServerRepositoryInterface&MockObject */
    private $monitoringServerRepository;

    /** @var MonitoringServerConfigurationRepositoryInterface&MockObject */
    private $monitoringServerConfigurationRepository;

    /** @var ReadAccessGroupRepositoryInterface&MockObject */
    private $readAccessGroupsRepository;

    /** @var ContactInterface&MockObject */
    private $user;

    /** @var AccessGroup&MockObject */
    private $accessGroup;

    /**
     * @var MonitoringServer
     */
    private $monitoringServer;

    protected function setUp(): void
    {
        $this->monitoringServerRepository = $this->createMock(MonitoringServerRepositoryInterface::class);
        $this->monitoringServerConfigurationRepository =
            $this->createMock(MonitoringServerConfigurationRepositoryInterface::class);
        $this->readAccessGroupsRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
        $this->user = $this->createMock(ContactInterface::class);

        $this->accessGroup = (new AccessGroup(2, 'nonAdmin', 'nonAdmin'));

        $this->monitoringServer = (new MonitoringServer())->setId(1);
    }

    public function testErrorRetrievingMonitoringServerException(): void
    {
        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $this->monitoringServerRepository
            ->expects($this->once())
            ->method('findServer')
            ->willReturn(null);

        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage(
            ConfigurationMonitoringServerException::notFound($this->monitoringServer->getId())->getMessage()
        );
        $useCase = new GenerateConfiguration(
            $this->monitoringServerRepository,
            $this->monitoringServerConfigurationRepository,
            $this->readAccessGroupsRepository,
            $this->user
        );
        $useCase->execute($this->monitoringServer->getId());
    }

    public function testErrorOnGeneration(): void
    {
        $repositoryException = new RepositoryException('Test exception message');

        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $this->monitoringServerRepository
            ->expects($this->once())
            ->method('findServer')
            ->willReturn($this->monitoringServer);

        $this->monitoringServerConfigurationRepository
            ->expects($this->once())
            ->method('generateConfiguration')
            ->willThrowException($repositoryException);

        $useCase = new GenerateConfiguration(
            $this->monitoringServerRepository,
            $this->monitoringServerConfigurationRepository,
            $this->readAccessGroupsRepository,
            $this->user
        );
        $this->expectException(ConfigurationMonitoringServerException::class);
        $this->expectExceptionMessage(ConfigurationMonitoringServerException::errorOnGeneration(
            $this->monitoringServer->getId(),
            $repositoryException->getMessage()
        )->getMessage());

        $useCase->execute($this->monitoringServer->getId());
    }

    public function testSuccessAsAdminUser(): void
    {
        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $this->monitoringServerRepository
            ->expects($this->once())
            ->method('findServer')
            ->willReturn($this->monitoringServer);

        $this->monitoringServerConfigurationRepository
            ->expects($this->once())
            ->method('generateConfiguration')
            ->with($this->monitoringServer->getId());

        $this->monitoringServerConfigurationRepository
            ->expects($this->once())
            ->method('moveExportFiles')
            ->with($this->monitoringServer->getId());

        $useCase = new GenerateConfiguration(
            $this->monitoringServerRepository,
            $this->monitoringServerConfigurationRepository,
            $this->readAccessGroupsRepository,
            $this->user
        );
        $useCase->execute($this->monitoringServer->getId());
    }

    public function testSuccessAsNonAdminUser(): void
    {
        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);

        $this->readAccessGroupsRepository
            ->expects($this->once())
            ->method('findByContact')
            ->willReturn([$this->accessGroup]);

        $this->monitoringServerRepository
            ->expects($this->once())
            ->method('findServerByIdAndAccessGroups')
            ->willReturn($this->monitoringServer);

        $this->monitoringServerConfigurationRepository
            ->expects($this->once())
            ->method('generateConfiguration')
            ->with($this->monitoringServer->getId());

        $this->monitoringServerConfigurationRepository
            ->expects($this->once())
            ->method('moveExportFiles')
            ->with($this->monitoringServer->getId());

        $useCase = new GenerateConfiguration(
            $this->monitoringServerRepository,
            $this->monitoringServerConfigurationRepository,
            $this->readAccessGroupsRepository,
            $this->user
        );
        $useCase->execute($this->monitoringServer->getId());
    }
}

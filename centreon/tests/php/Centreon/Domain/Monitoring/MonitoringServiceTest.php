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

namespace Tests\Centreon\Domain\Monitoring;

use Centreon\Domain\HostConfiguration\Interfaces\HostConfigurationServiceInterface;
use Centreon\Domain\Monitoring\Host;
use Centreon\Domain\Monitoring\HostGroup;
use Centreon\Domain\Monitoring\Interfaces\MonitoringRepositoryInterface;
use Centreon\Domain\Monitoring\MonitoringService;
use Centreon\Domain\Monitoring\Service;
use Centreon\Domain\Monitoring\ServiceGroup;
use Centreon\Domain\MonitoringServer\Interfaces\MonitoringServerServiceInterface;
use Centreon\Domain\Security\Interfaces\AccessGroupRepositoryInterface;
use Centreon\Domain\ServiceConfiguration\Interfaces\ServiceConfigurationServiceInterface;
use PHPUnit\Framework\TestCase;

class MonitoringServiceTest extends TestCase
{
    /**
     * @var MonitoringRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $monitoringRepository;

    /**
     * @var AccessGroupRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $accessGroupRepository;

    /**
     * @var ServiceConfigurationServiceInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $serviceConfiguration;
    /**
     * @var HostConfigurationServiceInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $hostConfiguration;
<<<<<<< HEAD
=======
    /**
     * @var MonitoringServerServiceInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $monitoringServerService;
>>>>>>> centreon/dev-21.10.x

    protected function setUp(): void
    {
        $this->monitoringRepository = $this->createMock(MonitoringRepositoryInterface::class);
        $this->accessGroupRepository = $this->createMock(AccessGroupRepositoryInterface::class);
        $this->serviceConfiguration = $this->createMock(ServiceConfigurationServiceInterface::class);
        $this->hostConfiguration = $this->createMock(HostConfigurationServiceInterface::class);
<<<<<<< HEAD
=======
        $this->monitoringServerService = $this->createMock(MonitoringServerServiceInterface::class);
>>>>>>> centreon/dev-21.10.x
    }

    /**
     * @throws \Exception
     */
<<<<<<< HEAD
    public function testFindServices(): void
=======
    public function testFindServices()
>>>>>>> centreon/dev-21.10.x
    {
        $service = (new Service())
            ->setId(1)
            ->setDisplayName('test');

        $this->monitoringRepository->expects(self::any())
            ->method('findServices')
            ->willReturn([$service]); // values returned for the all next tests

        $monitoringService = new MonitoringService(
            $this->monitoringRepository,
            $this->accessGroupRepository,
            $this->serviceConfiguration,
<<<<<<< HEAD
            $this->hostConfiguration
=======
            $this->hostConfiguration,
            $this->monitoringServerService
>>>>>>> centreon/dev-21.10.x
        );

        $servicesFound = $monitoringService->findServices();
        $this->assertCount(
            1,
            $servicesFound,
            "Error, this method must relay the 'findServices' method of the monitoring repository"
        );
    }

    /**
     * @throws \Exception
     */
<<<<<<< HEAD
    public function testFindServicesByHost(): void
=======
    public function testFindServicesByHost()
>>>>>>> centreon/dev-21.10.x
    {
        $service = (new Service())
            ->setId(1)
            ->setDisplayName('test');
        $hostId = 1;

        $this->monitoringRepository->expects(self::any())
            ->method('findServicesByHostWithRequestParameters')
            ->with($hostId)
            ->willReturn([$service]); // values returned for the all next tests

        $monitoringService = new MonitoringService(
            $this->monitoringRepository,
            $this->accessGroupRepository,
            $this->serviceConfiguration,
<<<<<<< HEAD
            $this->hostConfiguration
=======
            $this->hostConfiguration,
            $this->monitoringServerService
>>>>>>> centreon/dev-21.10.x
        );

        $servicesFound = $monitoringService->findServicesByHost($hostId);
        $this->assertCount(
            1,
            $servicesFound,
            "Error, this method must relay the 'findServicesByHost' method of the monitoring repository"
        );
    }

    /**
     * @throws \Exception
     */
<<<<<<< HEAD
    public function testFindHosts(): void
=======
    public function testFindHosts()
>>>>>>> centreon/dev-21.10.x
    {
        $service = (new Service())
            ->setId(1)
            ->setDisplayName('test');

        $host = (new Host())
            ->setId(1)
            ->setDisplayName('test');

        $this->monitoringRepository->expects(self::any())
            ->method('findHosts')
            ->willReturn([$host]); // values returned for the all next tests

        $this->monitoringRepository->expects(self::any())
            ->method('findServicesByHosts')
            ->with([$host->getId()])
            ->willReturn([$host->getId() => [$service]]); // values returned for the all next tests

        $monitoringService = new MonitoringService(
            $this->monitoringRepository,
            $this->accessGroupRepository,
            $this->serviceConfiguration,
<<<<<<< HEAD
            $this->hostConfiguration
=======
            $this->hostConfiguration,
            $this->monitoringServerService
>>>>>>> centreon/dev-21.10.x
        );

        /**
         * @var Host[] $hostsFound
         */
        $hostsFound = $monitoringService->findHosts(true);
        $this->assertCount(
            1,
            $hostsFound,
            "Error, the number of hosts is not equal to the number given by the "
            . "'findHosts' method of the monitoring repository"
        );
        $this->assertEquals($hostsFound[0]->getId(), $host->getId());

        $this->assertCount(
            1,
            $hostsFound[0]->getServices(),
            "Error, the service of the first host does not match the one given by the "
            . "'findServicesOnMultipleHosts' method of the monitoring repository"
        );
        $this->assertEquals($hostsFound[0]->getServices()[0]->getId(), $service->getId());
    }

    /**
     * @throws \Exception
     */
<<<<<<< HEAD
    public function testFindServiceGroups(): void
=======
    public function testFindServiceGroups()
>>>>>>> centreon/dev-21.10.x
    {
        $service = (new Service())
            ->setId(1)
            ->setDisplayName('test');

        $host = (new Host())
            ->setId(2)
            ->setDisplayName('test');
        $host->addService($service);

        $serviceGroup = (new ServiceGroup())
            ->setId(3)
            ->setHosts([$host]);

        $this->monitoringRepository->expects(self::any())
            ->method('findServiceGroups')
            ->willReturn([$serviceGroup]); // values returned for the all next tests

        $monitoringService = new MonitoringService(
            $this->monitoringRepository,
            $this->accessGroupRepository,
            $this->serviceConfiguration,
<<<<<<< HEAD
            $this->hostConfiguration
=======
            $this->hostConfiguration,
            $this->monitoringServerService
>>>>>>> centreon/dev-21.10.x
        );

        /**
         * @var ServiceGroup[] $servicesGroupsFound
         */
        $servicesGroupsFound = $monitoringService->findServiceGroups();
        $this->assertCount(
            1,
            $servicesGroupsFound,
            "Error, this method must relay the 'findServiceGroups' method of the monitoring repository"
        );
        $this->assertEquals($serviceGroup->getId(), $servicesGroupsFound[0]->getId());
        $this->assertEquals($host->getId(), $serviceGroup->getHosts()[0]->getId());
        $this->assertEquals($service->getId(), $serviceGroup->getHosts()[0]->getServices()[0]->getId());
    }

    /**
     * @throws \Exception
     */
<<<<<<< HEAD
    public function testFindOneService(): void
=======
    public function testFindOneService()
>>>>>>> centreon/dev-21.10.x
    {
        $service = (new Service())
            ->setId(1)
            ->setDisplayName('test');

        $host = (new Host())
            ->setId(1)
            ->setDisplayName('test');

        $this->monitoringRepository->expects(self::any())
            ->method('findOneService')
            ->with($host->getId(), $service->getId())
            ->willReturn($service); // values returned for the all next tests

        $monitoringService = new MonitoringService(
            $this->monitoringRepository,
            $this->accessGroupRepository,
            $this->serviceConfiguration,
<<<<<<< HEAD
            $this->hostConfiguration
=======
            $this->hostConfiguration,
            $this->monitoringServerService
>>>>>>> centreon/dev-21.10.x
        );

        $oneService = $monitoringService->findOneService($host->getId(), $service->getId());
        $this->assertNotNull($oneService);
        $this->assertEquals(
            $oneService->getId(),
            $service->getId(),
            "Error, this method must relay the 'findOneService' method of the monitoring repository"
        );
    }

    /**
     * @throws \Exception
     */
<<<<<<< HEAD
    public function testFindOneHost(): void
=======
    public function testFindOneHost()
>>>>>>> centreon/dev-21.10.x
    {
        $service = (new Service())
            ->setId(1)
            ->setDisplayName('test');

        $host = (new Host())
            ->setId(1)
            ->setDisplayName('test');
        $host->addService($service);

        $this->monitoringRepository->expects(self::any())
            ->method('findOneHost')
            ->with($host->getId())
            ->willReturn($host, null);

        $monitoringService = new MonitoringService(
            $this->monitoringRepository,
            $this->accessGroupRepository,
            $this->serviceConfiguration,
<<<<<<< HEAD
            $this->hostConfiguration
=======
            $this->hostConfiguration,
            $this->monitoringServerService
>>>>>>> centreon/dev-21.10.x
        );

        $hostFound = $monitoringService->findOneHost($host->getId());
        $this->assertNotNull($hostFound);
        $this->assertEquals($host->getId(), $hostFound->getId());
        $this->assertEquals($host->getServices()[0]->getId(), $service->getId());

        $hostFound = $monitoringService->findOneHost($host->getId());
        $this->assertNull($hostFound);
    }

    /**
     * @throws \Exception
     */
<<<<<<< HEAD
    public function testFindHostGroups(): void
=======
    public function testFindHostGroups()
>>>>>>> centreon/dev-21.10.x
    {
        $service = (new Service())
            ->setId(3)
            ->setDisplayName('test');

        $host = (new Host())
            ->setId(2)
            ->setDisplayName('test');
        $host->addService($service);

        $hostGroup = (new HostGroup())
            ->setId(1)
            ->addHost($host);

        $this->monitoringRepository->expects(self::any())
            ->method('findHostGroups')
            ->willReturn([$hostGroup]);

        $monitoringService = new MonitoringService(
            $this->monitoringRepository,
            $this->accessGroupRepository,
            $this->serviceConfiguration,
<<<<<<< HEAD
            $this->hostConfiguration
=======
            $this->hostConfiguration,
            $this->monitoringServerService
>>>>>>> centreon/dev-21.10.x
        );

        /**
         * @var HostGroup[] $hostsGroupsFound
         */
        $hostsGroupsFound = $monitoringService->findHostGroups();
        $this->assertCount(
            1,
            $hostsGroupsFound,
            "Error, this method must relay the 'findHostGroups' method of the monitoring repository"
        );
        $this->assertEquals($hostsGroupsFound[0]->getId(), $hostGroup->getId());
        $this->assertEquals($hostsGroupsFound[0]->getHosts()[0]->getId(), $host->getId());
        $this->assertEquals($hostsGroupsFound[0]->getHosts()[0]->getServices()[0]->getId(), $service->getId());
    }

    /**
     * @throws \Exception
     */
<<<<<<< HEAD
    public function testIsHostExist(): void
=======
    public function testIsHostExist()
>>>>>>> centreon/dev-21.10.x
    {
        $host = (new Host())
            ->setId(1)
            ->setDisplayName('test');

        $this->monitoringRepository->expects(self::any())
            ->method('findOneHost')
            ->with($host->getId())
            ->willReturn($host, null);

        $monitoringService = new MonitoringService(
            $this->monitoringRepository,
            $this->accessGroupRepository,
            $this->serviceConfiguration,
<<<<<<< HEAD
            $this->hostConfiguration
=======
            $this->hostConfiguration,
            $this->monitoringServerService
>>>>>>> centreon/dev-21.10.x
        );

        // First test when the 'findOneHost' returns one host
        $isHostExist = $monitoringService->isHostExists($host->getId());
        $this->assertTrue($isHostExist);

        // Second test when the 'findOneHost' returns null
        $isHostExist = $monitoringService->isHostExists($host->getId());
        $this->assertfalse($isHostExist);
    }

    /**
     * @throws \Exception
     */
<<<<<<< HEAD
    public function testIsServiceExist(): void
=======
    public function testIsServiceExist()
>>>>>>> centreon/dev-21.10.x
    {
        $host = (new Host())
            ->setId(1)
            ->setDisplayName('test');

        $service = (new Service())
            ->setId(1)
            ->setHost($host);

        $this->monitoringRepository->expects(self::any())
            ->method('findOneService')
            ->with($host->getId(), $service->getId())
            ->willReturn($service, null);

        $monitoringService = new MonitoringService(
            $this->monitoringRepository,
            $this->accessGroupRepository,
            $this->serviceConfiguration,
<<<<<<< HEAD
            $this->hostConfiguration
=======
            $this->hostConfiguration,
            $this->monitoringServerService
>>>>>>> centreon/dev-21.10.x
        );

        $exists = $monitoringService->isServiceExists($host->getId(), $service->getId());
        $this->assertTrue($exists);
    }

    /**
     * @throws \Exception
     */
<<<<<<< HEAD
    public function testFindServiceGroupsByHostAndService(): void
=======
    public function testFindServiceGroupsByHostAndService()
>>>>>>> centreon/dev-21.10.x
    {
        $service = (new Service())
            ->setId(1)
            ->setDisplayName('test');

        $host = (new Host())
            ->setId(2)
            ->setDisplayName('test');
        $host->addService($service);

        $serviceGroup = (new ServiceGroup())
            ->setId(3)
            ->setHosts([$host]);

        $this->monitoringRepository->expects(self::any())
            ->method('findServiceGroupsByHostAndService')
            ->with($host->getId(), $service->getId())
            ->willReturn([$serviceGroup]); // values returned for the all next tests

        $monitoringService = new MonitoringService(
            $this->monitoringRepository,
            $this->accessGroupRepository,
            $this->serviceConfiguration,
<<<<<<< HEAD
            $this->hostConfiguration
=======
            $this->hostConfiguration,
            $this->monitoringServerService
>>>>>>> centreon/dev-21.10.x
        );

        /**
         * @var ServiceGroup[] $servicesGroupsFound
         */
        $servicesGroupsFound = $monitoringService->findServiceGroupsByHostAndService($host->getId(), $service->getId());
        $this->assertCount(
            1,
            $servicesGroupsFound,
            "Error, this method must relay the 'findServiceGroupsByHostAndService' method of the monitoring repository"
        );
        $this->assertEquals($serviceGroup->getId(), $servicesGroupsFound[0]->getId());
        $this->assertEquals($host->getId(), $serviceGroup->getHosts()[0]->getId());
        $this->assertEquals($service->getId(), $serviceGroup->getHosts()[0]->getServices()[0]->getId());
    }
}

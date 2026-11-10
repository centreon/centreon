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

namespace Tests\Centreon\Domain\MonitoringServer\UseCase;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\MonitoringServer\Exception\ConfigurationMonitoringServerException;
use Centreon\Domain\MonitoringServer\Exception\GorgoneDeliveryException;
use Centreon\Domain\MonitoringServer\Exception\PartialDeliveryException;
use Centreon\Domain\MonitoringServer\Interfaces\MonitoringServerConfigurationRepositoryInterface;
use Centreon\Domain\MonitoringServer\Interfaces\MonitoringServerRepositoryInterface;
use Centreon\Domain\MonitoringServer\MonitoringServer;
use Centreon\Domain\MonitoringServer\UseCase\ReloadAllConfigurations;
use Centreon\Domain\Repository\RepositoryException;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ReloadAllConfigurationsTest extends TestCase
{
    /** @var MonitoringServerRepositoryInterface&MockObject */
    private $monitoringServerRepository;

    /** @var MonitoringServerConfigurationRepositoryInterface&MockObject */
    private $monitoringServerConfigurationRepository;

    /** @var ReadAccessGroupRepositoryInterface&MockObject */
    private $readAccessGroupRepository;

    /** @var ContactInterface&MockObject */
    private $contact;

    protected function setUp(): void
    {
        $this->monitoringServerRepository = $this->createMock(MonitoringServerRepositoryInterface::class);
        $this->monitoringServerConfigurationRepository
            = $this->createMock(MonitoringServerConfigurationRepositoryInterface::class);
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
        $this->contact = $this->createMock(ContactInterface::class);
    }

    public function testErrorRetrievingMonitoringServersException(): void
    {
        $this->contact
            ->expects($this->any())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->contact
            ->expects($this->any())
            ->method('isAdmin')
            ->willReturn(true);

        $exception = new \Exception();
        $this->monitoringServerRepository
            ->expects($this->once())
            ->method('findServersWithoutRequestParameters')
            ->willThrowException($exception);

        $this->expectException(ConfigurationMonitoringServerException::class);
        $this->expectExceptionMessage(
            ConfigurationMonitoringServerException::errorRetrievingMonitoringServers($exception)->getMessage()
        );
        $useCase = new ReloadAllConfigurations(
            $this->monitoringServerRepository,
            $this->monitoringServerConfigurationRepository,
            $this->readAccessGroupRepository,
            $this->contact
        );
        $useCase->execute();
    }

    public function testErrorAccessDeniedexception(): void
    {
        $this->contact
            ->expects($this->any())
            ->method('hasTopologyRole')
            ->willReturn(false);

        $this->contact
            ->expects($this->any())
            ->method('isAdmin')
            ->willReturn(false);

        $exception = new AccessDeniedException(
            'Insufficient rights (required: ROLE_CONFIGURATION_MONITORING_SERVER_READ or ROLE_CONFIGURATION_MONITORING_SERVER_READ_WRITE)'
        );

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage($exception->getMessage());
        $useCase = new ReloadAllConfigurations(
            $this->monitoringServerRepository,
            $this->monitoringServerConfigurationRepository,
            $this->readAccessGroupRepository,
            $this->contact
        );
        $useCase->execute();
    }

    public function testErrorOnReload(): void
    {
        $this->contact
            ->expects($this->any())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->contact
            ->expects($this->any())
            ->method('isAdmin')
            ->willReturn(true);

        $monitoringServers = [
            (new MonitoringServer())->setId(1),
        ];

        $repositoryException = new RepositoryException('Test exception message');

        $this->monitoringServerRepository
            ->expects($this->once())
            ->method('findServersWithoutRequestParameters')
            ->willReturn($monitoringServers);

        $this->monitoringServerConfigurationRepository
            ->expects($this->once())
            ->method('reloadConfigurationOfAll')
            ->willThrowException($repositoryException);

        $useCase = new ReloadAllConfigurations(
            $this->monitoringServerRepository,
            $this->monitoringServerConfigurationRepository,
            $this->readAccessGroupRepository,
            $this->contact
        );
        $this->expectException(ConfigurationMonitoringServerException::class);
        $this->expectExceptionMessage(
            ConfigurationMonitoringServerException::errorOfAll($repositoryException->getMessage())->getMessage()
        );
        $useCase->execute();
    }

    public function testSuccess(): void
    {
        $this->contact
            ->expects($this->any())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->contact
            ->expects($this->any())
            ->method('isAdmin')
            ->willReturn(true);

        $monitoringServer = (new MonitoringServer())->setId(1);
        $monitoringServers = [$monitoringServer];
        $this->monitoringServerRepository
            ->expects($this->once())
            ->method('findServersWithoutRequestParameters')
            ->willReturn($monitoringServers);

        $this->monitoringServerConfigurationRepository
            ->expects($this->once())
            ->method('reloadConfigurationOfAll')
            ->with([$monitoringServer->getId()]);

        $useCase = new ReloadAllConfigurations(
            $this->monitoringServerRepository,
            $this->monitoringServerConfigurationRepository,
            $this->readAccessGroupRepository,
            $this->contact
        );
        $useCase->execute();
    }

    public function testSuccessNonAdmin(): void
    {
        $this->contact
            ->expects($this->any())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->contact
            ->expects($this->any())
            ->method('isAdmin')
            ->willReturn(false);

        $monitoringServer = (new MonitoringServer())->setId(1);
        $monitoringServers = [$monitoringServer];
        $this->monitoringServerRepository
            ->expects($this->once())
            ->method('findAllServersWithAccessGroups')
            ->willReturn($monitoringServers);

        $this->monitoringServerConfigurationRepository
            ->expects($this->once())
            ->method('reloadConfigurationOfAll')
            ->with([$monitoringServer->getId()]);

        $useCase = new ReloadAllConfigurations(
            $this->monitoringServerRepository,
            $this->monitoringServerConfigurationRepository,
            $this->readAccessGroupRepository,
            $this->contact
        );
        $useCase->execute();
    }

    public function testEveryMonitoringServerIsReloadedAtOnce(): void
    {
        $this->contact->method('isAdmin')->willReturn(true);
        $this->monitoringServerRepository
            ->method('findServersWithoutRequestParameters')
            ->willReturn([(new MonitoringServer())->setId(3), (new MonitoringServer())->setId(1)]);
        $this->monitoringServerConfigurationRepository
            ->expects($this->once())
            ->method('reloadConfigurationOfAll')
            ->with([3, 1]);

        $this->createUseCase()->execute();
    }

    public function testDeliveredMonitoringServersAreReloadedBeforeTheDeliveryErrorIsThrown(): void
    {
        $partialDelivery = new PartialDeliveryException(
            '1 of 3 monitoring servers have not been delivered, the others have: Poller-3: not connected',
            GorgoneDeliveryException::NODE_NOT_CONNECTED,
            [2, 1]
        );
        $this->monitoringServerRepository->expects($this->never())->method('findServersWithoutRequestParameters');
        $this->monitoringServerConfigurationRepository
            ->expects($this->once())
            ->method('reloadConfigurationOfAll')
            ->with([2, 1]);

        try {
            $this->createUseCase()->executeAfterPartialDelivery($partialDelivery);
        } catch (PartialDeliveryException $ex) {
            $this->assertSame($partialDelivery, $ex);
        }
    }

    #[DataProvider('provideReloadFailuresAfterPartialDelivery')]
    public function testReloadFailureAfterPartialDeliveryNamesBoth(
        int $deliveryReason,
        int $reloadReason,
        int $expectedReason,
    ): void {
        $partialDelivery = new PartialDeliveryException('Poller-3 not delivered.', $deliveryReason, [2, 1]);
        $this->monitoringServerConfigurationRepository
            ->method('reloadConfigurationOfAll')
            ->willThrowException(new GorgoneDeliveryException('Poller-2 not reloaded.', $reloadReason));

        try {
            $this->createUseCase()->executeAfterPartialDelivery($partialDelivery);
        } catch (GorgoneDeliveryException $ex) {
            $this->assertNotInstanceOf(PartialDeliveryException::class, $ex);
            $this->assertSame('Poller-3 not delivered. Poller-2 not reloaded.', $ex->getMessage());
            $this->assertSame($expectedReason, $ex->getCode());
            $this->assertSame($partialDelivery, $ex->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function provideReloadFailuresAfterPartialDelivery(): iterable
    {
        yield 'timeouts only (504)' => [
            GorgoneDeliveryException::RESULT_TIMEOUT,
            GorgoneDeliveryException::RESULT_TIMEOUT,
            GorgoneDeliveryException::RESULT_TIMEOUT,
        ];

        yield 'different reasons (409)' => [
            GorgoneDeliveryException::RESULT_TIMEOUT,
            GorgoneDeliveryException::NODE_NOT_CONNECTED,
            GorgoneDeliveryException::DELIVERY_FAILED,
        ];
    }

    private function createUseCase(): ReloadAllConfigurations
    {
        return new ReloadAllConfigurations(
            $this->monitoringServerRepository,
            $this->monitoringServerConfigurationRepository,
            $this->readAccessGroupRepository,
            $this->contact
        );
    }
}

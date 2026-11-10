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
use Centreon\Domain\MonitoringServer\Exception\IncompleteGenerationException;
use Centreon\Domain\MonitoringServer\Exception\PartialDeliveryException;
use Centreon\Domain\MonitoringServer\Interfaces\MonitoringServerConfigurationRepositoryInterface;
use Centreon\Domain\MonitoringServer\Interfaces\MonitoringServerRepositoryInterface;
use Centreon\Domain\MonitoringServer\MonitoringServer;
use Centreon\Domain\MonitoringServer\UseCase\GenerateAllConfigurations;
use Centreon\Domain\Repository\RepositoryException;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class GenerateAllConfigurationsTest extends TestCase
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
        $useCase = new GenerateAllConfigurations(
            $this->monitoringServerRepository,
            $this->monitoringServerConfigurationRepository,
            $this->readAccessGroupRepository,
            $this->contact
        );
        $useCase->execute();
    }

    public function testErrorAccessDeniedException(): void
    {
        $this->contact
            ->expects($this->any())
            ->method('hasTopologyRole')
            ->willReturn(false);

        $exception = new AccessDeniedException(
            'Insufficient rights (required: ROLE_CONFIGURATION_MONITORING_SERVER_READ or ROLE_CONFIGURATION_MONITORING_SERVER_READ_WRITE)'
        );

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage(
            $exception->getMessage()
        );
        $useCase = new GenerateAllConfigurations(
            $this->monitoringServerRepository,
            $this->monitoringServerConfigurationRepository,
            $this->readAccessGroupRepository,
            $this->contact
        );
        $useCase->execute();
    }

    public function testErrorOnGeneration(): void
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
            ->method('generateConfiguration')
            ->willThrowException($repositoryException);

        $this->monitoringServerConfigurationRepository
            ->expects($this->never())
            ->method('moveExportFilesOfAll');

        $useCase = new GenerateAllConfigurations(
            $this->monitoringServerRepository,
            $this->monitoringServerConfigurationRepository,
            $this->readAccessGroupRepository,
            $this->contact
        );

        $this->expectException(ConfigurationMonitoringServerException::class);
        $this->expectExceptionMessage(ConfigurationMonitoringServerException::errorOnGeneration(
            $monitoringServers[0]->getId(),
            $repositoryException->getMessage()
        )->getMessage());
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
            ->method('generateConfiguration')
            ->with($monitoringServer->getId());

        $this->monitoringServerConfigurationRepository
            ->expects($this->once())
            ->method('moveExportFilesOfAll')
            ->with([$monitoringServer->getId()]);

        $useCase = new GenerateAllConfigurations(
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
            ->method('generateConfiguration')
            ->with($monitoringServer->getId());

        $this->monitoringServerConfigurationRepository
            ->expects($this->once())
            ->method('moveExportFilesOfAll')
            ->with([$monitoringServer->getId()]);

        $useCase = new GenerateAllConfigurations(
            $this->monitoringServerRepository,
            $this->monitoringServerConfigurationRepository,
            $this->readAccessGroupRepository,
            $this->contact
        );

        $useCase->execute();
    }

    public function testEveryConfigurationIsGeneratedBeforeAllAreMovedAtOnce(): void
    {
        $this->contact->method('isAdmin')->willReturn(true);
        $this->monitoringServerRepository
            ->method('findServersWithoutRequestParameters')
            ->willReturn([
                (new MonitoringServer())->setId(3),
                (new MonitoringServer())->setId(2)->setActivate(false),
                (new MonitoringServer())->setId(1),
            ]);

        $calls = [];
        $this->monitoringServerConfigurationRepository
            ->method('generateConfiguration')
            ->willReturnCallback(function (int $id) use (&$calls): void {
                $calls[] = 'generate ' . $id;
            });
        $this->monitoringServerConfigurationRepository
            ->expects($this->once())
            ->method('moveExportFilesOfAll')
            ->willReturnCallback(function (array $ids) use (&$calls): void {
                $calls[] = 'move ' . implode(',', $ids);
            });

        $this->createUseCase()->execute();

        // the disabled monitoring server is neither generated nor moved
        $this->assertSame(['generate 3', 'generate 1', 'move 3,1'], $calls);
    }

    public function testGenerationFailureMovesNothing(): void
    {
        $this->contact->method('isAdmin')->willReturn(true);
        $this->monitoringServerRepository
            ->method('findServersWithoutRequestParameters')
            ->willReturn([(new MonitoringServer())->setId(3), (new MonitoringServer())->setId(2)]);
        $this->monitoringServerConfigurationRepository
            ->method('generateConfiguration')
            ->willReturnCallback(function (int $id): void {
                if ($id === 2) {
                    throw new RepositoryException('generation failed');
                }
            });
        $this->monitoringServerConfigurationRepository->expects($this->never())->method('moveExportFilesOfAll');

        $this->expectException(ConfigurationMonitoringServerException::class);
        $this->expectExceptionMessage('Generation error on monitoring server #2: generation failed');
        $this->createUseCase()->execute();
    }

    #[DataProvider('provideMoveErrorsKeptAsIs')]
    public function testMoveErrorIsKeptAsIs(\Exception $error): void
    {
        $this->contact->method('isAdmin')->willReturn(true);
        $this->monitoringServerRepository
            ->method('findServersWithoutRequestParameters')
            ->willReturn([(new MonitoringServer())->setId(1)]);
        $this->monitoringServerConfigurationRepository->method('moveExportFilesOfAll')->willThrowException($error);

        try {
            $this->createUseCase()->execute();
            $this->fail('the move error is expected');
        } catch (\Exception $ex) {
            $this->assertSame($error, $ex);
        }
    }

    /**
     * @return iterable<string, array{\Exception}>
     */
    public static function provideMoveErrorsKeptAsIs(): iterable
    {
        yield 'some monitoring servers delivered (their ids are kept for the reload)' => [
            new PartialDeliveryException('1 of 2 not delivered', GorgoneDeliveryException::DELIVERY_FAILED, [1]),
        ];

        yield 'not delivered' => [
            new GorgoneDeliveryException('node 1 not connected', GorgoneDeliveryException::NODE_NOT_CONNECTED),
        ];

        yield 'last generation incomplete' => [
            new IncompleteGenerationException('incomplete', IncompleteGenerationException::REASON),
        ];
    }

    public function testMoveFailureIsNotAGenerationError(): void
    {
        $this->contact->method('isAdmin')->willReturn(true);
        $this->monitoringServerRepository
            ->method('findServersWithoutRequestParameters')
            ->willReturn([(new MonitoringServer())->setId(1)]);
        $this->monitoringServerConfigurationRepository
            ->method('moveExportFilesOfAll')
            ->willThrowException(new RepositoryException('Could not write into centcore.cmd'));

        $this->expectException(ConfigurationMonitoringServerException::class);
        $this->expectExceptionMessage('Error on the monitoring servers: Could not write into centcore.cmd');
        $this->createUseCase()->execute();
    }

    private function createUseCase(): GenerateAllConfigurations
    {
        return new GenerateAllConfigurations(
            $this->monitoringServerRepository,
            $this->monitoringServerConfigurationRepository,
            $this->readAccessGroupRepository,
            $this->contact
        );
    }
}

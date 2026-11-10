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

use Centreon\Domain\Authentication\Exception\AuthenticationException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Exception\TimeoutException;
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

        try {
            $useCase->execute();
            $this->fail('A PartialDeliveryException was expected');
        } catch (PartialDeliveryException $ex) {
            $this->assertSame(
                '1 of 1 monitoring servers have not been generated, the others have: #1: Test exception message',
                $ex->getMessage()
            );
            $this->assertSame(GorgoneDeliveryException::DELIVERY_FAILED, $ex->getCode());
            $this->assertSame([], $ex->getDeliveredMonitoringServerIds());
        }
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

    public function testGenerationFailureDoesNotStopTheOthers(): void
    {
        $this->givenMonitoringServers(4, 3, 2, 1);
        $this->monitoringServerConfigurationRepository
            ->method('generateConfiguration')
            ->willReturnCallback(function (int $id): void {
                match ($id) {
                    4 => throw new RepositoryException('generation failed'),
                    2 => throw new RepositoryException('Could not write the engine configuration'),
                    default => null,
                };
            });
        $this->monitoringServerConfigurationRepository
            ->expects($this->once())
            ->method('moveExportFilesOfAll')
            ->with([3, 1]);

        try {
            $this->createUseCase()->execute();
            $this->fail('A PartialDeliveryException was expected');
        } catch (PartialDeliveryException $ex) {
            $this->assertSame(
                '2 of 4 monitoring servers have not been generated, the others have: '
                    . 'Poller-4: generation failed | Poller-2: Could not write the engine configuration',
                $ex->getMessage()
            );
            // a generation failure is refused as an incomplete generation would be: 409
            $this->assertSame(GorgoneDeliveryException::DELIVERY_FAILED, $ex->getCode());
            $this->assertSame([3, 1], $ex->getDeliveredMonitoringServerIds());
        }
    }

    public function testNoOtherGenerationIsStartedAfterAGenerationTimeout(): void
    {
        $this->givenMonitoringServers(3, 2, 1);
        $this->monitoringServerConfigurationRepository
            ->expects($this->exactly(2))
            ->method('generateConfiguration')
            ->willReturnCallback(function (int $id): void {
                if ($id === 2) {
                    throw new TimeoutException('did not finish within 60 seconds');
                }
            });
        $this->monitoringServerConfigurationRepository
            ->expects($this->once())
            ->method('moveExportFilesOfAll')
            ->with([3]);

        try {
            $this->createUseCase()->execute();
            $this->fail('A PartialDeliveryException was expected');
        } catch (PartialDeliveryException $ex) {
            $this->assertSame(
                '2 of 3 monitoring servers have not been generated, the others have: '
                    . 'Poller-2: did not finish within 60 seconds | Poller-1: not generated, the generation of a '
                    . 'previous monitoring server did not finish in time',
                $ex->getMessage()
            );
            $this->assertSame([3], $ex->getDeliveredMonitoringServerIds());
        }
    }

    public function testGenerationAndDeliveryFailuresAreNamedTogether(): void
    {
        $this->givenMonitoringServers(3, 2, 1);
        $this->monitoringServerConfigurationRepository
            ->method('generateConfiguration')
            ->willReturnCallback(function (int $id): void {
                if ($id === 3) {
                    throw new RepositoryException('');
                }
            });
        $deliveryError = new PartialDeliveryException(
            '1 of 2 monitoring servers have not been delivered, the others have: Poller-2: timeout',
            GorgoneDeliveryException::RESULT_TIMEOUT,
            [1]
        );
        $this->monitoringServerConfigurationRepository
            ->method('moveExportFilesOfAll')
            ->with([2, 1])
            ->willThrowException($deliveryError);

        try {
            $this->createUseCase()->execute();
            $this->fail('A PartialDeliveryException was expected');
        } catch (PartialDeliveryException $ex) {
            $this->assertSame(
                '1 of 3 monitoring servers have not been generated, the others have: '
                    . 'Poller-3: the monitoring engine reported errors in its configuration '
                    . '1 of 2 monitoring servers have not been delivered, the others have: Poller-2: timeout',
                $ex->getMessage()
            );
            // not only delivery timeouts: 409
            $this->assertSame(GorgoneDeliveryException::DELIVERY_FAILED, $ex->getCode());
            $this->assertSame([1], $ex->getDeliveredMonitoringServerIds());
            $this->assertSame($deliveryError, $ex->getPrevious());
        }
    }

    public function testNothingIsMovedWhenNothingIsGenerated(): void
    {
        $this->givenMonitoringServers(2, 1);
        $this->monitoringServerConfigurationRepository
            ->method('generateConfiguration')
            ->willThrowException(new RepositoryException('generation failed'));
        $this->monitoringServerConfigurationRepository->expects($this->never())->method('moveExportFilesOfAll');

        $this->expectException(PartialDeliveryException::class);
        $this->expectExceptionMessage('2 of 2 monitoring servers have not been generated');
        $this->createUseCase()->execute();
    }

    public function testAuthenticationFailureStopsTheGeneration(): void
    {
        $this->givenMonitoringServers(2, 1);
        $this->monitoringServerConfigurationRepository
            ->expects($this->once())
            ->method('generateConfiguration')
            ->willThrowException(AuthenticationException::authenticationTokenExpired());
        $this->monitoringServerConfigurationRepository->expects($this->never())->method('moveExportFilesOfAll');

        $this->expectException(ConfigurationMonitoringServerException::class);
        $this->expectExceptionMessage('Generation error on monitoring server #2');
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

    private function givenMonitoringServers(int ...$ids): void
    {
        $this->contact->method('isAdmin')->willReturn(true);
        $this->monitoringServerRepository
            ->method('findServersWithoutRequestParameters')
            ->willReturn(array_map(
                static fn (int $id): MonitoringServer => (new MonitoringServer())->setId($id)->setName('Poller-' . $id),
                $ids
            ));
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

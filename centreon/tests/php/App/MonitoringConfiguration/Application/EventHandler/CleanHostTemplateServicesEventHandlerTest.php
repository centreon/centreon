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

namespace Tests\App\MonitoringConfiguration\Application\EventHandler;

use App\MonitoringConfiguration\Application\Command\DeleteHostTemplateServiceCommand;
use App\MonitoringConfiguration\Application\EventHandler\CleanHostTemplateServicesEventHandler;
use App\MonitoringConfiguration\Application\Service\HostTemplateServicesCleaner;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Event\HostTemplateServicesCleanupRequested;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceRepository;
use Tests\App\Shared\Double\CommandBusSpy;
use Tests\App\Shared\Infrastructure\Messenger\Double\LoggerSpy;

final class CleanHostTemplateServicesEventHandlerTest extends TestCase
{
    private const HOST_ID = 12;

    private FakeHostTemplateRepository $templateRepository;

    private FakeServiceRepository $serviceRepository;

    private CommandBusSpy $commandBus;

    private LoggerSpy $logger;

    /** @var list<int> */
    private array $serviceIds = [];

    protected function setUp(): void
    {
        $this->templateRepository = new FakeHostTemplateRepository();
        $this->serviceRepository = new FakeServiceRepository();
        $this->commandBus = new CommandBusSpy();
        $this->logger = new LoggerSpy();

        // Template 3 is removed, template 4 kept: the services of service templates 30 and 31 go.
        $this->templateRepository->serviceTemplateIdsByTemplate = [3 => [30, 31], 4 => [40]];
        $this->serviceIds = [$this->seedService(30), $this->seedService(31)];
        $this->seedService(40);
    }

    public function testItRequestsTheDeletionOfEachServiceOfTheRemovedTemplatesForTheActor(): void
    {
        $this->handle();

        self::assertEqualsCanonicalizing(
            array_map(static fn (int $id): array => [$id, self::HOST_ID, 7], $this->serviceIds),
            array_map(
                static function (object $command): array {
                    self::assertInstanceOf(DeleteHostTemplateServiceCommand::class, $command);

                    return [$command->serviceId->value, $command->hostId->value, $command->deletedBy->value];
                },
                $this->commandBus->executed,
            ),
        );
        self::assertSame([], $this->logger->errorMessages);
    }

    public function testTheOtherServicesAreStillCleanedWhenOneFails(): void
    {
        $failing = $this->serviceIds[0];
        $this->commandBus->failsFor = static fn (object $command): bool => $command instanceof DeleteHostTemplateServiceCommand
            && $command->serviceId->value === $failing;

        $this->handle();

        self::assertCount(2, $this->commandBus->executed);
    }

    /**
     * Delivered after the commit, so nothing can be rolled back: an escaping exception would turn a
     * saved host into a 5xx.
     */
    public function testAFailingServiceNeverEscapes(): void
    {
        $this->commandBus->throws = true;

        $this->handle();

        self::assertCount(2, $this->commandBus->executed);
    }

    public function testAFailureBeforeAnyServiceNeverEscapes(): void
    {
        $this->templateRepository->findServiceTemplateIdsThrows = new \RuntimeException('The database failed.');

        $this->handle();

        self::assertSame([], $this->commandBus->executed);
    }

    public function testEachFailingServiceIsLoggedAtErrorWithTheHostAndTheService(): void
    {
        $this->commandBus->throws = true;

        $this->handle();

        $loggedServiceIds = [];
        foreach ($this->logger->errorMessages as $entry) {
            self::assertSame(self::HOST_ID, $entry['context']['host_id']);
            self::assertInstanceOf(\RuntimeException::class, $entry['context']['exception']);
            $loggedServiceIds[] = $entry['context']['service_id'];
        }
        self::assertEqualsCanonicalizing($this->serviceIds, $loggedServiceIds);
    }

    public function testAFailureBeforeAnyServiceIsLoggedAtErrorWithTheHost(): void
    {
        $this->templateRepository->findServiceTemplateIdsThrows = new \RuntimeException('The database failed.');
        $failure = $this->templateRepository->findServiceTemplateIdsThrows;

        $this->handle();

        self::assertCount(1, $this->logger->errorMessages);
        $context = $this->logger->errorMessages[0]['context'];
        self::assertSame(self::HOST_ID, $context['host_id']);
        self::assertArrayNotHasKey('service_id', $context);
        self::assertSame($failure, $context['exception']);
    }

    public function testAFailingLoggerIsSwallowedToo(): void
    {
        $this->commandBus->throws = true;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('error')->willThrowException(new \RuntimeException('The logger failed.'));

        $this->handle($logger);

        self::assertCount(2, $this->commandBus->executed);
    }

    private function handle(?LoggerInterface $logger = null): void
    {
        $handler = new CleanHostTemplateServicesEventHandler(
            new HostTemplateServicesCleaner($this->templateRepository, $this->serviceRepository),
            $this->commandBus,
            $logger ?? $this->logger,
        );

        $handler(new HostTemplateServicesCleanupRequested(
            new HostId(self::HOST_ID),
            new Collection([new HostTemplateId(3), new HostTemplateId(4)], HostTemplateId::class),
            new Collection([new HostTemplateId(4)], HostTemplateId::class),
            new UserId(7),
        ));
    }

    private function seedService(int $serviceTemplateId): int
    {
        $service = new Service(
            id: null,
            name: new ServiceName('service-' . $serviceTemplateId),
            hostId: new HostId(self::HOST_ID),
            hostActivated: true,
            hostPollerId: null,
        );
        $this->serviceRepository->add($service);
        $this->serviceRepository->serviceTemplateIdOf[$service->id()->value] = $serviceTemplateId;

        return $service->id()->value;
    }
}

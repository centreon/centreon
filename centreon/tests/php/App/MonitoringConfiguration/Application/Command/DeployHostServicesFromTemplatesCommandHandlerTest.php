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

namespace Tests\App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Application\Command\DeployHostServicesFromTemplatesCommand;
use App\MonitoringConfiguration\Application\Command\DeployHostServicesFromTemplatesCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostState;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
use App\MonitoringConfiguration\Domain\Event\ServiceCreated;
use App\MonitoringConfiguration\Domain\Exception\InvalidServiceTemplateAliasException;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Application\Command\NonTransactionalCommand;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\Event\EventInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceTemplateRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;
use Tests\App\Shared\Double\EventBusSpy;

final class DeployHostServicesFromTemplatesCommandHandlerTest extends KernelTestCase
{
    private const HOST_ID = 10;
    private const ACTOR_ID = 5;

    private DeployHostServicesFromTemplatesCommandHandler $handler;

    private FakeServiceRepository $serviceRepository;

    private FakeServiceTemplateRepository $serviceTemplateRepository;

    private EventBusSpy $eventBus;

    /**
     * Boots the real container and swaps only the repositories and the event bus for fakes, so the handler and its
     * deployer are built by Symfony's DI exactly as in production.
     */
    protected function setUp(): void
    {
        $container = self::getContainer();

        $hostRepository = new FakeHostRepository();
        $hostRepository->states[self::HOST_ID] = new HostState(true, new PollerId(3));
        $this->serviceRepository = new FakeServiceRepository();
        $this->serviceTemplateRepository = new FakeServiceTemplateRepository();
        $this->eventBus = new EventBusSpy();

        $container->set(HostRepository::class, $hostRepository);
        $container->set(ResourceAccessRepository::class, $hostRepository->resourceAccess);
        $container->set(ServiceRepository::class, $this->serviceRepository);
        $container->set(ServiceTemplateRepository::class, $this->serviceTemplateRepository);
        $container->set(EventBus::class, $this->eventBus);

        /** @var DeployHostServicesFromTemplatesCommandHandler $handler */
        $handler = $container->get(DeployHostServicesFromTemplatesCommandHandler::class);
        $this->handler = $handler;
    }

    /**
     * The events are fired once every service is written, so a failing write leaves no real-time side effect of
     * the services written before it.
     */
    public function testItFiresOneServiceCreatedPerServiceAfterTheLastAdd(): void
    {
        $this->givenTemplates('ping', 'http', 'disk');
        $storedAtEachFire = [];
        $this->eventBus->onFire = function (EventInterface $event) use (&$storedAtEachFire): void {
            $storedAtEachFire[] = count($this->serviceRepository->services);
        };

        $services = ($this->handler)($this->command())->toArray();

        $events = $this->eventBus->getDispatchedEvents(ServiceCreated::class);
        self::assertSame($services, array_map(static fn (ServiceCreated $event): object => $event->aggregate, $events));
        self::assertSame([self::ACTOR_ID, self::ACTOR_ID, self::ACTOR_ID], array_map(static fn (ServiceCreated $event): int => $event->creatorId, $events));
        self::assertSame([3, 3, 3], $storedAtEachFire);
    }

    public function testItFiresNothingWhenNothingIsCreated(): void
    {
        $services = ($this->handler)($this->command());

        self::assertCount(0, $services);
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(ServiceCreated::class));
    }

    public function testItReturnsTheCreatedServices(): void
    {
        $this->givenTemplates('ping', 'http');

        $services = ($this->handler)($this->command())->toArray();

        self::assertSame(array_values($this->serviceRepository->services), $services);
        self::assertSame(['ping', 'http'], array_map(static fn (Service $service): string => $service->name->value, $services));
    }

    /**
     * Atomicity comes from the bus transaction, which a non-transactional command would skip.
     */
    public function testTheCommandIsTransactional(): void
    {
        self::assertFalse(new \ReflectionClass(DeployHostServicesFromTemplatesCommand::class)->implementsInterface(NonTransactionalCommand::class));
    }

    public function testAFailureOfTheDeployerPropagatesWithoutEvent(): void
    {
        $this->givenTemplates('ping', 'bad=alias');

        try {
            ($this->handler)($this->command());
            self::fail('The deployment must fail.');
        } catch (InvalidServiceTemplateAliasException) {
            self::assertSame([], $this->eventBus->getDispatchedEvents(ServiceCreated::class));
        }
    }

    private function command(): DeployHostServicesFromTemplatesCommand
    {
        return new DeployHostServicesFromTemplatesCommand(new HostId(self::HOST_ID), TestActingUsers::unrestricted(self::ACTOR_ID));
    }

    private function givenTemplates(string ...$aliases): void
    {
        $templates = [];
        foreach (array_values($aliases) as $index => $alias) {
            $templates[] = new ServiceTemplate(new ServiceTemplateId($index + 1), new ServiceTemplateName("template-{$index}"), alias: $alias);
        }
        $this->serviceTemplateRepository->deployableOnHost[self::HOST_ID] = $templates;
    }
}

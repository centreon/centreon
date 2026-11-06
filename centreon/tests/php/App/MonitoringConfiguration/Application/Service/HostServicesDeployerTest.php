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

namespace Tests\App\MonitoringConfiguration\Application\Service;

use App\MonitoringConfiguration\Application\Service\HostServicesDeployer;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostState;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\InvalidServiceTemplateAliasException;
use App\Security\Domain\Aggregate\ActingUser;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceTemplateRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;

final class HostServicesDeployerTest extends TestCase
{
    private const HOST_ID = 10;
    private const POLLER_ID = 3;
    private const RESTRICTED_USER_ID = 7;

    private FakeHostRepository $hostRepository;

    private FakeServiceTemplateRepository $serviceTemplateRepository;

    private FakeServiceRepository $serviceRepository;

    protected function setUp(): void
    {
        $this->hostRepository = new FakeHostRepository();
        $this->serviceTemplateRepository = new FakeServiceTemplateRepository();
        $this->serviceRepository = new FakeServiceRepository();
        $this->hostRepository->states[self::HOST_ID] = new HostState(true, new PollerId(self::POLLER_ID));
    }

    /**
     * The host is checked first: an unknown host, or one outside a restricted actor's scope, is not found even
     * when it has templates, and nothing is read or written.
     */
    #[DataProvider('unreachableHostProvider')]
    public function testAnUnknownOrOutOfScopeHostIsNotFoundEvenWithTemplates(int $hostId, ActingUser $actor): void
    {
        $this->serviceTemplateRepository->deployableOnHost[$hostId] = [$this->template(1, 'ping')];

        try {
            $this->deployer()->deploy(new HostId($hostId), $actor);
            self::fail('The host must not be found.');
        } catch (HostNotFoundException $exception) {
            self::assertSame(['hostId' => [$hostId]], $exception->criteria);
        }
        self::assertSame([], $this->serviceTemplateRepository->deployableOnHostRequests);
        self::assertSame([], $this->serviceRepository->services);
    }

    /**
     * @return iterable<string, array{int, ActingUser}>
     */
    public static function unreachableHostProvider(): iterable
    {
        yield 'unknown host' => [404, TestActingUsers::unrestricted()];

        yield 'host outside the actor scope' => [self::HOST_ID, TestActingUsers::restricted(self::RESTRICTED_USER_ID)];
    }

    public function testNothingIsCreatedWithoutCandidate(): void
    {
        $services = $this->deploy();

        self::assertCount(0, $services);
        self::assertSame([], $this->serviceRepository->services);
    }

    /**
     * Runs of two or more whitespace characters become one space, then the ends are trimmed: in that order, as
     * legacy, so a run mixing a space and a form feed at an end disappears.
     */
    #[DataProvider('normalizedAliasProvider')]
    public function testItNormalizesTheAliasCollapseThenTrim(string $alias, string $expectedName): void
    {
        $this->serviceTemplateRepository->deployableOnHost[self::HOST_ID] = [$this->template(1, $alias)];

        self::assertSame([$expectedName], $this->names($this->deploy()));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function normalizedAliasProvider(): iterable
    {
        yield 'inner and outer runs' => ['  a  b ', 'a b'];

        yield 'trailing line break' => ["Ping\n", 'Ping'];

        yield 'run of line breaks' => ["a\n\nb", 'a b'];

        yield 'run with a form feed at an end' => [" \fPing", 'Ping'];

        yield '200 characters' => [str_repeat('a', 200), str_repeat('a', 200)];

        yield '200 multibyte characters' => [str_repeat('é', 200), str_repeat('é', 200)];
    }

    #[DataProvider('invalidAliasProvider')]
    public function testItRefusesAnInvalidAlias(?string $alias, string $expectedReason): void
    {
        $this->serviceTemplateRepository->deployableOnHost[self::HOST_ID] = [$this->template(42, $alias)];

        try {
            $this->deploy();
            self::fail('The alias must be refused.');
        } catch (InvalidServiceTemplateAliasException $exception) {
            self::assertSame("The alias of service template #42 {$expectedReason}, no service was deployed.", $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function invalidAliasProvider(): iterable
    {
        yield 'null' => [null, 'is empty'];

        yield 'empty' => ['', 'is empty'];

        yield 'whitespace only' => [" \t\n ", 'is empty'];

        yield '201 characters' => [str_repeat('a', 201), 'is longer than 200 characters'];
        foreach (mb_str_split('~!$%^&*"|\'<>?,()=') as $character) {
            yield "forbidden {$character}" => ["Pi{$character}ng", 'contains a forbidden character (~ ! $ % ^ & * " | \' < > ? , ( ) =)'];
        }

        yield 'single inner tab' => ["Pi\tng", 'contains a control character'];

        yield 'inner null byte' => ["Pi\0ng", 'contains a control character'];

        yield 'delete character' => ["Ping\x7F", 'contains a control character'];
    }

    /**
     * Every name is validated before the first write: an invalid alias anywhere leaves nothing written.
     */
    public function testAnInvalidAliasFailsBeforeAnyWrite(): void
    {
        $this->serviceTemplateRepository->deployableOnHost[self::HOST_ID] = [
            $this->template(1, 'ping'),
            $this->template(2, 'bad=alias'),
            $this->template(3, 'http'),
        ];

        try {
            $this->deploy();
            self::fail('The deployment must fail.');
        } catch (InvalidServiceTemplateAliasException) {
            self::assertSame([], $this->serviceRepository->services);
        }
    }

    /**
     * The name is compared as the repository compares it, under the column collation (case-insensitive with the
     * fake): `ping` is not deployed next to `Ping`.
     */
    public function testItSkipsANameAlreadyOnTheHost(): void
    {
        $this->addExistingService('Ping');
        $this->serviceTemplateRepository->deployableOnHost[self::HOST_ID] = [$this->template(1, 'ping'), $this->template(2, 'http')];

        self::assertSame(['http'], $this->names($this->deploy()));
        self::assertCount(2, $this->serviceRepository->services);
    }

    /**
     * Two templates giving the same name: the first one, in the repository order, is the one deployed.
     */
    public function testItSkipsANameCreatedEarlierInTheSameDeployment(): void
    {
        $this->serviceTemplateRepository->deployableOnHost[self::HOST_ID] = [$this->template(5, 'ping'), $this->template(4, ' ping  ')];

        $services = $this->deploy()->toArray();

        self::assertCount(1, $services);
        self::assertSame([5, 'ping'], [$services[0]->templateId?->value, $services[0]->name->value]);
    }

    public function testItCreatesEnabledServicesFromTheirTemplates(): void
    {
        $this->hostRepository->states[self::HOST_ID] = new HostState(false, new PollerId(self::POLLER_ID));
        $this->serviceTemplateRepository->deployableOnHost[self::HOST_ID] = [
            $this->template(2, 'ping', groupIds: [20, 21]),
            $this->template(1, 'http'),
        ];

        $services = $this->deploy()->toArray();

        self::assertSame(array_values($this->serviceRepository->services), $services, 'Every service is persisted.');
        self::assertSame(['ping', 'http'], $this->names(new Collection($services, Service::class)));
        [$ping, $http] = $services;
        self::assertSame(
            [self::HOST_ID, false, self::POLLER_ID, true, 2, [20, 21]],
            [$ping->hostId->value, $ping->hostActivated, $ping->hostPollerId?->value, $ping->activated, $ping->templateId?->value, $this->groupIds($ping)],
        );
        self::assertSame([1, []], [$http->templateId?->value, $this->groupIds($http)]);
        foreach ($services as $service) {
            self::assertEquals($this->defaultNotifications(), $service->notifications);
            self::assertNull($service->checkOptions->checkCommandId);
            self::assertSame([], $service->checkOptions->macros);
            self::assertNull($service->dataProcessing->eventHandlerCommandId);
            self::assertNull($service->extendedInformations);
            self::assertNull($service->graphTemplateId);
            self::assertNull($service->severityId);
            self::assertCount(0, $service->categoryIds);
            self::assertCount(0, $service->trapIds);
        }
    }

    public function testOnCloudTheServicesHaveNoNotificationsBlock(): void
    {
        $this->serviceTemplateRepository->deployableOnHost[self::HOST_ID] = [$this->template(1, 'ping')];

        $services = $this->deployer(isCloudPlatform: true)->deploy(new HostId(self::HOST_ID), TestActingUsers::unrestricted())->toArray();

        self::assertCount(1, $services);
        self::assertNull($services[0]->notifications);
    }

    private function deployer(bool $isCloudPlatform = false): HostServicesDeployer
    {
        return new HostServicesDeployer(
            $this->hostRepository,
            $this->serviceTemplateRepository,
            $this->serviceRepository,
            $isCloudPlatform,
        );
    }

    /**
     * @return Collection<Service>
     */
    private function deploy(): Collection
    {
        return $this->deployer()->deploy(new HostId(self::HOST_ID), TestActingUsers::unrestricted());
    }

    /**
     * @param list<int> $groupIds
     */
    private function template(int $id, ?string $alias, array $groupIds = []): ServiceTemplate
    {
        return new ServiceTemplate(
            new ServiceTemplateId($id),
            new ServiceTemplateName("template-{$id}"),
            alias: $alias,
            serviceGroupIds: new Collection(
                array_map(static fn (int $groupId): ServiceGroupId => new ServiceGroupId($groupId), $groupIds),
                ServiceGroupId::class,
            ),
        );
    }

    private function addExistingService(string $name): void
    {
        $this->serviceRepository->add(new Service(
            id: null,
            name: new ServiceName($name),
            hostId: new HostId(self::HOST_ID),
            hostActivated: true,
            hostPollerId: null,
        ));
    }

    /**
     * The block a service created through the API gets when none is sent.
     */
    private function defaultNotifications(): ServiceNotifications
    {
        return new ServiceNotifications(
            enabled: TriStateEnum::UseDefault,
            contactIds: new Collection([], NotificationContactId::class),
            contactGroupIds: new Collection([], ContactGroupId::class),
        );
    }

    /**
     * @param Collection<Service> $services
     *
     * @return list<string>
     */
    private function names(Collection $services): array
    {
        return array_values(array_map(static fn (Service $service): string => $service->name->value, $services->toArray()));
    }

    /**
     * @return list<int>
     */
    private function groupIds(Service $service): array
    {
        return array_values(array_map(static fn (ServiceGroupId $id): int => $id->value, $service->serviceGroupIds->toArray()));
    }
}

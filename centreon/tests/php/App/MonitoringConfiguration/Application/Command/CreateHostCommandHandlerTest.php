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

use App\MonitoringConfiguration\Application\Command\CreateHostCommand;
use App\MonitoringConfiguration\Application\Command\CreateHostCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategory;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroup;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupName;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\BrokerInformation;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\ConnectorConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\EngineInformation;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\GorgoneConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerUid;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\TrapConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\Timezone;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneName;
use App\MonitoringConfiguration\Domain\Event\HostCreated;
use App\MonitoringConfiguration\Domain\Event\HostServicesDeploymentRequested;
use App\MonitoringConfiguration\Domain\Exception\CircularHostRelationException;
use App\MonitoringConfiguration\Domain\Exception\HostAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostCategoryNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostSeverityNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\PollerNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimezoneNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\VaultInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostCategoryRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostSeverityRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakePollerRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimezoneRepository;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Shared\Double\EventBusSpy;
use Tests\App\Shared\Double\FakeVault;

final class CreateHostCommandHandlerTest extends KernelTestCase
{
    private CreateHostCommandHandler $handler;

    private FakeHostRepository $hostRepository;

    private FakePollerRepository $pollerRepository;

    private FakeHostGroupRepository $hostGroupRepository;

    private FakeResourceAccessRepository $resourceAccessRepository;

    private EventBusSpy $eventBus;

    private FakeVault $vault;

    private FakeHostCategoryRepository $hostCategoryRepository;

    private FakeHostSeverityRepository $hostSeverityRepository;

    private FakeTimezoneRepository $timezoneRepository;

    private FakeHostTemplateRepository $hostTemplateRepository;

    /**
     * Boots the real container and swaps only the repositories and the event bus for fakes, so
     * the handler itself is built by Symfony's DI exactly as it is in production — catching a
     * wiring break (a constructor argument the service config no longer knows how to autowire)
     * that a hand-instantiated handler would silently miss.
     */
    protected function setUp(): void
    {
        $container = self::getContainer();

        $this->hostRepository = new FakeHostRepository();
        $this->pollerRepository = new FakePollerRepository();
        $this->hostGroupRepository = new FakeHostGroupRepository();
        $this->resourceAccessRepository = new FakeResourceAccessRepository();
        $this->eventBus = new EventBusSpy();
        $this->vault = new FakeVault();
        $this->hostCategoryRepository = new FakeHostCategoryRepository();
        $this->hostSeverityRepository = new FakeHostSeverityRepository();
        $this->timezoneRepository = new FakeTimezoneRepository();
        $this->hostTemplateRepository = new FakeHostTemplateRepository();
        $this->vault->vaultEnabled = false;

        $container->set(HostRepository::class, $this->hostRepository);
        $container->set(PollerRepository::class, $this->pollerRepository);
        $container->set(HostGroupRepository::class, $this->hostGroupRepository);
        $container->set(ResourceAccessRepository::class, $this->resourceAccessRepository);
        $container->set(EventBus::class, $this->eventBus);
        $container->set(VaultInterface::class, $this->vault);
        $container->set(HostCategoryRepository::class, $this->hostCategoryRepository);
        $container->set(HostSeverityRepository::class, $this->hostSeverityRepository);
        $container->set(TimezoneRepository::class, $this->timezoneRepository);
        $container->set(HostTemplateRepository::class, $this->hostTemplateRepository);

        /** @var CreateHostCommandHandler $handler */
        $handler = $container->get(CreateHostCommandHandler::class);
        $this->handler = $handler;
    }

    public function testItCreatesTheHost(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);

        ($this->handler)(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
        ));

        self::assertTrue($this->hostRepository->isNameUsedByHostOrTemplate(new HostName('server-01')));
    }

    public function testItRejectsADuplicateName(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);

        $command = new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
        );
        ($this->handler)($command);

        $this->expectException(HostAlreadyExistsException::class);

        ($this->handler)($command);
    }

    public function testItRejectsAnUnknownPoller(): void
    {
        $this->expectException(PollerNotFoundException::class);

        ($this->handler)(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: new PollerId(404),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
        ));
    }

    public function testItRejectsAnUnknownHostGroup(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);

        $this->expectException(HostGroupNotFoundException::class);

        ($this->handler)(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([new HostGroupId(404)], HostGroupId::class),
            creatorId: 1,
        ));
    }

    public function testItAcceptsAnExistingHostGroup(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $this->hostGroupRepository->hostGroups[5] = new HostGroup(id: new HostGroupId(5), name: new HostGroupName('Linux servers'));

        ($this->handler)(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([new HostGroupId(5)], HostGroupId::class),
            creatorId: 1,
        ));

        self::assertTrue($this->hostRepository->isNameUsedByHostOrTemplate(new HostName('server-01')));
    }

    public function testItDispatchesHostCreated(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);

        ($this->handler)(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
        ));

        self::assertTrue($this->eventBus->shouldHaveDispatched(HostCreated::class));
    }

    public function testARestrictedViewerCanCreateAHostOnAnAccessiblePoller(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        // this specific poller is accessible
        $this->resourceAccessRepository->unrestrictedPollerAccess = true;

        ($this->handler)(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
            viewerId: new UserId(7),
        ));

        self::assertTrue($this->hostRepository->isNameUsedByHostOrTemplate(new HostName('server-01')));
    }

    /**
     * A restricted viewer referencing a poller outside their own ACL scope gets the same
     * not-found error as a truly nonexistent poller — legacy does not distinguish the two,
     * to avoid leaking existence information.
     */
    public function testARestrictedViewerCannotCreateAHostOnAnInaccessiblePoller(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $this->resourceAccessRepository->unrestrictedPollerAccess = false;

        $this->expectException(PollerNotFoundException::class);

        ($this->handler)(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
            viewerId: new UserId(7),
        ));
    }

    public function testARestrictedViewerCannotReferenceAHostGroupOutsideTheirAccessibleScope(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $this->hostGroupRepository->hostGroups[5] = new HostGroup(id: new HostGroupId(5), name: new HostGroupName('Linux servers'));
        // Restricted to a different set of host groups than the one being requested (5).
        $this->resourceAccessRepository->accessibleHostGroupIds = new Collection([new HostGroupId(9)], HostGroupId::class);

        $this->expectException(HostGroupNotFoundException::class);

        ($this->handler)(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([new HostGroupId(5)], HostGroupId::class),
            creatorId: 1,
            viewerId: new UserId(7),
        ));
    }

    /**
     * References are authorized before the name is looked up, so a restricted viewer cannot tell
     * a duplicate name apart from an inaccessible poller — both surface as the same
     * PollerNotFoundException, never HostAlreadyExistsException, closing an enumeration channel
     * (a restricted viewer could otherwise learn a name is already taken by some host or template
     * they can't even see, just by observing whether they get a 409 or a 404).
     */
    public function testARestrictedViewerGetsPollerNotFoundNotDuplicateNameForAnInaccessiblePollerWithADuplicateName(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $this->resourceAccessRepository->unrestrictedPollerAccess = false;

        $existingName = new HostName('server-01');
        $this->hostRepository->add(new Host(
            id: null,
            name: $existingName,
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: $poller->id(),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
        ));

        $this->expectException(PollerNotFoundException::class);

        ($this->handler)(new CreateHostCommand(
            name: $existingName,
            address: new HostAddress('127.0.0.2'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
            viewerId: new UserId(7),
        ));
    }

    public function testItKeepsTheSnmpCommunityInPlaintextWhenNoVaultIsConfigured(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $this->vault->vaultEnabled = false;

        $host = ($this->handler)($this->snmpCommand($poller->id(), 'public'));

        self::assertSame('public', $host->snmpCommunity?->value);
        self::assertSame([], $this->vault->writeManyCalls);
    }

    public function testItStoresTheSnmpCommunityInTheVaultWhenOneIsConfigured(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $this->vault->vaultEnabled = true;
        $this->vault->writtenPaths[CreateHostCommandHandler::HOST_SNMP_COMMUNITY_KEY] = 'secret::vault::monitoring/hosts/abc::_HOSTSNMPCOMMUNITY';

        $host = ($this->handler)($this->snmpCommand($poller->id(), 'public'));

        self::assertSame('secret::vault::monitoring/hosts/abc::_HOSTSNMPCOMMUNITY', $host->snmpCommunity?->value);
    }

    public function testItDoesNotTouchTheVaultWhenNoSnmpCommunityIsGiven(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $this->vault->vaultEnabled = true;

        $host = ($this->handler)($this->snmpCommand($poller->id(), null));

        self::assertNull($host->snmpCommunity);
        self::assertSame(0, $this->vault->isEnabledCalls);
    }

    /**
     * With a vault the column receives the `secret::` reference, so the plaintext is never
     * measured against the column width — as in legacy, which substitutes before asserting.
     */
    public function testItVaultsACommunityTooLongForTheColumn(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $this->vault->vaultEnabled = true;
        $this->vault->writtenPaths[CreateHostCommandHandler::HOST_SNMP_COMMUNITY_KEY] = 'secret::vault::monitoring/hosts/abc::_HOSTSNMPCOMMUNITY';

        $host = ($this->handler)($this->snmpCommand($poller->id(), str_repeat('a', SnmpCommunity::MAX_LENGTH + 1)));

        self::assertSame('secret::vault::monitoring/hosts/abc::_HOSTSNMPCOMMUNITY', $host->snmpCommunity?->value);
    }

    public function testItRefusesACommunityTooLongForTheColumnWithoutAVault(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $this->vault->vaultEnabled = false;

        $this->expectException(\InvalidArgumentException::class);

        ($this->handler)($this->snmpCommand($poller->id(), str_repeat('a', SnmpCommunity::MAX_LENGTH + 1)));
    }

    /**
     * The vault write happens before the host is persisted, so a failure must leave no host at all
     * rather than one whose SNMP community was silently dropped.
     */
    public function testAVaultFailureLeavesNoHostBehind(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $this->vault->vaultEnabled = true;
        $this->vault->writeThrows = true;

        try {
            ($this->handler)($this->snmpCommand($poller->id(), 'public'));
            self::fail('The vault failure should have propagated.');
        } catch (\RuntimeException) {
        }

        self::assertFalse($this->hostRepository->isNameUsedByHostOrTemplate(new HostName('snmp-host')));
    }

    public function testItStoresTheAlias(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);

        $host = ($this->handler)(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
            alias: new HostAlias('Web server'),
        ));

        self::assertSame('Web server', $host->alias?->value);
    }

    public function testItRejectsAnUnknownCategory(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);

        $this->expectException(HostCategoryNotFoundException::class);

        ($this->handler)($this->referenceCommand($poller->id(), categoryIds: [404]));
    }

    public function testItRejectsACategoryOutsideTheViewerScope(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $this->hostCategoryRepository->hostCategories[7] = new HostCategory(new HostCategoryId(7), new HostCategoryName('Production'));
        $this->resourceAccessRepository->accessibleHostCategoryIds = new Collection([], HostCategoryId::class);

        $this->expectException(HostCategoryNotFoundException::class);

        ($this->handler)($this->referenceCommand($poller->id(), categoryIds: [7], viewerId: new UserId(9)));
    }

    public function testItRejectsAnUnknownSeverity(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);

        $this->expectException(HostSeverityNotFoundException::class);

        ($this->handler)($this->referenceCommand($poller->id(), severityId: 404));
    }

    public function testItRejectsASeverityOutsideTheViewerScope(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $this->hostSeverityRepository->hostSeverities[7] = new HostSeverityName('Critical');
        $this->resourceAccessRepository->accessibleHostSeverityIds = new Collection([], HostSeverityId::class);

        $this->expectException(HostSeverityNotFoundException::class);

        ($this->handler)($this->referenceCommand($poller->id(), severityId: 7, viewerId: new UserId(9)));
    }

    public function testItRejectsAnUnknownTimezone(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);

        $this->expectException(TimezoneNotFoundException::class);

        ($this->handler)($this->referenceCommand($poller->id(), timezoneId: 404));
    }

    public function testItKeepsTheAcceptedReferencesOnTheAggregate(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $this->hostCategoryRepository->hostCategories[7] = new HostCategory(new HostCategoryId(7), new HostCategoryName('Production'));
        $this->hostSeverityRepository->hostSeverities[8] = new HostSeverityName('Critical');
        $this->timezoneRepository->timezones[9] = new Timezone(new TimezoneId(9), new TimezoneName('Europe/Paris'));

        $host = ($this->handler)($this->referenceCommand($poller->id(), categoryIds: [7], severityId: 8, timezoneId: 9));

        self::assertSame([7], array_map(static fn (HostCategoryId $id): int => $id->value, $host->categoryIds->toArray()));
        self::assertSame(8, $host->severityId?->value);
        self::assertSame(9, $host->timezoneId?->value);
    }

    public function testItRejectsAnUnknownTemplate(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);

        $this->expectException(HostTemplateNotFoundException::class);

        ($this->handler)($this->relationCommand($poller->id(), templateIds: [404]));
    }

    public function testItRejectsAnUnknownParentHost(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);

        $this->expectException(HostNotFoundException::class);

        ($this->handler)($this->relationCommand($poller->id(), parentHostIds: [404]));
    }

    public function testItRejectsAnUnknownChildHost(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);

        $this->expectException(HostNotFoundException::class);

        ($this->handler)($this->relationCommand($poller->id(), childHostIds: [404]));
    }

    public function testItRejectsAChildThatIsAnAncestorOfAParent(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $ancestorId = $this->addRelatedHost($poller->id(), 'core-router');
        $parentId = $this->addRelatedHost($poller->id(), 'edge-router', parentHostIds: [$ancestorId]);

        $this->expectException(CircularHostRelationException::class);

        ($this->handler)($this->relationCommand($poller->id(), parentHostIds: [$parentId], childHostIds: [$ancestorId]));
    }

    /**
     * The edge is declared from the child side, so `edge-router` names no parent of its own while
     * `host_hostparent_relation` still records the link.
     */
    public function testItRejectsACycleClosedThroughAnEdgeCreatedFromTheChildSide(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $descendantId = $this->addRelatedHost($poller->id(), 'edge-router');
        $ancestorId = $this->addRelatedHost($poller->id(), 'core-router', childHostIds: [$descendantId]);

        $this->expectException(CircularHostRelationException::class);

        ($this->handler)($this->relationCommand($poller->id(), parentHostIds: [$descendantId], childHostIds: [$ancestorId]));
    }

    public function testItKeepsTheTemplateOrderOnTheAggregate(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $this->hostTemplateRepository->hostTemplates[7] = new HostTemplate(new HostTemplateId(7), new HostTemplateName('generic-host'));
        $this->hostTemplateRepository->hostTemplates[8] = new HostTemplate(new HostTemplateId(8), new HostTemplateName('linux-host'));

        $host = ($this->handler)($this->relationCommand($poller->id(), templateIds: [8, 7]));

        self::assertSame([8, 7], array_map(static fn (HostTemplateId $id): int => $id->value, $host->templateIds->toArray()));
    }

    public function testItRequestsServiceDeploymentForAHostWithTemplates(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $this->hostTemplateRepository->hostTemplates[3] = new HostTemplate(new HostTemplateId(3), new HostTemplateName('generic-active-host'));

        ($this->handler)($this->relationCommand($poller->id(), templateIds: [3]));

        self::assertTrue($this->eventBus->shouldHaveDispatched(HostServicesDeploymentRequested::class));
    }

    public function testItDoesNotRequestServiceDeploymentWhenTheToggleIsOff(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $this->hostTemplateRepository->hostTemplates[3] = new HostTemplate(new HostTemplateId(3), new HostTemplateName('generic-active-host'));

        ($this->handler)($this->relationCommand($poller->id(), templateIds: [3], deployServicesFromTemplates: false));

        self::assertFalse($this->eventBus->shouldHaveDispatched(HostServicesDeploymentRequested::class));
    }

    /**
     * Legacy resolves what to deploy by walking host_template_relation, so without templates there
     * is nothing to do — and reaching it would boot a second Symfony kernel for nothing.
     */
    public function testItDoesNotRequestServiceDeploymentWithoutTemplates(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);

        ($this->handler)($this->relationCommand($poller->id()));

        self::assertFalse($this->eventBus->shouldHaveDispatched(HostServicesDeploymentRequested::class));
    }

    private function snmpCommand(PollerId $pollerId, ?string $snmpCommunity): CreateHostCommand
    {
        return new CreateHostCommand(
            name: new HostName('snmp-host'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $pollerId,
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
            snmpVersion: SnmpVersionEnum::TwoC,
            snmpCommunity: $snmpCommunity,
        );
    }

    /**
     * @param list<int> $categoryIds
     */
    private function referenceCommand(
        PollerId $pollerId,
        array $categoryIds = [],
        ?int $severityId = null,
        ?int $timezoneId = null,
        ?UserId $viewerId = null,
    ): CreateHostCommand {
        return new CreateHostCommand(
            name: new HostName('reference-host'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $pollerId,
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
            viewerId: $viewerId,
            categoryIds: new Collection(
                array_map(static fn (int $id): HostCategoryId => new HostCategoryId($id), $categoryIds),
                HostCategoryId::class,
            ),
            timezoneId: $timezoneId !== null ? new TimezoneId($timezoneId) : null,
            severityId: $severityId !== null ? new HostSeverityId($severityId) : null,
        );
    }

    /**
     * @param list<int> $templateIds
     * @param list<int> $parentHostIds
     * @param list<int> $childHostIds
     */
    private function relationCommand(
        PollerId $pollerId,
        array $templateIds = [],
        array $parentHostIds = [],
        array $childHostIds = [],
        bool $deployServicesFromTemplates = true,
    ): CreateHostCommand {
        return new CreateHostCommand(
            name: new HostName('relation-host'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $pollerId,
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
            templateIds: new Collection(
                array_map(static fn (int $id): HostTemplateId => new HostTemplateId($id), $templateIds),
                HostTemplateId::class,
            ),
            parentHostIds: new Collection(
                array_map(static fn (int $id): HostId => new HostId($id), $parentHostIds),
                HostId::class,
            ),
            childHostIds: new Collection(
                array_map(static fn (int $id): HostId => new HostId($id), $childHostIds),
                HostId::class,
            ),
            deployServicesFromTemplates: $deployServicesFromTemplates,
        );
    }

    /**
     * @param list<int> $parentHostIds
     * @param list<int> $childHostIds
     */
    private function addRelatedHost(PollerId $pollerId, string $name, array $parentHostIds = [], array $childHostIds = []): int
    {
        $host = new Host(
            id: null,
            name: new HostName($name),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: $pollerId,
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            parentHostIds: new Collection(
                array_map(static fn (int $id): HostId => new HostId($id), $parentHostIds),
                HostId::class,
            ),
            childHostIds: new Collection(
                array_map(static fn (int $id): HostId => new HostId($id), $childHostIds),
                HostId::class,
            ),
        );
        $this->hostRepository->add($host);

        return $host->id()->value;
    }

    private function addPoller(FakePollerRepository $repository, int $id): Poller
    {
        $poller = new Poller(
            id: null,
            name: new PollerName('Central'),
            address: new PollerAddress('127.0.0.1'),
            isCentral: true,
            isDefault: true,
            isActivated: true,
            pollerType: PollerTypeEnum::VM,
            uid: new PollerUid(123456789012345),
            globalMacros: new Collection([], GlobalMacro::class),
            gorgoneConfiguration: new GorgoneConfiguration(),
            engineInformation: new EngineInformation(),
            brokerInformation: new BrokerInformation(),
            connectorConfiguration: new ConnectorConfiguration(),
            trapConfiguration: new TrapConfiguration(),
            pollerCommands: new Collection([], PollerCommand::class),
        );

        $reflection = new \ReflectionProperty(AggregateRoot::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($poller, new PollerId($id));

        $repository->pollers[$id] = $poller;

        return $poller;
    }
}

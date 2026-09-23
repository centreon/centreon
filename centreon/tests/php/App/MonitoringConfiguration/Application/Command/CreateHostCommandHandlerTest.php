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
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
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
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
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
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeVault;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Shared\Double\EventBusSpy;

final class CreateHostCommandHandlerTest extends KernelTestCase
{
    private CreateHostCommandHandler $handler;

    private FakeHostRepository $hostRepository;

    private FakePollerRepository $pollerRepository;

    private FakeHostGroupRepository $hostGroupRepository;

    private FakeHostTemplateRepository $hostTemplateRepository;

    private FakeHostCategoryRepository $hostCategoryRepository;

    private FakeHostSeverityRepository $hostSeverityRepository;

    private FakeTimezoneRepository $timezoneRepository;

    private FakeVault $vault;

    private FakeResourceAccessRepository $resourceAccessRepository;

    private EventBusSpy $eventBus;

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
        $this->hostTemplateRepository = new FakeHostTemplateRepository();
        $this->hostCategoryRepository = new FakeHostCategoryRepository();
        $this->hostSeverityRepository = new FakeHostSeverityRepository();
        $this->timezoneRepository = new FakeTimezoneRepository();
        $this->resourceAccessRepository = new FakeResourceAccessRepository();
        $this->vault = new FakeVault();
        $this->vault->vaultEnabled = false;

        $this->eventBus = new EventBusSpy();

        $container->set(HostRepository::class, $this->hostRepository);
        $container->set(PollerRepository::class, $this->pollerRepository);
        $container->set(HostGroupRepository::class, $this->hostGroupRepository);
        $container->set(HostTemplateRepository::class, $this->hostTemplateRepository);
        $container->set(HostCategoryRepository::class, $this->hostCategoryRepository);
        $container->set(HostSeverityRepository::class, $this->hostSeverityRepository);
        $container->set(TimezoneRepository::class, $this->timezoneRepository);
        $container->set(ResourceAccessRepository::class, $this->resourceAccessRepository);
        $container->set(VaultInterface::class, $this->vault);
        $container->set(EventBus::class, $this->eventBus);

        /** @var CreateHostCommandHandler $handler */
        $handler = $container->get(CreateHostCommandHandler::class);
        $this->handler = $handler;
    }

    public function testItCreatesTheHost(): void
    {
        $poller = $this->addPoller();

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
        $poller = $this->addPoller();

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
        $poller = $this->addPoller();

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
        $poller = $this->addPoller();
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
        $poller = $this->addPoller();

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
        $poller = $this->addPoller();
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
        $poller = $this->addPoller();
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
        $poller = $this->addPoller();
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
        $poller = $this->addPoller();
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

    public function testItRejectsAnUnknownTemplate(): void
    {
        $poller = $this->addPoller();

        $this->expectException(HostTemplateNotFoundException::class);

        ($this->handler)($this->command($poller->id(), templateIds: new Collection([new HostTemplateId(404)], HostTemplateId::class)));
    }

    public function testItKeepsTheRequestedTemplateOrder(): void
    {
        $poller = $this->addPoller();
        $this->hostTemplateRepository->hostTemplates[3] = new HostTemplate(new HostTemplateId(3), new HostTemplateName('generic-active-host'));
        $this->hostTemplateRepository->hostTemplates[7] = new HostTemplate(new HostTemplateId(7), new HostTemplateName('generic-passive-host'));

        $host = ($this->handler)($this->command(
            $poller->id(),
            templateIds: new Collection([new HostTemplateId(7), new HostTemplateId(3)], HostTemplateId::class),
        ));

        self::assertSame([7, 3], array_map(static fn (HostTemplateId $id): int => $id->value, $host->templateIds->toArray()));
    }

    public function testItRejectsAnUnknownCategory(): void
    {
        $poller = $this->addPoller();

        $this->expectException(HostCategoryNotFoundException::class);

        ($this->handler)($this->command($poller->id(), categoryIds: new Collection([new HostCategoryId(404)], HostCategoryId::class)));
    }

    public function testARestrictedViewerCannotReferenceACategoryOutsideTheirAccessibleScope(): void
    {
        $poller = $this->addPoller();
        $this->hostCategoryRepository->hostCategories[5] = new HostCategory(new HostCategoryId(5), new HostCategoryName('Production'));
        $this->resourceAccessRepository->accessibleHostCategoryIds = new Collection([new HostCategoryId(9)], HostCategoryId::class);

        $this->expectException(HostCategoryNotFoundException::class);

        ($this->handler)($this->command(
            $poller->id(),
            categoryIds: new Collection([new HostCategoryId(5)], HostCategoryId::class),
            viewerId: new UserId(7),
        ));
    }

    public function testItRejectsAnUnknownSeverity(): void
    {
        $poller = $this->addPoller();

        $this->expectException(HostSeverityNotFoundException::class);

        ($this->handler)($this->command($poller->id(), severityId: new HostSeverityId(404)));
    }

    public function testARestrictedViewerCannotReferenceASeverityOutsideTheirAccessibleScope(): void
    {
        $poller = $this->addPoller();
        $this->hostSeverityRepository->hostSeverities[5] = new HostSeverityName('Critical');
        $this->resourceAccessRepository->accessibleHostSeverityIds = new Collection([new HostSeverityId(9)], HostSeverityId::class);

        $this->expectException(HostSeverityNotFoundException::class);

        ($this->handler)($this->command($poller->id(), severityId: new HostSeverityId(5), viewerId: new UserId(7)));
    }

    public function testItRejectsAnUnknownTimezone(): void
    {
        $poller = $this->addPoller();

        $this->expectException(TimezoneNotFoundException::class);

        ($this->handler)($this->command($poller->id(), timezoneId: new TimezoneId(404)));
    }

    public function testItRejectsAnUnknownParentHost(): void
    {
        $poller = $this->addPoller();

        $this->expectException(HostNotFoundException::class);

        ($this->handler)($this->command($poller->id(), parentHostIds: new Collection([new HostId(404)], HostId::class)));
    }

    public function testItRejectsAnUnknownChildHost(): void
    {
        $poller = $this->addPoller();

        $this->expectException(HostNotFoundException::class);

        ($this->handler)($this->command($poller->id(), childHostIds: new Collection([new HostId(404)], HostId::class)));
    }

    public function testItRejectsAHostNamedAsBothParentAndChild(): void
    {
        $poller = $this->addPoller();
        $otherId = $this->addHost($poller->id(), 'router-01');

        $this->expectException(CircularHostRelationException::class);

        ($this->handler)($this->command(
            $poller->id(),
            parentHostIds: new Collection([new HostId($otherId)], HostId::class),
            childHostIds: new Collection([new HostId($otherId)], HostId::class),
        ));
    }

    public function testItRejectsACycleClosedThroughAnEdgeCreatedFromTheChildSide(): void
    {
        $poller = $this->addPoller();
        $descendantId = $this->addHost($poller->id(), 'edge-router');
        // Declared from the child side, so the edge exists even though `edge-router` itself
        // names no parent, exactly as host_hostparent_relation records it.
        $ancestorId = $this->addHost($poller->id(), 'core-router', childHostIds: [$descendantId]);

        $this->expectException(CircularHostRelationException::class);

        ($this->handler)($this->command(
            $poller->id(),
            parentHostIds: new Collection([new HostId($descendantId)], HostId::class),
            childHostIds: new Collection([new HostId($ancestorId)], HostId::class),
        ));
    }

    public function testItRejectsAChildThatIsAnAncestorOfAParent(): void
    {
        $poller = $this->addPoller();
        $ancestorId = $this->addHost($poller->id(), 'core-router');
        $parentId = $this->addHost($poller->id(), 'edge-router', parentHostIds: [$ancestorId]);

        $this->expectException(CircularHostRelationException::class);

        ($this->handler)($this->command(
            $poller->id(),
            parentHostIds: new Collection([new HostId($parentId)], HostId::class),
            childHostIds: new Collection([new HostId($ancestorId)], HostId::class),
        ));
    }

    public function testItAcceptsUnrelatedParentAndChildHosts(): void
    {
        $poller = $this->addPoller();
        $parentId = $this->addHost($poller->id(), 'router-01');
        $childId = $this->addHost($poller->id(), 'vm-03');

        $host = ($this->handler)($this->command(
            $poller->id(),
            parentHostIds: new Collection([new HostId($parentId)], HostId::class),
            childHostIds: new Collection([new HostId($childId)], HostId::class),
        ));

        self::assertSame([$parentId], array_map(static fn (HostId $id): int => $id->value, $host->parentHostIds->toArray()));
        self::assertSame([$childId], array_map(static fn (HostId $id): int => $id->value, $host->childHostIds->toArray()));
    }

    public function testItKeepsTheSnmpCommunityInPlaintextWhenNoVaultIsConfigured(): void
    {
        $poller = $this->addPoller();
        $this->vault->vaultEnabled = false;

        $host = ($this->handler)($this->command($poller->id(), snmpCommunity: 'public'));

        self::assertSame('public', $host->snmpCommunity?->value);
        self::assertSame([], $this->vault->writeCalls);
    }

    public function testItStoresTheSnmpCommunityInTheVaultWhenOneIsConfigured(): void
    {
        $poller = $this->addPoller();
        $this->vault->vaultEnabled = true;
        $this->vault->writtenPaths[VaultInterface::HOST_SNMP_COMMUNITY_KEY] = 'secret::vault::monitoring/hosts/abc::_HOSTSNMPCOMMUNITY';

        $host = ($this->handler)($this->command($poller->id(), snmpCommunity: 'public'));

        self::assertSame('secret::vault::monitoring/hosts/abc::_HOSTSNMPCOMMUNITY', $host->snmpCommunity?->value);
        self::assertSame([[
            'customPath' => VaultInterface::HOST_VAULT_PATH,
            'key' => VaultInterface::HOST_SNMP_COMMUNITY_KEY,
            'value' => 'public',
            'uuid' => null,
        ]], $this->vault->writeCalls);
    }

    public function testItVaultsACommunityTooLongForTheColumn(): void
    {
        $poller = $this->addPoller();
        $this->vault->vaultEnabled = true;
        $this->vault->writtenPaths[VaultInterface::HOST_SNMP_COMMUNITY_KEY] = 'secret::vault::monitoring/hosts/abc::_HOSTSNMPCOMMUNITY';
        $plaintext = str_repeat('a', SnmpCommunity::MAX_LENGTH + 1);

        $host = ($this->handler)($this->command($poller->id(), snmpCommunity: $plaintext));

        self::assertSame('secret::vault::monitoring/hosts/abc::_HOSTSNMPCOMMUNITY', $host->snmpCommunity?->value);
        self::assertSame($plaintext, $this->vault->writeCalls[0]['value']);
    }

    public function testItRefusesACommunityTooLongForTheColumnWithoutAVault(): void
    {
        $poller = $this->addPoller();
        $this->vault->vaultEnabled = false;

        $this->expectException(\InvalidArgumentException::class);

        ($this->handler)($this->command($poller->id(), snmpCommunity: str_repeat('a', SnmpCommunity::MAX_LENGTH + 1)));
    }

    public function testItDoesNotTouchTheVaultWhenNoSnmpCommunityIsGiven(): void
    {
        $poller = $this->addPoller();
        $this->vault->vaultEnabled = true;

        $host = ($this->handler)($this->command($poller->id()));

        self::assertNull($host->snmpCommunity);
        self::assertSame([], $this->vault->writeCalls);
    }

    public function testAVaultFailureLeavesNoHostBehind(): void
    {
        $poller = $this->addPoller();
        $this->vault->vaultEnabled = true;
        $this->vault->writeThrows = true;

        $name = new HostName('server-vault-failure');

        try {
            ($this->handler)($this->command($poller->id(), snmpCommunity: 'public', name: $name));
            self::fail('The vault failure should have propagated.');
        } catch (\RuntimeException) {
        }

        self::assertFalse($this->hostRepository->isNameUsedByHostOrTemplate($name));
        self::assertFalse($this->eventBus->shouldHaveDispatched(HostCreated::class));
    }

    public function testItRequestsServiceDeploymentWhenTheHostHasTemplates(): void
    {
        $poller = $this->addPoller();
        $this->hostTemplateRepository->hostTemplates[3] = new HostTemplate(new HostTemplateId(3), new HostTemplateName('generic-active-host'));

        ($this->handler)($this->command($poller->id(), templateIds: new Collection([new HostTemplateId(3)], HostTemplateId::class)));

        self::assertTrue($this->eventBus->shouldHaveDispatched(HostServicesDeploymentRequested::class));
    }

    public function testItDoesNotRequestServiceDeploymentWhenTheToggleIsOff(): void
    {
        $poller = $this->addPoller();
        $this->hostTemplateRepository->hostTemplates[3] = new HostTemplate(new HostTemplateId(3), new HostTemplateName('generic-active-host'));

        ($this->handler)($this->command(
            $poller->id(),
            templateIds: new Collection([new HostTemplateId(3)], HostTemplateId::class),
            deployServicesFromTemplates: false,
        ));

        self::assertFalse($this->eventBus->shouldHaveDispatched(HostServicesDeploymentRequested::class));
    }

    public function testItDoesNotRequestServiceDeploymentWithoutTemplates(): void
    {
        $poller = $this->addPoller();

        ($this->handler)($this->command($poller->id()));

        self::assertFalse($this->eventBus->shouldHaveDispatched(HostServicesDeploymentRequested::class));
    }

    /**
     * @param Collection<HostTemplateId>|null $templateIds
     * @param Collection<HostCategoryId>|null $categoryIds
     * @param Collection<HostId>|null $parentHostIds
     * @param Collection<HostId>|null $childHostIds
     */
    private function command(
        PollerId $pollerId,
        ?Collection $templateIds = null,
        ?Collection $categoryIds = null,
        ?Collection $parentHostIds = null,
        ?Collection $childHostIds = null,
        ?HostSeverityId $severityId = null,
        ?TimezoneId $timezoneId = null,
        ?string $snmpCommunity = null,
        ?UserId $viewerId = null,
        bool $deployServicesFromTemplates = true,
        ?HostName $name = null,
    ): CreateHostCommand {
        return new CreateHostCommand(
            name: $name ?? new HostName('server-' . bin2hex(random_bytes(4))),
            address: new HostAddress('127.0.0.1'),
            pollerId: $pollerId,
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
            viewerId: $viewerId,
            templateIds: $templateIds ?? new Collection([], HostTemplateId::class),
            categoryIds: $categoryIds ?? new Collection([], HostCategoryId::class),
            parentHostIds: $parentHostIds ?? new Collection([], HostId::class),
            childHostIds: $childHostIds ?? new Collection([], HostId::class),
            snmpCommunity: $snmpCommunity,
            timezoneId: $timezoneId,
            severityId: $severityId,
            deployServicesFromTemplates: $deployServicesFromTemplates,
        );
    }

    /**
     * @param list<int> $parentHostIds
     * @param list<int> $childHostIds
     */
    private function addHost(PollerId $pollerId, string $name, array $parentHostIds = [], array $childHostIds = []): int
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

    private function addPoller(): Poller
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
        $reflection->setValue($poller, new PollerId(1));

        $this->pollerRepository->pollers[1] = $poller;

        return $poller;
    }
}

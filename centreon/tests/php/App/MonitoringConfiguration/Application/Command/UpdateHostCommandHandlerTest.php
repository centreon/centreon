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

use App\MonitoringConfiguration\Application\Command\UpdateHostCommand;
use App\MonitoringConfiguration\Application\Command\UpdateHostCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
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
use App\MonitoringConfiguration\Domain\Event\HostServicesDeploymentRequested;
use App\MonitoringConfiguration\Domain\Event\HostUpdated;
use App\MonitoringConfiguration\Domain\Event\HostVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\HostAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\InheritedHostMacroRepository;
use App\MonitoringConfiguration\Domain\Repository\OptionRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\VaultInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostCategoryRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostSeverityRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeInheritedHostMacroRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeOptionRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakePollerRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimezoneRepository;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Shared\Double\EventBusSpy;
use Tests\App\Shared\Double\FakeVault;

final class UpdateHostCommandHandlerTest extends KernelTestCase
{
    private UpdateHostCommandHandler $handler;

    private FakeHostRepository $hostRepository;

    private FakePollerRepository $pollerRepository;

    private FakeInheritedHostMacroRepository $inheritedHostMacroRepository;

    private FakeVault $vault;

    private EventBusSpy $eventBus;

    private FakeHostTemplateRepository $hostTemplateRepository;

    protected function setUp(): void
    {
        $container = self::getContainer();

        $this->hostRepository = new FakeHostRepository();
        $this->pollerRepository = new FakePollerRepository();
        $this->inheritedHostMacroRepository = new FakeInheritedHostMacroRepository();
        $this->vault = new FakeVault();
        $this->vault->vaultEnabled = false;

        $this->eventBus = new EventBusSpy();
        $this->hostTemplateRepository = new FakeHostTemplateRepository();

        $container->set(HostRepository::class, $this->hostRepository);
        $container->set(PollerRepository::class, $this->pollerRepository);
        $container->set(HostGroupRepository::class, new FakeHostGroupRepository());
        $container->set(CommandRepository::class, new FakeCommandRepository());
        $container->set(InheritedHostMacroRepository::class, $this->inheritedHostMacroRepository);
        $container->set(ResourceAccessRepository::class, new FakeResourceAccessRepository());
        $container->set(OptionRepository::class, new FakeOptionRepository());
        $container->set(VaultInterface::class, $this->vault);
        $container->set(VaultCredentialWriter::class, new VaultCredentialWriter($this->vault));
        $container->set(EventBus::class, $this->eventBus);
        $container->set(HostCategoryRepository::class, new FakeHostCategoryRepository());
        $container->set(HostSeverityRepository::class, new FakeHostSeverityRepository());
        $container->set(TimezoneRepository::class, new FakeTimezoneRepository());
        $container->set(HostTemplateRepository::class, $this->hostTemplateRepository);

        /** @var UpdateHostCommandHandler $handler */
        $handler = $container->get(UpdateHostCommandHandler::class);
        $this->handler = $handler;
    }

    public function testItUpdatesTheHost(): void
    {
        $this->addPoller(1);
        $this->seedHost(10, name: 'server-old');

        $host = ($this->handler)($this->command(10, name: 'server-new'));

        self::assertSame(10, $host->id()->value);
        self::assertSame('server-new', $host->name->value);
        self::assertSame('server-new', $this->hostRepository->findOne(new HostId(10))?->name->value);
    }

    public function testItRejectsAnUnknownHost(): void
    {
        $this->addPoller(1);

        $this->expectException(HostNotFoundException::class);

        ($this->handler)($this->command(404));
    }

    public function testItRejectsADuplicateNameFromAnotherHost(): void
    {
        $this->addPoller(1);
        $this->seedHost(10, name: 'server-a');
        $this->seedHost(20, name: 'server-b');

        $this->expectException(HostAlreadyExistsException::class);

        ($this->handler)($this->command(10, name: 'server-b'));
    }

    public function testItAllowsTheHostToKeepItsOwnName(): void
    {
        $this->addPoller(1);
        $this->seedHost(10, name: 'server-a');

        $host = ($this->handler)($this->command(10, name: 'server-a'));

        self::assertSame('server-a', $host->name->value);
    }

    public function testItChangesTheActivationState(): void
    {
        $this->addPoller(1);
        $this->seedHost(10, activated: true);

        $host = ($this->handler)($this->command(10, activated: false));

        self::assertFalse($host->activated);
    }

    public function testItDispatchesHostUpdated(): void
    {
        $this->addPoller(1);
        $this->seedHost(10);

        ($this->handler)($this->command(10));

        self::assertTrue($this->eventBus->shouldHaveDispatched(HostUpdated::class));
    }

    public function testItCarriesThePreviousPollerWhenThePollerChanged(): void
    {
        $this->addPoller(1);
        $this->addPoller(2);
        $this->seedHost(10, pollerId: 1);

        ($this->handler)($this->command(10, pollerId: 2));

        /** @var list<HostUpdated> $events */
        $events = $this->eventBus->getDispatchedEvents(HostUpdated::class);
        self::assertSame(1, $events[0]->previousPollerId?->value);
    }

    public function testItLeavesThePreviousPollerNullWhenUnchanged(): void
    {
        $this->addPoller(1);
        $this->seedHost(10, pollerId: 1);

        ($this->handler)($this->command(10, pollerId: 1));

        /** @var list<HostUpdated> $events */
        $events = $this->eventBus->getDispatchedEvents(HostUpdated::class);
        self::assertNull($events[0]->previousPollerId);
    }

    public function testItReusesTheExistingVaultUuidForTheSnmpCommunity(): void
    {
        $this->addPoller(1);
        $this->vault->vaultEnabled = true;
        $existingReference = 'secret::vault::monitoring/hosts/existing-uuid::_HOSTSNMPCOMMUNITY';
        $this->vault->extractedUuids[$existingReference] = 'existing-uuid';
        $this->seedHost(10, snmpCommunity: new SnmpCommunity($existingReference));

        ($this->handler)($this->command(10, snmpCommunity: 'new-community'));

        self::assertNotEmpty($this->vault->writeManyCalls);
        self::assertSame('existing-uuid', $this->vault->writeManyCalls[0]['uuid']);
    }

    public function testItRequestsADeferredVaultPurgeWhenAllSecretsAreRemoved(): void
    {
        $this->addPoller(1);
        $this->vault->vaultEnabled = true;
        $existingReference = 'secret::vault::monitoring/hosts/existing-uuid::_HOSTSNMPCOMMUNITY';
        $this->vault->extractedUuids[$existingReference] = 'existing-uuid';
        $this->seedHost(10, snmpCommunity: new SnmpCommunity($existingReference));

        // No SNMP community and no password macro in the new payload.
        ($this->handler)($this->command(10, snmpCommunity: null));

        // The purge is deferred (post-commit) via the event carrying the pre-update host, never done
        // inline, so a rollback cannot destroy a live secret.
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostVaultPurgeRequested::class));
        self::assertEmpty($this->vault->deleteCalls);
        /** @var list<HostVaultPurgeRequested> $events */
        $events = $this->eventBus->getDispatchedEvents(HostVaultPurgeRequested::class);
        self::assertSame('existing-uuid', $events[0]->host->getVaultUuid($this->vault));
    }

    public function testItDoesNotRequestAVaultPurgeWhenASecretSurvives(): void
    {
        $this->addPoller(1);
        $this->vault->vaultEnabled = true;
        $existingReference = 'secret::vault::monitoring/hosts/existing-uuid::_HOSTSNMPCOMMUNITY';
        $this->vault->extractedUuids[$existingReference] = 'existing-uuid';
        $this->seedHost(10, snmpCommunity: new SnmpCommunity($existingReference));

        // A new SNMP community keeps a secret on the host, so the entry must not be purged.
        ($this->handler)($this->command(10, snmpCommunity: 'still-secret'));

        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostVaultPurgeRequested::class));
    }

    public function testItDoesNotRequestAVaultPurgeWhenTheHostNeverHadASecret(): void
    {
        $this->addPoller(1);
        $this->vault->vaultEnabled = true;
        $this->seedHost(10, snmpCommunity: null);

        ($this->handler)($this->command(10, snmpCommunity: null));

        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostVaultPurgeRequested::class));
    }

    public function testItDeploysServicesWhenTemplatesArePresent(): void
    {
        $this->addPoller(1);
        $this->hostTemplateRepository->hostTemplates[7] = new HostTemplate(new HostTemplateId(7), new HostTemplateName('generic-host'));
        $this->seedHost(10);

        ($this->handler)($this->command(10, templateIds: new Collection([new HostTemplateId(7)], HostTemplateId::class)));

        self::assertTrue($this->eventBus->shouldHaveDispatched(HostServicesDeploymentRequested::class));
    }

    /**
     * @param ?Collection<HostTemplateId> $templateIds
     */
    private function command(
        int $id,
        int $pollerId = 1,
        string $name = 'server-01',
        bool $activated = true,
        ?string $snmpCommunity = null,
        ?Collection $templateIds = null,
        CheckOptions $checkOptions = new CheckOptions(null),
    ): UpdateHostCommand {
        return new UpdateHostCommand(
            id: new HostId($id),
            name: new HostName($name),
            address: new HostAddress('127.0.0.1'),
            pollerId: new PollerId($pollerId),
            activated: $activated,
            hostGroupIds: new Collection([], HostGroupId::class),
            updatedBy: 1,
            templateIds: $templateIds ?? new Collection([], HostTemplateId::class),
            snmpCommunity: $snmpCommunity,
            checkOptions: $checkOptions,
        );
    }

    private function seedHost(
        int $id,
        int $pollerId = 1,
        string $name = 'server-01',
        bool $activated = true,
        ?SnmpCommunity $snmpCommunity = null,
    ): Host {
        $host = new Host(
            id: null,
            name: new HostName($name),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: $activated,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            snmpCommunity: $snmpCommunity,
        );

        return $this->hostRepository->seed($host, $id);
    }

    private function addPoller(int $id): void
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

        $this->pollerRepository->pollers[$id] = $poller;
    }
}

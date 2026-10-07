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
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroup;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupName;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
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
use App\MonitoringConfiguration\Domain\Event\HostDisabled;
use App\MonitoringConfiguration\Domain\Event\HostEnabled;
use App\MonitoringConfiguration\Domain\Event\HostServicesDeploymentRequested;
use App\MonitoringConfiguration\Domain\Event\HostUpdated;
use App\MonitoringConfiguration\Domain\Event\HostVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\HostAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostMacroNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostMacroValueRequiredException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\OptionRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\TriStateEnum;
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

    private FakeVault $vault;

    private FakeCommandRepository $commandRepository;

    private EventBusSpy $eventBus;

    private FakeHostTemplateRepository $hostTemplateRepository;

    private FakeHostGroupRepository $hostGroupRepository;

    private FakeHostSeverityRepository $hostSeverityRepository;

    private FakeResourceAccessRepository $resourceAccessRepository;

    protected function setUp(): void
    {
        $container = self::getContainer();

        $this->hostRepository = new FakeHostRepository();
        $this->pollerRepository = new FakePollerRepository();
        $this->commandRepository = new FakeCommandRepository();
        $this->vault = new FakeVault();
        $this->vault->vaultEnabled = false;

        $this->eventBus = new EventBusSpy();
        $this->hostTemplateRepository = new FakeHostTemplateRepository();

        $this->hostGroupRepository = new FakeHostGroupRepository();
        $this->hostSeverityRepository = new FakeHostSeverityRepository();
        $this->resourceAccessRepository = new FakeResourceAccessRepository();

        $container->set(HostRepository::class, $this->hostRepository);
        $container->set(PollerRepository::class, $this->pollerRepository);
        $container->set(HostGroupRepository::class, $this->hostGroupRepository);
        $container->set(CommandRepository::class, $this->commandRepository);
        $container->set(ResourceAccessRepository::class, $this->resourceAccessRepository);
        $container->set(OptionRepository::class, new FakeOptionRepository());
        $container->set(VaultInterface::class, $this->vault);
        $container->set(VaultCredentialWriter::class, new VaultCredentialWriter($this->vault));
        $container->set(EventBus::class, $this->eventBus);
        $container->set(HostCategoryRepository::class, new FakeHostCategoryRepository());
        $container->set(HostSeverityRepository::class, $this->hostSeverityRepository);
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

    public function testItDispatchesHostUpdatedWhenALoggedFieldChanges(): void
    {
        $this->addPoller(1);
        $this->seedHost(10, name: 'server-old');

        ($this->handler)($this->command(10, name: 'server-new'));

        // A non-activation change: a single "change" line, no enable/disable.
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostUpdated::class));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostEnabled::class));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostDisabled::class));
    }

    public function testItDispatchesNothingOnANoOpUpdate(): void
    {
        $this->addPoller(1);
        $this->seedHost(10);

        // Same values as stored: ISO with legacy, which logs nothing and, here, triggers no side effect.
        ($this->handler)($this->command(10));

        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostUpdated::class));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostEnabled::class));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostDisabled::class));
    }

    public function testItDispatchesOnlyAnEnableWhenOnlyActivationIsTurnedOn(): void
    {
        $this->addPoller(1);
        $this->seedHost(10, activated: false);

        ($this->handler)($this->command(10, activated: true));

        self::assertTrue($this->eventBus->shouldHaveDispatched(HostEnabled::class));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostDisabled::class));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostUpdated::class));
    }

    public function testItDispatchesOnlyADisableWhenOnlyActivationIsTurnedOff(): void
    {
        $this->addPoller(1);
        $this->seedHost(10, activated: true);

        ($this->handler)($this->command(10, activated: false));

        self::assertTrue($this->eventBus->shouldHaveDispatched(HostDisabled::class));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostEnabled::class));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostUpdated::class));
    }

    public function testItDispatchesBothADisableAndAChangeWhenActivationAndAnotherFieldChange(): void
    {
        $this->addPoller(1);
        $this->seedHost(10, name: 'server-old', activated: true);

        ($this->handler)($this->command(10, name: 'server-new', activated: false));

        // Legacy writes two lines here (disable + change); mirror both events.
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostDisabled::class));
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostUpdated::class));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostEnabled::class));
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
        $this->seedHost(10, name: 'server-old', pollerId: 1);

        // Another field changes so a "change" line is produced, but the poller is the same.
        ($this->handler)($this->command(10, name: 'server-new', pollerId: 1));

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
        // The save succeeded: failing to tidy up the emptied entry must not fail the request.
        self::assertTrue($events[0]->bestEffort);
        self::assertSame(['_HOSTSNMPCOMMUNITY'], $this->vault->writeManyCalls[0]['deletes']);
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

    public function testItUpdatesADirectMacroInPlace(): void
    {
        // R5: a direct macro referred to by id keeps that id through a rename and a new value, so it
        // is updated rather than replaced.
        $this->addPoller(1);
        $this->seedHost(10, macros: [new HostMacro(new HostMacroName('old'), 'v1', isPassword: false, id: new HostMacroId(5))]);

        $host = ($this->handler)($this->command(10, macroChanges: [
            new HostMacroChange(new HostMacroName('new'), 'v2', isPassword: false, id: new HostMacroId(5)),
        ]));

        self::assertCount(1, $host->checkOptions->macros);
        self::assertSame(5, $host->checkOptions->macros[0]->id?->value);
        self::assertSame('NEW', $host->checkOptions->macros[0]->name->value);
        self::assertSame('v2', $host->checkOptions->macros[0]->value);
    }

    public function testItKeepsAStoredPasswordWhenItsValueIsNotResent(): void
    {
        // R1: a password is never echoed back, so a null value keeps the stored one.
        $this->addPoller(1);
        $this->seedHost(10, macros: [new HostMacro(new HostMacroName('pwd'), 'stored-secret', isPassword: true, id: new HostMacroId(5))]);

        $host = ($this->handler)($this->command(10, macroChanges: [
            new HostMacroChange(new HostMacroName('pwd'), null, isPassword: true, id: new HostMacroId(5)),
        ]));

        self::assertSame('stored-secret', $host->checkOptions->macros[0]->value);
        self::assertSame(5, $host->checkOptions->macros[0]->id?->value);
    }

    public function testItDropsADirectMacroNoLongerSubmitted(): void
    {
        $this->addPoller(1);
        $this->seedHost(10, macros: [
            new HostMacro(new HostMacroName('kept'), 'a', isPassword: false, id: new HostMacroId(5)),
            new HostMacro(new HostMacroName('dropped'), 'b', isPassword: false, id: new HostMacroId(6)),
        ]);

        $host = ($this->handler)($this->command(10, macroChanges: [
            new HostMacroChange(new HostMacroName('kept'), 'a', isPassword: false, id: new HostMacroId(5)),
        ]));

        self::assertCount(1, $host->checkOptions->macros);
        self::assertSame('KEPT', $host->checkOptions->macros[0]->name->value);
    }

    public function testItRejectsADirectMacroIdTheHostDoesNotOwn(): void
    {
        $this->addPoller(1);
        $this->seedHost(10, macros: [new HostMacro(new HostMacroName('own'), 'a', isPassword: false, id: new HostMacroId(5))]);

        $this->expectException(HostMacroNotFoundException::class);

        ($this->handler)($this->command(10, macroChanges: [
            new HostMacroChange(new HostMacroName('own'), 'a', isPassword: false, id: new HostMacroId(99)),
        ]));
    }

    public function testAChangedInheritedMacroBecomesANewDirectMacroLeavingTheTemplateUntouched(): void
    {
        // R4/R6: an inherited macro is addressed by its template id, but changing it never writes to
        // the template: the host gets its own macro, with no id until it is inserted.
        $this->addPoller(1);
        $templateMacro = new HostMacro(new HostMacroName('shared'), 'tpl-value', isPassword: false, id: new HostMacroId(70));
        $this->hostTemplateRepository->hostTemplates[7] = new HostTemplate(
            new HostTemplateId(7),
            new HostTemplateName('generic-host'),
            new Collection([$templateMacro], HostMacro::class),
        );
        $templateIds = new Collection([new HostTemplateId(7)], HostTemplateId::class);
        $this->seedHost(10, templateIds: $templateIds);

        $host = ($this->handler)($this->command(10, templateIds: $templateIds, macroChanges: [
            new HostMacroChange(new HostMacroName('shared'), 'host-value', isPassword: false, id: new HostMacroId(70), parent: HostMacroParentEnum::Template),
        ]));

        self::assertCount(1, $host->checkOptions->macros);
        $macro = $host->checkOptions->macros[0];
        self::assertTrue($macro->isDirect());
        self::assertNull($macro->id);
        self::assertSame('SHARED', $macro->name->value);
        self::assertSame('host-value', $macro->value);
        self::assertSame('tpl-value', $templateMacro->value);
    }

    public function testAnUntouchedInheritedMacroEchoedBackStaysInherited(): void
    {
        // R7: resending an inherited macro as read never materialises a copy on the host.
        $this->addPoller(1);
        $this->hostTemplateRepository->hostTemplates[7] = new HostTemplate(
            new HostTemplateId(7),
            new HostTemplateName('generic-host'),
            new Collection([new HostMacro(new HostMacroName('shared'), 'tpl-value', isPassword: false, id: new HostMacroId(70))], HostMacro::class),
        );
        $templateIds = new Collection([new HostTemplateId(7)], HostTemplateId::class);
        $this->seedHost(10, templateIds: $templateIds);

        $host = ($this->handler)($this->command(10, templateIds: $templateIds, macroChanges: [
            new HostMacroChange(new HostMacroName('shared'), 'tpl-value', isPassword: false, id: new HostMacroId(70), parent: HostMacroParentEnum::Template),
        ]));

        self::assertSame([], $host->checkOptions->macros);
    }

    public function testADirectOverrideSetBackToTheInheritedValueCollapsesToInherited(): void
    {
        // R7: an override equal to the macro it shadows is dropped, the host inherits again.
        $this->addPoller(1);
        $this->hostTemplateRepository->hostTemplates[7] = new HostTemplate(
            new HostTemplateId(7),
            new HostTemplateName('generic-host'),
            new Collection([new HostMacro(new HostMacroName('shared'), 'tpl-value', isPassword: false, id: new HostMacroId(70))], HostMacro::class),
        );
        $templateIds = new Collection([new HostTemplateId(7)], HostTemplateId::class);
        $this->seedHost(
            10,
            macros: [new HostMacro(new HostMacroName('shared'), 'override', isPassword: false, id: new HostMacroId(5))],
            templateIds: $templateIds,
        );

        $host = ($this->handler)($this->command(10, templateIds: $templateIds, macroChanges: [
            new HostMacroChange(new HostMacroName('shared'), 'tpl-value', isPassword: false, id: new HostMacroId(5)),
        ]));

        self::assertSame([], $host->checkOptions->macros);
    }

    public function testAPromotedTemplatePasswordIsCopiedUnderTheHostOwnVaultEntry(): void
    {
        // R4 with a vault: the template's secret is copied under the host's existing entry, never
        // shared through the template's path.
        $this->addPoller(1);
        $this->vault->vaultEnabled = true;
        $hostReference = 'secret::vault::monitoring/hosts/host-uuid::_HOSTSNMPCOMMUNITY';
        $this->vault->extractedUuids[$hostReference] = 'host-uuid';
        $templateSecret = 'secret::vault::monitoring/hosts/tpl-uuid::_HOSTTPLPWD';
        $this->vault->extractedUuids[$templateSecret] = 'tpl-uuid';
        $this->vault->resolved[$templateSecret] = 'tpl-secret';
        $this->hostTemplateRepository->hostTemplates[7] = new HostTemplate(
            new HostTemplateId(7),
            new HostTemplateName('generic-host'),
            new Collection([new HostMacro(new HostMacroName('tplpwd'), $templateSecret, isPassword: true, id: new HostMacroId(70))], HostMacro::class),
        );
        $templateIds = new Collection([new HostTemplateId(7)], HostTemplateId::class);
        $this->seedHost(10, snmpCommunity: new SnmpCommunity($hostReference), templateIds: $templateIds);

        $host = ($this->handler)($this->command(10, snmpCommunity: 'public', templateIds: $templateIds, macroChanges: [
            new HostMacroChange(new HostMacroName('mypwd'), null, isPassword: true, id: new HostMacroId(70), parent: HostMacroParentEnum::Template),
        ]));

        $macroWrite = null;
        foreach ($this->vault->writeManyCalls as $call) {
            if (array_key_exists('_HOSTMYPWD', $call['secrets'])) {
                $macroWrite = $call;
            }
        }
        self::assertNotNull($macroWrite, 'The promoted password should have been written to the vault.');
        self::assertSame('tpl-secret', $macroWrite['secrets']['_HOSTMYPWD']);
        self::assertSame('host-uuid', $macroWrite['uuid']);
        self::assertNull($host->checkOptions->macros[0]->id);
        self::assertStringNotContainsString('tpl-uuid', $host->checkOptions->macros[0]->value);
    }

    public function testEmptyingTheSnmpCommunityRemovesItsKeyFromTheVault(): void
    {
        // A password macro keeps the entry alive, but the community's key must not linger in it.
        $this->addPoller(1);
        $this->vault->vaultEnabled = true;
        $this->seedHost(
            10,
            snmpCommunity: new SnmpCommunity('secret::vault::monitoring/hosts/host-uuid::_HOSTSNMPCOMMUNITY'),
            macros: [new HostMacro(new HostMacroName('pwd'), 'secret::vault::monitoring/hosts/host-uuid::_HOSTPWD', isPassword: true, id: new HostMacroId(5))],
        );

        ($this->handler)($this->command(10, snmpCommunity: null, macroChanges: [
            new HostMacroChange(new HostMacroName('pwd'), null, isPassword: true, id: new HostMacroId(5)),
        ]));

        self::assertCount(1, $this->vault->writeManyCalls);
        self::assertSame('host-uuid', $this->vault->writeManyCalls[0]['uuid']);
        self::assertSame([], $this->vault->writeManyCalls[0]['secrets']);
        self::assertSame(['_HOSTSNMPCOMMUNITY'], $this->vault->writeManyCalls[0]['deletes']);
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostVaultPurgeRequested::class));
    }

    public function testANewPasswordMacroJoinsTheHostExistingVaultEntry(): void
    {
        // Legacy keeps every secret of a host (SNMP community + password macros) under one entry.
        $this->addPoller(1);
        $this->vault->vaultEnabled = true;
        $community = 'secret::vault::monitoring/hosts/host-uuid::_HOSTSNMPCOMMUNITY';
        $this->seedHost(10, snmpCommunity: new SnmpCommunity($community));

        $host = ($this->handler)($this->command(10, snmpCommunity: 'public', macroChanges: [
            new HostMacroChange(new HostMacroName('pwd'), 'plain-secret', isPassword: true),
        ]));

        self::assertSame($community, $host->snmpCommunity?->value);
        self::assertSame('secret::vault::monitoring/hosts/host-uuid::_HOSTPWD', $host->checkOptions->macros[0]->value);
        foreach ($this->vault->writeManyCalls as $call) {
            self::assertSame('host-uuid', $call['uuid']);
        }
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostVaultPurgeRequested::class));
    }

    public function testAPasswordMacroOnAHostWithoutSecretMintsAnEntry(): void
    {
        $this->addPoller(1);
        $this->vault->vaultEnabled = true;
        $this->seedHost(10);

        $host = ($this->handler)($this->command(10, macroChanges: [
            new HostMacroChange(new HostMacroName('pwd'), 'plain-secret', isPassword: true),
        ]));

        self::assertCount(1, $this->vault->writeManyCalls);
        self::assertNull($this->vault->writeManyCalls[0]['uuid']);
        self::assertSame(['_HOSTPWD' => 'plain-secret'], $this->vault->writeManyCalls[0]['secrets']);
        self::assertSame('secret::vault::monitoring/hosts/new-uuid::_HOSTPWD', $host->checkOptions->macros[0]->value);
    }

    public function testRemovingAPasswordMacroClearsItsKeyAndKeepsTheEntry(): void
    {
        $this->addPoller(1);
        $this->vault->vaultEnabled = true;
        $community = 'secret::vault::monitoring/hosts/host-uuid::_HOSTSNMPCOMMUNITY';
        $this->seedHost(
            10,
            snmpCommunity: new SnmpCommunity($community),
            macros: [new HostMacro(new HostMacroName('pwd'), 'secret::vault::monitoring/hosts/host-uuid::_HOSTPWD', isPassword: true, id: new HostMacroId(5))],
        );
        $this->vault->writtenPaths['_HOSTSNMPCOMMUNITY'] = $community;

        $host = ($this->handler)($this->command(10, snmpCommunity: 'public'));

        self::assertSame([], $host->checkOptions->macros);
        $deletes = array_merge(...array_column($this->vault->writeManyCalls, 'deletes'));
        self::assertSame(['_HOSTPWD'], $deletes);
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostVaultPurgeRequested::class));
    }

    public function testRemovingTheLastPasswordMacroRequestsAPurge(): void
    {
        $this->addPoller(1);
        $this->vault->vaultEnabled = true;
        $this->seedHost(10, macros: [
            new HostMacro(new HostMacroName('pwd'), 'secret::vault::monitoring/hosts/host-uuid::_HOSTPWD', isPassword: true, id: new HostMacroId(5)),
        ]);

        ($this->handler)($this->command(10));

        /** @var list<HostVaultPurgeRequested> $events */
        $events = $this->eventBus->getDispatchedEvents(HostVaultPurgeRequested::class);
        self::assertCount(1, $events);
        self::assertSame('host-uuid', $events[0]->host->getVaultUuid($this->vault));
    }

    public function testRenamingAPasswordMacroKeepsItsSecret(): void
    {
        // R5: the macro keeps its id and its vault reference, no plaintext is ever needed.
        $this->addPoller(1);
        $this->vault->vaultEnabled = true;
        $reference = 'secret::vault::monitoring/hosts/host-uuid::_HOSTPWD';
        $this->seedHost(10, macros: [new HostMacro(new HostMacroName('pwd'), $reference, isPassword: true, id: new HostMacroId(5))]);

        $host = ($this->handler)($this->command(10, macroChanges: [
            new HostMacroChange(new HostMacroName('renamed'), null, isPassword: true, id: new HostMacroId(5)),
        ]));

        $macro = $host->checkOptions->macros[0];
        self::assertSame(['RENAMED', 5, $reference], [$macro->name->value, $macro->id?->value, $macro->value]);
        self::assertSame([], $this->vault->writeManyCalls);
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostVaultPurgeRequested::class));
    }

    public function testANewPasswordValueIsRewrittenInTheHostEntry(): void
    {
        $this->addPoller(1);
        $this->vault->vaultEnabled = true;
        $this->seedHost(10, macros: [
            new HostMacro(new HostMacroName('pwd'), 'secret::vault::monitoring/hosts/host-uuid::_HOSTPWD', isPassword: true, id: new HostMacroId(5)),
        ]);

        $host = ($this->handler)($this->command(10, macroChanges: [
            new HostMacroChange(new HostMacroName('pwd'), 'new-secret', isPassword: true, id: new HostMacroId(5)),
        ]));

        self::assertCount(1, $this->vault->writeManyCalls);
        self::assertSame('host-uuid', $this->vault->writeManyCalls[0]['uuid']);
        self::assertSame(['_HOSTPWD' => 'new-secret'], $this->vault->writeManyCalls[0]['secrets']);
        self::assertSame('secret::vault::monitoring/hosts/host-uuid::_HOSTPWD', $host->checkOptions->macros[0]->value);
        self::assertSame(5, $host->checkOptions->macros[0]->id?->value);
    }

    public function testTurningAPasswordIntoAPlainMacroClearsItsKey(): void
    {
        $this->addPoller(1);
        $this->vault->vaultEnabled = true;
        $community = 'secret::vault::monitoring/hosts/host-uuid::_HOSTSNMPCOMMUNITY';
        $this->seedHost(
            10,
            snmpCommunity: new SnmpCommunity($community),
            macros: [new HostMacro(new HostMacroName('pwd'), 'secret::vault::monitoring/hosts/host-uuid::_HOSTPWD', isPassword: true, id: new HostMacroId(5))],
        );

        $host = ($this->handler)($this->command(10, snmpCommunity: 'public', macroChanges: [
            new HostMacroChange(new HostMacroName('pwd'), 'now-visible', isPassword: false, id: new HostMacroId(5)),
        ]));

        self::assertSame(['now-visible', false], [$host->checkOptions->macros[0]->value, $host->checkOptions->macros[0]->isPassword]);
        $deletes = array_merge(...array_column($this->vault->writeManyCalls, 'deletes'));
        self::assertSame(['_HOSTPWD'], $deletes);
    }

    public function testAnEmptyPasswordIsStoredWithoutReachingTheVault(): void
    {
        // R8: an empty password is an explicit value, nothing to vault.
        $this->addPoller(1);
        $this->vault->vaultEnabled = true;
        $this->seedHost(10);

        $host = ($this->handler)($this->command(10, macroChanges: [
            new HostMacroChange(new HostMacroName('pwd'), '', isPassword: true),
        ]));

        self::assertSame('', $host->checkOptions->macros[0]->value);
        self::assertSame([], $this->vault->writeManyCalls);
    }

    public function testItRejectsKeepingTheStoredValueOfAMacroThatIsNotAPassword(): void
    {
        // A null value only keeps the value of a stored password: a plain macro's value is echoed back.
        $this->addPoller(1);
        $this->seedHost(10, macros: [new HostMacro(new HostMacroName('plain'), 'v', isPassword: false, id: new HostMacroId(5))]);

        $this->expectException(HostMacroValueRequiredException::class);

        ($this->handler)($this->command(10, macroChanges: [
            new HostMacroChange(new HostMacroName('plain'), null, isPassword: true, id: new HostMacroId(5)),
        ]));
    }

    public function testACheckCommandMacroIsResolvedByNameWhateverIdIsSent(): void
    {
        // Command macro ids are unstable (rewritten on every command save): a stale one is no error.
        $this->addPoller(1);
        $this->commandRepository->commands[9] = new Command(
            new CommandId(9),
            new CommandName('check_it'),
            CommandTypeEnum::Check,
            new CommandLine('$USER1$/check -a $_HOSTFROMCOMMAND$'),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: false,
            connector: null,
            comment: null,
        );
        $this->seedHost(10);

        $host = ($this->handler)($this->command(10, checkOptions: new CheckOptions(new CommandId(9)), macroChanges: [
            new HostMacroChange(new HostMacroName('fromcommand'), 'set', isPassword: false, id: new HostMacroId(999), parent: HostMacroParentEnum::Command),
        ]));

        self::assertCount(1, $host->checkOptions->macros);
        self::assertTrue($host->checkOptions->macros[0]->isDirect());
        self::assertSame('set', $host->checkOptions->macros[0]->value);
    }

    public function testResendingTheStoredNotificationsIsANoOp(): void
    {
        $this->addPoller(1);
        $this->seedHost(10, notifications: $this->notifications([NotificationOptionEnum::Down, NotificationOptionEnum::Recovery], contactIds: [3, 4]));

        // Same block, options and contacts in another order.
        ($this->handler)($this->command(10, notifications: $this->notifications([NotificationOptionEnum::Recovery, NotificationOptionEnum::Down], contactIds: [4, 3])));

        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostUpdated::class));
    }

    public function testANotificationChangeIsLogged(): void
    {
        $this->addPoller(1);
        $this->seedHost(10, notifications: $this->notifications([NotificationOptionEnum::Down]));

        ($this->handler)($this->command(10, notifications: $this->notifications([NotificationOptionEnum::Recovery])));

        /** @var list<HostUpdated> $events */
        $events = $this->eventBus->getDispatchedEvents(HostUpdated::class);
        self::assertCount(1, $events);
        self::assertTrue($events[0]->loggable);
    }

    public function testAnUpdateWithoutNotificationsLeavesTheStoredOnesOutOfTheComparison(): void
    {
        // Cloud: a PUT never carries the block, the stored one is not a change to report.
        $this->addPoller(1);
        $this->seedHost(10, notifications: $this->notifications([NotificationOptionEnum::Down], contactIds: [3]));

        ($this->handler)($this->command(10, notifications: null));

        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostUpdated::class));
    }

    public function testAMacroChangeOnlyFiresANonLoggableUpdate(): void
    {
        // Macros are not part of the legacy activity log, but the engine must still regenerate.
        $this->addPoller(1);
        $this->seedHost(10, macros: [new HostMacro(new HostMacroName('m'), 'v1', isPassword: false, id: new HostMacroId(5))]);

        ($this->handler)($this->command(10, macroChanges: [
            new HostMacroChange(new HostMacroName('m'), 'v2', isPassword: false, id: new HostMacroId(5)),
        ]));

        /** @var list<HostUpdated> $events */
        $events = $this->eventBus->getDispatchedEvents(HostUpdated::class);
        self::assertCount(1, $events);
        self::assertFalse($events[0]->loggable);
    }

    public function testItDeploysServicesWhenTemplatesArePresent(): void
    {
        $this->addPoller(1);
        $this->hostTemplateRepository->hostTemplates[7] = new HostTemplate(new HostTemplateId(7), new HostTemplateName('generic-host'));
        $this->seedHost(10);

        ($this->handler)($this->command(10, templateIds: new Collection([new HostTemplateId(7)], HostTemplateId::class)));

        self::assertTrue($this->eventBus->shouldHaveDispatched(HostServicesDeploymentRequested::class));
    }

    public function testItPreservesOutOfScopeHostGroupsForARestrictedViewer(): void
    {
        $this->addPoller(1);
        // Group 1 is visible to the viewer; group 2 belongs to a scope they cannot see.
        $this->hostGroupRepository->hostGroups[1] = new HostGroup(new HostGroupId(1), new HostGroupName('grp-visible'));
        $this->resourceAccessRepository->accessibleHostGroupIds = new Collection([new HostGroupId(1)], HostGroupId::class);
        $this->seedHost(10, hostGroupIds: new Collection([new HostGroupId(1), new HostGroupId(2)], HostGroupId::class));

        // The viewer resends only the group they can see, as a GET -> PUT round trip would.
        ($this->handler)($this->command(
            10,
            hostGroupIds: new Collection([new HostGroupId(1)], HostGroupId::class),
            viewerId: new UserId(42),
        ));

        $groupIds = array_map(
            static fn (HostGroupId $id): int => $id->value,
            $this->hostRepository->findOne(new HostId(10))?->hostGroupIds->toArray() ?? [],
        );
        sort($groupIds);
        // The out-of-scope group 2 is preserved rather than wiped.
        self::assertSame([1, 2], $groupIds);
    }

    public function testItPreservesAnOutOfScopeSeverityForARestrictedViewer(): void
    {
        $this->addPoller(1);
        // The viewer can access only severity 1; the host carries the out-of-scope severity 2.
        $this->resourceAccessRepository->accessibleHostSeverityIds = new Collection([new HostSeverityId(1)], HostSeverityId::class);
        $this->seedHost(10, severityId: new HostSeverityId(2));

        // The viewer sends no severity: they cannot see the stored one, so it must not be cleared.
        $host = ($this->handler)($this->command(10, viewerId: new UserId(42)));

        self::assertSame(2, $host->severityId?->value);
    }

    public function testItFiresANonLoggableUpdateWhenOnlyRelationsChange(): void
    {
        $this->addPoller(1);
        $this->hostGroupRepository->hostGroups[1] = new HostGroup(new HostGroupId(1), new HostGroupName('g1'));
        $this->hostGroupRepository->hostGroups[2] = new HostGroup(new HostGroupId(2), new HostGroupName('g2'));
        $this->seedHost(10, hostGroupIds: new Collection([new HostGroupId(1)], HostGroupId::class));

        // Adding a group changes nothing legacy logs, but the engine and ACL must still refresh.
        ($this->handler)($this->command(
            10,
            hostGroupIds: new Collection([new HostGroupId(1), new HostGroupId(2)], HostGroupId::class),
        ));

        /** @var list<HostUpdated> $events */
        $events = $this->eventBus->getDispatchedEvents(HostUpdated::class);
        self::assertCount(1, $events);
        // The side effects run (HostUpdated is dispatched) but the activity log stays silent.
        self::assertFalse($events[0]->loggable);
    }

    public function testItFiresALoggableUpdateWhenALoggedFieldChanges(): void
    {
        $this->addPoller(1);
        $this->seedHost(10, name: 'server-old');

        ($this->handler)($this->command(10, name: 'server-new'));

        /** @var list<HostUpdated> $events */
        $events = $this->eventBus->getDispatchedEvents(HostUpdated::class);
        self::assertCount(1, $events);
        self::assertTrue($events[0]->loggable);
    }

    public function testItFiresNoUpdateEventWhenNothingChanges(): void
    {
        $this->addPoller(1);
        $this->seedHost(10);

        ($this->handler)($this->command(10));

        self::assertSame([], $this->eventBus->getDispatchedEvents(HostUpdated::class));
    }

    public function testItDoesNotPreserveWhenTheViewerIsUnrestricted(): void
    {
        $this->addPoller(1);
        $this->hostGroupRepository->hostGroups[1] = new HostGroup(new HostGroupId(1), new HostGroupName('grp'));
        $this->seedHost(10, hostGroupIds: new Collection([new HostGroupId(1), new HostGroupId(2)], HostGroupId::class));

        // An admin (no viewer id) replaces the groups wholesale — nothing is preserved.
        ($this->handler)($this->command(
            10,
            hostGroupIds: new Collection([new HostGroupId(1)], HostGroupId::class),
        ));

        $groupIds = array_map(
            static fn (HostGroupId $id): int => $id->value,
            $this->hostRepository->findOne(new HostId(10))?->hostGroupIds->toArray() ?? [],
        );
        self::assertSame([1], $groupIds);
    }

    /**
     * @param ?Collection<HostTemplateId> $templateIds
     * @param ?Collection<HostGroupId> $hostGroupIds
     * @param list<HostMacroChange> $macroChanges
     */
    private function command(
        int $id,
        int $pollerId = 1,
        string $name = 'server-01',
        bool $activated = true,
        ?string $snmpCommunity = null,
        ?Collection $templateIds = null,
        CheckOptions $checkOptions = new CheckOptions(null),
        ?Collection $hostGroupIds = null,
        ?HostSeverityId $severityId = null,
        ?UserId $viewerId = null,
        array $macroChanges = [],
        ?Notifications $notifications = null,
    ): UpdateHostCommand {
        return new UpdateHostCommand(
            id: new HostId($id),
            name: new HostName($name),
            address: new HostAddress('127.0.0.1'),
            pollerId: new PollerId($pollerId),
            activated: $activated,
            hostGroupIds: $hostGroupIds ?? new Collection([], HostGroupId::class),
            updatedBy: 1,
            viewerId: $viewerId,
            templateIds: $templateIds ?? new Collection([], HostTemplateId::class),
            snmpCommunity: $snmpCommunity,
            severityId: $severityId,
            checkOptions: $checkOptions,
            notifications: $notifications,
            macroChanges: $macroChanges,
        );
    }

    /**
     * @param ?Collection<HostGroupId> $hostGroupIds
     * @param list<HostMacro> $macros the host's stored direct macros
     * @param ?Collection<HostTemplateId> $templateIds
     */
    private function seedHost(
        int $id,
        int $pollerId = 1,
        string $name = 'server-01',
        bool $activated = true,
        ?SnmpCommunity $snmpCommunity = null,
        ?Collection $hostGroupIds = null,
        ?HostSeverityId $severityId = null,
        array $macros = [],
        ?Collection $templateIds = null,
        ?Notifications $notifications = null,
    ): Host {
        $host = new Host(
            id: null,
            name: new HostName($name),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: $activated,
            pollerId: new PollerId($pollerId),
            templateIds: $templateIds ?? new Collection([], HostTemplateId::class),
            hostGroupIds: $hostGroupIds ?? new Collection([], HostGroupId::class),
            severityId: $severityId,
            snmpCommunity: $snmpCommunity,
            checkOptions: new CheckOptions(null, [], $macros),
            notifications: $notifications,
        );

        return $this->hostRepository->seed($host, $id);
    }

    /**
     * @param list<NotificationOptionEnum> $options
     * @param list<int> $contactIds
     */
    private function notifications(array $options, array $contactIds = []): Notifications
    {
        return new Notifications(
            enabled: TriStateEnum::True,
            contactIds: new Collection(
                array_map(static fn (int $id): NotificationContactId => new NotificationContactId($id), $contactIds),
                NotificationContactId::class,
            ),
            contactGroupIds: new Collection([], ContactGroupId::class),
            options: $options,
            interval: 30,
        );
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

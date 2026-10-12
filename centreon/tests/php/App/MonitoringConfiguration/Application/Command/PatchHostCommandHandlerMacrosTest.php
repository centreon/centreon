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

use App\MonitoringConfiguration\Application\Command\MacroChanges;
use App\MonitoringConfiguration\Application\Command\PatchHostCommand;
use App\MonitoringConfiguration\Application\Command\PatchHostCommandHandler;
use App\MonitoringConfiguration\Application\Service\AdditiveInheritanceModeApplier;
use App\MonitoringConfiguration\Application\Service\HostMacroSecretsSynchronizer;
use App\MonitoringConfiguration\Application\Service\HostMacrosUpdater;
use App\MonitoringConfiguration\Application\Service\HostReferencesChecker;
use App\MonitoringConfiguration\Application\Service\HostRelationsUpdater;
use App\MonitoringConfiguration\Application\Service\HostSecretsSynchronizer;
use App\MonitoringConfiguration\Application\Service\HostTemplateServicesCleaner;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Event\HostMassChanged;
use App\MonitoringConfiguration\Domain\Event\HostVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\HostMacroNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostMacroValueIsAVaultReferenceException;
use App\MonitoringConfiguration\Domain\Exception\HostMacroValueRequiredException;
use App\MonitoringConfiguration\Domain\Exception\ReservedHostMacroNameException;
use App\MonitoringConfiguration\Domain\Service\HostMacroChangesResolver;
use App\MonitoringConfiguration\Domain\Service\InheritedHostMacrosResolver;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Collection;
use App\Shared\Domain\NoValue;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeContactGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostCategoryRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostSeverityRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeMediaRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeNotificationContactRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeOptionRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakePollerRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeStandardMacroRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimePeriodRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimezoneRepository;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Shared\Double\EventBusSpy;
use Tests\App\Shared\Double\FakeVault;
use Tests\App\Shared\Infrastructure\Messenger\Double\LoggerSpy;

final class PatchHostCommandHandlerMacrosTest extends TestCase
{
    private const HOST_ID = 5;

    private FakeHostRepository $repository;

    private FakeVault $vault;

    private EventBusSpy $eventBus;

    private PatchHostCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new FakeHostRepository();
        $this->vault = new FakeVault();
        $this->vault->vaultEnabled = false;

        $this->eventBus = new EventBusSpy();
        $hostTemplateRepository = new FakeHostTemplateRepository();
        $commandRepository = new FakeCommandRepository();
        $resourceAccessRepository = new FakeResourceAccessRepository();

        $this->handler = new PatchHostCommandHandler(
            $this->repository,
            $commandRepository,
            new HostReferencesChecker(
                new FakePollerRepository(),
                new FakeHostSeverityRepository(),
                new FakeTimezoneRepository(),
                new FakeTimePeriodRepository(),
                new FakeMediaRepository(),
                $commandRepository,
                $resourceAccessRepository,
            ),
            new HostRelationsUpdater(
                new FakeHostGroupRepository(),
                $hostTemplateRepository,
                new FakeHostCategoryRepository(),
                $this->repository,
                new FakeNotificationContactRepository(),
                new FakeContactGroupRepository(),
                $resourceAccessRepository,
            ),
            new HostTemplateServicesCleaner($hostTemplateRepository, new FakeServiceRepository(), $this->eventBus),
            new HostMacrosUpdater(
                new InheritedHostMacrosResolver($hostTemplateRepository, $commandRepository),
                new HostMacroChangesResolver(),
                new FakeStandardMacroRepository(['$_HOSTRESERVED$']),
                $this->vault,
            ),
            new HostSecretsSynchronizer(
                $this->vault,
                new VaultCredentialWriter($this->vault),
                new HostMacroSecretsSynchronizer($this->vault, new VaultCredentialWriter($this->vault)),
            ),
            $this->vault,
            new AdditiveInheritanceModeApplier(new FakeOptionRepository()),
            $this->eventBus,
            new LoggerSpy(),
        );
    }

    public function testAReplacementWritesTheMacrosInPlaceAndRemovesTheOtherOnes(): void
    {
        $this->seedHost([$this->macro('PORT', '80', id: 10), $this->macro('DROPPED', 'x', id: 11)]);

        $result = $this->patch(MacroChanges::replace([
            new HostMacroChange(new HostMacroName('PORT'), '8080', false, new HostMacroId(10)),
            new HostMacroChange(new HostMacroName('ADDED'), 'y', false),
        ]));

        self::assertSame(['PORT' => '8080', 'ADDED' => 'y'], $this->valuesByName($result));
        self::assertSame(10, $this->macroNamed($result, 'PORT')->id?->value);
        self::assertCount(1, $this->repository->macrosReplacedHosts);
        self::assertSame([], $this->repository->relationsReplacedHosts);
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostMassChanged::class, 1));
    }

    public function testAnUpsertKeepsTheMacrosItDoesNotMention(): void
    {
        $this->seedHost([$this->macro('PORT', '80', id: 10), $this->macro('KEPT', 'k', id: 11)]);

        $result = $this->patch(MacroChanges::upsert([
            new HostMacroChange(new HostMacroName('PORT'), '8080', false, new HostMacroId(10)),
            new HostMacroChange(new HostMacroName('ADDED'), 'y', false),
        ]));

        self::assertSame(['KEPT' => 'k', 'PORT' => '8080', 'ADDED' => 'y'], $this->valuesByName($result));
    }

    public function testAnUpsertWithoutIdUpdatesTheMacroOfTheSameName(): void
    {
        $this->seedHost([$this->macro('PORT', '80', id: 10)]);

        $result = $this->patch(MacroChanges::upsert([
            new HostMacroChange(new HostMacroName('PORT'), '9090', false),
        ]));

        self::assertCount(1, $result->checkOptions->macros);
        self::assertSame('9090', $this->macroNamed($result, 'PORT')->value);
        self::assertSame(10, $this->macroNamed($result, 'PORT')->id?->value);
    }

    public function testAnUpsertThatChangesNothingSavesNothing(): void
    {
        $this->seedHost([$this->macro('PORT', '80', id: 10)]);

        $this->patch(MacroChanges::upsert([
            new HostMacroChange(new HostMacroName('PORT'), '80', false, new HostMacroId(10)),
        ]));

        self::assertSame([], $this->repository->updatedHosts);
        self::assertSame([], $this->repository->macrosReplacedHosts);
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostMassChanged::class));
    }

    public function testARemovalTakesTheMacrosOutByIdAndKeepsTheOtherOnes(): void
    {
        $this->seedHost([$this->macro('PORT', '80', id: 10), $this->macro('KEPT', 'k', id: 11)]);

        $result = $this->patch(MacroChanges::remove([new HostMacroId(10)]));

        self::assertSame(['KEPT' => 'k'], $this->valuesByName($result));
    }

    public function testARemovalOfAMacroTheHostDoesNotOwnIsRefused(): void
    {
        $this->seedHost([$this->macro('PORT', '80', id: 10)]);

        $this->expectException(HostMacroNotFoundException::class);

        $this->patch(MacroChanges::remove([new HostMacroId(10), new HostMacroId(999)]));
    }

    public function testAChangeOfAnUnknownMacroIsRefused(): void
    {
        $this->seedHost([$this->macro('PORT', '80', id: 10)]);

        $this->expectException(HostMacroNotFoundException::class);

        $this->patch(MacroChanges::replace([new HostMacroChange(new HostMacroName('PORT'), '1', false, new HostMacroId(999))]));
    }

    public function testKeepingTheValueOfAMacroThatIsNotAPasswordIsRefused(): void
    {
        $this->seedHost([$this->macro('PORT', '80', id: 10)]);

        $this->expectException(HostMacroValueRequiredException::class);

        $this->patch(MacroChanges::replace([new HostMacroChange(new HostMacroName('PORT'), null, true, new HostMacroId(10))]));
    }

    public function testAReservedNameIsRefused(): void
    {
        $this->seedHost();

        $this->expectException(ReservedHostMacroNameException::class);

        $this->patch(MacroChanges::upsert([new HostMacroChange(new HostMacroName('RESERVED'), 'x', false)]));
    }

    public function testAVaultReferenceAsAValueIsRefusedBeforeAnythingIsWrittenToTheVault(): void
    {
        $this->vault->vaultEnabled = true;
        $this->seedHost();

        try {
            ($this->handler)(new PatchHostCommand(
                id: new HostId(self::HOST_ID),
                updatedBy: 1,
                snmpCommunity: 'a-new-community',
                macros: MacroChanges::upsert([
                    new HostMacroChange(new HostMacroName('TOKEN'), 'secret::vault::monitoring/hosts/other-uuid::_HOSTTOKEN', true),
                ]),
            ));
            self::fail('A vault reference should have been refused.');
        } catch (HostMacroValueIsAVaultReferenceException) {
            self::assertSame([], $this->vault->writeManyCalls);
            self::assertSame([], $this->repository->updatedHosts);
        }
    }

    public function testAPasswordMacroIsWrittenToTheVaultAndStoredAsAReference(): void
    {
        $this->vault->vaultEnabled = true;
        $this->seedHost();

        $result = $this->patch(MacroChanges::upsert([new HostMacroChange(new HostMacroName('TOKEN'), 'plain-secret', true)]));

        self::assertSame(['_HOSTTOKEN' => 'plain-secret'], $this->vault->writeManyCalls[0]['secrets']);
        self::assertSame('secret::vault::monitoring/hosts/new-uuid::_HOSTTOKEN', $this->macroNamed($result, 'TOKEN')->value);
    }

    public function testTheCommunityAndTheMacrosShareOneNewVaultEntry(): void
    {
        $this->vault->vaultEnabled = true;
        $this->seedHost();

        $this->patchWith(
            MacroChanges::upsert([new HostMacroChange(new HostMacroName('TOKEN'), 'plain-secret', true)]),
            community: 'a-community',
        );

        self::assertCount(2, $this->vault->writeManyCalls);
        self::assertNull($this->vault->writeManyCalls[0]['uuid']);
        self::assertSame('new-uuid', $this->vault->writeManyCalls[1]['uuid']);
    }

    public function testRemovingTheLastSecretClearsItsKeyAndReleasesTheWholeEntry(): void
    {
        $this->vault->vaultEnabled = true;
        $reference = 'secret::vault::monitoring/hosts/uuid-1::_HOSTTOKEN';
        $before = $this->seedHost([$this->macro('TOKEN', $reference, isPassword: true, id: 10)]);

        $result = $this->patch(MacroChanges::remove([new HostMacroId(10)]));

        self::assertSame([], $result->checkOptions->macros);
        self::assertSame(['_HOSTTOKEN'], $this->vault->writeManyCalls[0]['deletes']);
        $event = $this->eventBus->getDispatchedEvents(HostVaultPurgeRequested::class)[0];
        self::assertSame($before, $event->host);
        self::assertTrue($event->bestEffort);
    }

    public function testKeepingThePasswordOfAMacroLeavesTheVaultAlone(): void
    {
        $this->vault->vaultEnabled = true;
        $reference = 'secret::vault::monitoring/hosts/uuid-1::_HOSTTOKEN';
        $this->seedHost([$this->macro('TOKEN', $reference, isPassword: true, id: 10)]);

        $this->patch(MacroChanges::upsert([new HostMacroChange(new HostMacroName('TOKEN'), null, true, new HostMacroId(10))]));

        self::assertSame([], $this->vault->writeManyCalls);
        self::assertSame([], $this->repository->macrosReplacedHosts);
    }

    /**
     * @param list<HostMacro> $macros
     */
    private function seedHost(array $macros = [], ?string $community = null): Host
    {
        return $this->repository->seed(new Host(
            id: null,
            name: new HostName('server-01'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            snmpCommunity: $community === null ? null : new SnmpCommunity($community),
            checkOptions: new CheckOptions(null, macros: $macros),
        ), self::HOST_ID);
    }

    private function patch(MacroChanges $macros): Host
    {
        return $this->patchWith($macros);
    }

    private function patchWith(MacroChanges $macros, ?string $community = null): Host
    {
        return ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            snmpCommunity: $community ?? new NoValue(),
            macros: $macros,
        ));
    }

    private function macro(string $name, string $value, bool $isPassword = false, ?int $id = null): HostMacro
    {
        return new HostMacro(new HostMacroName($name), $value, $isPassword, $id === null ? null : new HostMacroId($id));
    }

    private function macroNamed(Host $host, string $name): HostMacro
    {
        $macro = array_find($host->checkOptions->macros, static fn (HostMacro $macro): bool => $macro->name->value === $name);
        self::assertInstanceOf(HostMacro::class, $macro);

        return $macro;
    }

    /**
     * @return array<string, string>
     */
    private function valuesByName(Host $host): array
    {
        $values = [];
        foreach ($host->checkOptions->macros as $macro) {
            $values[$macro->name->value] = $macro->value;
        }

        return $values;
    }
}

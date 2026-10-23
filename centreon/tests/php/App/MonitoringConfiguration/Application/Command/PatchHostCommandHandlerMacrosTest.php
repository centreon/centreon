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

use App\MonitoringConfiguration\Application\Command\ListChange;
use App\MonitoringConfiguration\Application\Command\MacroChanges;
use App\MonitoringConfiguration\Application\Command\PatchHostCommand;
use App\MonitoringConfiguration\Application\Command\PatchHostCommandHandler;
use App\MonitoringConfiguration\Application\Service\AdditiveInheritanceModeApplier;
use App\MonitoringConfiguration\Application\Service\HostLicenseLimit;
use App\MonitoringConfiguration\Application\Service\HostMacroSecretsSynchronizer;
use App\MonitoringConfiguration\Application\Service\HostMacrosUpdater;
use App\MonitoringConfiguration\Application\Service\HostReferencesChecker;
use App\MonitoringConfiguration\Application\Service\HostRelationsUpdater;
use App\MonitoringConfiguration\Application\Service\HostTemplateLicenseGuard;
use App\MonitoringConfiguration\Application\Service\HostTemplateServicesCleaner;
use App\MonitoringConfiguration\Application\Service\UnlicensedHostTemplates;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
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
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Event\HostMassChanged;
use App\MonitoringConfiguration\Domain\Event\HostVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\DuplicateHostMacroNameException;
use App\MonitoringConfiguration\Domain\Exception\HostMacroNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostMacroPasswordUnmaskedException;
use App\MonitoringConfiguration\Domain\Exception\HostMacroValueIsAVaultReferenceException;
use App\MonitoringConfiguration\Domain\Exception\HostMacroValueRequiredException;
use App\MonitoringConfiguration\Domain\Exception\ReservedHostMacroNameException;
use App\MonitoringConfiguration\Domain\Exception\VaultWriteFailedException;
use App\MonitoringConfiguration\Domain\Service\HostMacroChangesResolver;
use App\MonitoringConfiguration\Domain\Service\InheritedHostMacrosResolver;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Collection;
use App\Shared\Domain\NoValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeContactGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostCategoryRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostSeverityRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeMediaRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeModuleRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeNotificationContactRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeOptionRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakePollerRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeStandardMacroRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimePeriodRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimezoneRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;
use Tests\App\Shared\Double\EventBusSpy;
use Tests\App\Shared\Double\FakeVault;
use Tests\App\Shared\Infrastructure\Messenger\Double\LoggerSpy;

final class PatchHostCommandHandlerMacrosTest extends TestCase
{
    private const HOST_ID = 5;

    private FakeHostRepository $repository;

    private FakeVault $vault;

    private FakeHostTemplateRepository $templateRepository;

    private FakeCommandRepository $commandRepository;

    private LoggerSpy $logger;

    private EventBusSpy $eventBus;

    private PatchHostCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new FakeHostRepository();
        $this->vault = new FakeVault();
        $this->vault->vaultEnabled = false;

        $this->eventBus = new EventBusSpy();
        $this->logger = new LoggerSpy();
        $hostTemplateRepository = new FakeHostTemplateRepository();
        $this->templateRepository = $hostTemplateRepository;
        $commandRepository = new FakeCommandRepository();
        $this->commandRepository = $commandRepository;

        $resourceAccessRepository = $this->repository->resourceAccess;

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
                new AccessScopeResolver($resourceAccessRepository),
            ),
            new HostRelationsUpdater(
                new FakeHostGroupRepository(),
                $hostTemplateRepository,
                new FakeHostCategoryRepository(),
                $this->repository,
                new FakeNotificationContactRepository(),
                new FakeContactGroupRepository(),
                new AccessScopeResolver($resourceAccessRepository),
                new HostTemplateLicenseGuard(new UnlicensedHostTemplates(
                    new HostLicenseLimit(new FakeModuleRepository(), new FakeHostRepository()),
                    $hostTemplateRepository,
                )),
            ),
            new HostTemplateServicesCleaner($hostTemplateRepository, new FakeServiceRepository(), $this->eventBus),
            new HostMacrosUpdater(
                new InheritedHostMacrosResolver($hostTemplateRepository, $commandRepository),
                new HostMacroChangesResolver(),
                new FakeStandardMacroRepository(['$_HOSTRESERVED$']),
                $this->vault,
            ),
            new HostMacroSecretsSynchronizer($this->vault, new VaultCredentialWriter($this->vault)),
            $this->vault,
            new VaultCredentialWriter($this->vault),
            new AdditiveInheritanceModeApplier(new FakeOptionRepository()),
            $this->eventBus,
            $this->logger,
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
                actor: TestActingUsers::unrestricted(1),
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
     * @param array{
     *     vault?: bool,
     *     community?: ?string,
     *     before?: list<array{string, string, bool, int}>,
     *     changes: MacroChanges,
     *     after: array<string, array{string, bool}>,
     *     vaultCalls?: list<array{secrets: array<string, string>, deletes: list<string>}>,
     *     macrosWritten?: bool,
     *     massChanged?: bool,
     *     purged?: bool,
     * } $scenario every macro is [value, isPassword], by name; the vault is on unless said otherwise
     */
    #[DataProvider('macroScenarios')]
    public function testTheMacrosEndUpAsExpected(array $scenario): void
    {
        $this->vault->vaultEnabled = $scenario['vault'] ?? true;
        $this->seedHost(
            array_map(
                fn (array $macro): HostMacro => $this->macro($macro[0], $macro[1], $macro[2], $macro[3]),
                $scenario['before'] ?? [],
            ),
            $scenario['community'] ?? null,
        );

        $result = $this->patch($scenario['changes']);

        $after = [];
        foreach ($result->checkOptions->macros as $macro) {
            $after[$macro->name->value] = [$macro->value, $macro->isPassword];
        }
        self::assertEquals($scenario['after'], $after, 'the macros of the host');
        self::assertSame(
            $scenario['vaultCalls'] ?? [],
            array_map(
                static fn (array $call): array => ['secrets' => $call['secrets'], 'deletes' => $call['deletes']],
                $this->vault->writeManyCalls,
            ),
            'what is written to the vault',
        );
        self::assertSame($scenario['macrosWritten'] ?? false, $this->repository->macrosReplacedHosts !== [], 'the macros are written to the database');
        self::assertSame($scenario['massChanged'] ?? ($scenario['macrosWritten'] ?? false), $this->eventBus->getDispatchedEvents(HostMassChanged::class) !== [], 'the change is notified');
        self::assertSame($scenario['purged'] ?? false, $this->eventBus->getDispatchedEvents(HostVaultPurgeRequested::class) !== [], 'the vault entry is purged');
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function macroScenarios(): iterable
    {
        $reference = static fn (string $name, string $uuid = 'uuid-1'): string => 'secret::vault::monitoring/hosts/' . $uuid . '::_HOST' . $name;
        $change = static fn (string $name, ?string $value, bool $isPassword = false, ?int $id = null): HostMacroChange => new HostMacroChange(
            new HostMacroName($name),
            $value,
            $isPassword,
            $id === null ? null : new HostMacroId($id),
        );

        // --- replace: the macros given are the macros of the host
        yield 'replace: a value changes' => [[
            'before' => [['PORT', '80', false, 10]],
            'changes' => MacroChanges::replace([$change('PORT', '8080', false, 10)]),
            'after' => ['PORT' => ['8080', false]],
            'macrosWritten' => true,
        ]];

        yield 'replace: the same macros change nothing' => [[
            'before' => [['PORT', '80', false, 10]],
            'changes' => MacroChanges::replace([$change('PORT', '80', false, 10)]),
            'after' => ['PORT' => ['80', false]],
        ]];

        yield 'replace: a plain macro is added, the vault is left alone' => [[
            'changes' => MacroChanges::replace([$change('ADDED', 'y')]),
            'after' => ['ADDED' => ['y', false]],
            'macrosWritten' => true,
        ]];

        yield 'replace: a password is added and goes to the vault' => [[
            'changes' => MacroChanges::replace([$change('TOKEN', 'secret', true)]),
            'after' => ['TOKEN' => [$reference('TOKEN', 'new-uuid'), true]],
            'vaultCalls' => [['secrets' => ['_HOSTTOKEN' => 'secret'], 'deletes' => []]],
            'macrosWritten' => true,
        ]];

        yield 'replace: a password is added without a vault and stays as it is' => [[
            'vault' => false,
            'changes' => MacroChanges::replace([$change('TOKEN', 'secret', true)]),
            'after' => ['TOKEN' => ['secret', true]],
            'macrosWritten' => true,
        ]];

        yield 'replace: a password without a value is kept' => [[
            'before' => [['TOKEN', $reference('TOKEN'), true, 10]],
            'changes' => MacroChanges::replace([$change('TOKEN', null, true, 10)]),
            'after' => ['TOKEN' => [$reference('TOKEN'), true]],
        ]];

        yield 'replace: a password without a value is kept without a vault' => [[
            'vault' => false,
            'before' => [['TOKEN', 'plain-secret', true, 10]],
            'changes' => MacroChanges::replace([$change('TOKEN', null, true, 10)]),
            'after' => ['TOKEN' => ['plain-secret', true]],
        ]];

        yield 'replace: a new password value is written under the same reference' => [[
            'before' => [['TOKEN', $reference('TOKEN'), true, 10]],
            'changes' => MacroChanges::replace([$change('TOKEN', 'new-secret', true, 10)]),
            'after' => ['TOKEN' => [$reference('TOKEN'), true]],
            'vaultCalls' => [['secrets' => ['_HOSTTOKEN' => 'new-secret'], 'deletes' => []]],
            'massChanged' => true,
        ]];

        yield 'replace: a macro that becomes a password goes to the vault' => [[
            'before' => [['TOKEN', 'plain', false, 10]],
            'changes' => MacroChanges::replace([$change('TOKEN', 'plain', true, 10)]),
            'after' => ['TOKEN' => [$reference('TOKEN', 'new-uuid'), true]],
            'vaultCalls' => [['secrets' => ['_HOSTTOKEN' => 'plain'], 'deletes' => []]],
            'macrosWritten' => true,
        ]];

        yield 'replace: the last password left out empties and releases the entry' => [[
            'before' => [['TOKEN', $reference('TOKEN'), true, 10]],
            'changes' => MacroChanges::replace([]),
            'after' => [],
            'vaultCalls' => [['secrets' => [], 'deletes' => ['_HOSTTOKEN']]],
            'macrosWritten' => true,
            'purged' => true,
        ]];

        yield 'replace: a password left out while another stays only loses its key' => [[
            'before' => [['TOKEN', $reference('TOKEN'), true, 10], ['OTHER', $reference('OTHER'), true, 11]],
            'changes' => MacroChanges::replace([$change('OTHER', null, true, 11)]),
            'after' => ['OTHER' => [$reference('OTHER'), true]],
            'vaultCalls' => [['secrets' => [], 'deletes' => ['_HOSTTOKEN']]],
            'macrosWritten' => true,
        ]];

        yield 'replace: a renamed password keeps its reference' => [[
            'before' => [['TOKEN', $reference('TOKEN'), true, 10]],
            'changes' => MacroChanges::replace([$change('RENAMED', null, true, 10)]),
            'after' => ['RENAMED' => [$reference('TOKEN'), true]],
            'macrosWritten' => true,
        ]];

        yield 'replace: an empty password is stored as it is' => [[
            'changes' => MacroChanges::replace([$change('TOKEN', '', true)]),
            'after' => ['TOKEN' => ['', true]],
            'macrosWritten' => true,
        ]];

        // --- upsert: the macros given are written, the other ones are kept
        yield 'upsert: the other macros are kept' => [[
            'before' => [['A', '1', false, 10], ['B', '2', false, 11]],
            'changes' => MacroChanges::upsert([$change('A', '9', false, 10)]),
            'after' => ['A' => ['9', false], 'B' => ['2', false]],
            'macrosWritten' => true,
        ]];

        yield 'upsert: a macro with a new name is added' => [[
            'before' => [['A', '1', false, 10]],
            'changes' => MacroChanges::upsert([$change('B', '2')]),
            'after' => ['A' => ['1', false], 'B' => ['2', false]],
            'macrosWritten' => true,
        ]];

        yield 'upsert: a password gets its new value under the same reference' => [[
            'before' => [['TOKEN', $reference('TOKEN'), true, 10]],
            'changes' => MacroChanges::upsert([$change('TOKEN', 'new-secret', true, 10)]),
            'after' => ['TOKEN' => [$reference('TOKEN'), true]],
            'vaultCalls' => [['secrets' => ['_HOSTTOKEN' => 'new-secret'], 'deletes' => []]],
            'massChanged' => true,
        ]];

        yield 'upsert: a new password joins the entry the host already has' => [[
            'before' => [['TOKEN', $reference('TOKEN'), true, 10]],
            'changes' => MacroChanges::upsert([$change('OTHER', 'secret', true)]),
            'after' => ['TOKEN' => [$reference('TOKEN'), true], 'OTHER' => [$reference('OTHER'), true]],
            'vaultCalls' => [['secrets' => ['_HOSTOTHER' => 'secret'], 'deletes' => []]],
            'macrosWritten' => true,
        ]];

        yield 'upsert: a plain macro leaves the passwords and the vault alone' => [[
            'before' => [['TOKEN', $reference('TOKEN'), true, 10]],
            'changes' => MacroChanges::upsert([$change('PORT', '80')]),
            'after' => ['TOKEN' => [$reference('TOKEN'), true], 'PORT' => ['80', false]],
            'macrosWritten' => true,
        ]];

        // --- remove: the macros given, by id, are removed
        yield 'remove: a plain macro' => [[
            'before' => [['A', '1', false, 10], ['B', '2', false, 11]],
            'changes' => MacroChanges::remove([new HostMacroId(10)]),
            'after' => ['B' => ['2', false]],
            'macrosWritten' => true,
        ]];

        yield 'remove: the only password releases the entry' => [[
            'before' => [['TOKEN', $reference('TOKEN'), true, 10]],
            'changes' => MacroChanges::remove([new HostMacroId(10)]),
            'after' => [],
            'vaultCalls' => [['secrets' => [], 'deletes' => ['_HOSTTOKEN']]],
            'macrosWritten' => true,
            'purged' => true,
        ]];

        yield 'remove: one password of two only loses its key' => [[
            'before' => [['TOKEN', $reference('TOKEN'), true, 10], ['OTHER', $reference('OTHER'), true, 11]],
            'changes' => MacroChanges::remove([new HostMacroId(10)]),
            'after' => ['OTHER' => [$reference('OTHER'), true]],
            'vaultCalls' => [['secrets' => [], 'deletes' => ['_HOSTTOKEN']]],
            'macrosWritten' => true,
        ]];

        yield 'remove: a plain macro leaves the vault alone' => [[
            'before' => [['TOKEN', $reference('TOKEN'), true, 10], ['PORT', '80', false, 11]],
            'changes' => MacroChanges::remove([new HostMacroId(11)]),
            'after' => ['TOKEN' => [$reference('TOKEN'), true]],
            'macrosWritten' => true,
        ]];

        yield 'remove: a password macro without a vault' => [[
            'vault' => false,
            'before' => [['TOKEN', 'plain-secret', true, 10]],
            'changes' => MacroChanges::remove([new HostMacroId(10)]),
            'after' => [],
            'macrosWritten' => true,
        ]];

        yield 'remove: the last password keeps the entry the community still uses' => [[
            'community' => 'secret::vault::monitoring/hosts/uuid-1::_HOSTSNMPCOMMUNITY',
            'before' => [['TOKEN', $reference('TOKEN'), true, 10]],
            'changes' => MacroChanges::remove([new HostMacroId(10)]),
            'after' => [],
            'vaultCalls' => [['secrets' => [], 'deletes' => ['_HOSTTOKEN']]],
            'macrosWritten' => true,
        ]];
    }

    public function testARenameOntoTheNameOfAnotherMacroIsRefusedAndNothingIsLost(): void
    {
        $this->seedHost([$this->macro('A', '1', id: 10), $this->macro('B', '2', id: 11)]);

        try {
            $this->patch(MacroChanges::upsert([new HostMacroChange(new HostMacroName('B'), '9', false, new HostMacroId(10))]));
            self::fail('The rename onto an existing name should have been refused.');
        } catch (DuplicateHostMacroNameException) {
            self::assertSame([], $this->repository->macrosReplacedHosts);
            self::assertSame([], $this->repository->updatedHosts);
        }
    }

    public function testAnInheritedMacroRenamedOntoTheNameOfAnotherOwnMacroIsRefusedAndNothingIsLost(): void
    {
        $this->templateRepository->hostTemplates[1] = new HostTemplate(
            new HostTemplateId(1),
            new HostTemplateName('template-1'),
            new Collection([$this->macro('PORT', '80', false, 500)], HostMacro::class),
        );
        $this->repository->seed(new Host(
            id: null,
            name: new HostName('server-01'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([new HostTemplateId(1)], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            checkOptions: new CheckOptions(null, macros: [$this->macro('OTHER', 'x', false, 10)]),
        ), self::HOST_ID);

        try {
            $this->patch(MacroChanges::upsert([new HostMacroChange(new HostMacroName('OTHER'), '90', false, new HostMacroId(500), HostMacroParentEnum::Template)]));
            self::fail('The rename onto an existing name should have been refused.');
        } catch (DuplicateHostMacroNameException) {
            self::assertSame([], $this->repository->macrosReplacedHosts);
        }
    }

    public function testAPasswordCannotStopBeingOneAndNothingIsWritten(): void
    {
        $reference = 'secret::vault::monitoring/hosts/uuid-1::_HOSTTOKEN';
        $this->seedHost([$this->macro('TOKEN', $reference, true, 10)]);

        foreach ([MacroChanges::replace(...), MacroChanges::upsert(...)] as $mode) {
            try {
                $this->patch($mode([new HostMacroChange(new HostMacroName('TOKEN'), 'plain', false, new HostMacroId(10))]));
                self::fail('Turning a password into a plain macro should have been refused.');
            } catch (HostMacroPasswordUnmaskedException) {
                self::assertSame([], $this->repository->macrosReplacedHosts);
                self::assertSame([], $this->vault->writeManyCalls);
            }
        }
    }

    public function testACheckCommandMacroTheHostAlreadyOverridesIsReplacedWhateverIdIsSent(): void
    {
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
        $this->repository->seed(new Host(
            id: null,
            name: new HostName('server-01'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            checkOptions: new CheckOptions(new CommandId(9), macros: [$this->macro('FROMCOMMAND', 'old', false, 10)]),
        ), self::HOST_ID);

        $host = $this->patch(MacroChanges::upsert([
            new HostMacroChange(new HostMacroName('FROMCOMMAND'), 'new', false, new HostMacroId(999), HostMacroParentEnum::Command),
        ]));

        self::assertCount(1, $host->checkOptions->macros);
        self::assertSame('new', $host->checkOptions->macros[0]->value);
    }

    #[DataProvider('macrosWithTheSameName')]
    public function testTwoMacrosOfTheSameNameAreRefused(MacroChanges $changes): void
    {
        $this->seedHost([$this->macro('A', '1', id: 10), $this->macro('B', '2', id: 11)]);

        $this->expectException(DuplicateHostMacroNameException::class);

        $this->patch($changes);
    }

    /**
     * @return iterable<string, array{MacroChanges}>
     */
    public static function macrosWithTheSameName(): iterable
    {
        yield 'a replacement with two macros of the same name' => [MacroChanges::replace([
            new HostMacroChange(new HostMacroName('B'), '9', false, new HostMacroId(10)),
            new HostMacroChange(new HostMacroName('B'), '8', false, new HostMacroId(11)),
        ])];

        yield 'a replacement renaming a macro onto another one kept as it is' => [MacroChanges::replace([
            new HostMacroChange(new HostMacroName('B'), '9', false, new HostMacroId(10)),
            new HostMacroChange(new HostMacroName('B'), '2', false, new HostMacroId(11)),
        ])];

        yield 'an upsert with two new macros of the same name' => [MacroChanges::upsert([
            new HostMacroChange(new HostMacroName('NEW'), '1', false),
            new HostMacroChange(new HostMacroName('NEW'), '2', false),
        ])];

        yield 'an upsert without id of a name the host already has' => [MacroChanges::upsert([
            new HostMacroChange(new HostMacroName('A'), '9', false),
        ])];

        yield 'an upsert with a new macro and a rename onto its name' => [MacroChanges::upsert([
            new HostMacroChange(new HostMacroName('NEW'), '1', false),
            new HostMacroChange(new HostMacroName('NEW'), '2', false, new HostMacroId(10)),
        ])];
    }

    /**
     * @param array{
     *     templates?: array<int, list<array{string, string, bool, int}>>,
     *     hostTemplates?: list<int>,
     *     before?: list<array{string, string, bool, int}>,
     *     templateChange?: ListChange<HostTemplateId>,
     *     changes: MacroChanges,
     *     after: array<string, array{string, bool}>,
     *     vaultSecrets?: array<string, string>,
     *     vaultCalls?: list<array{secrets: array<string, string>, deletes: list<string>}>,
     *     macrosWritten?: bool,
     * } $scenario every macro is [name, value, isPassword, id], and every macro of the result [value, isPassword]
     */
    #[DataProvider('inheritanceScenarios')]
    public function testTheMacrosTheHostInheritsAreResolvedAgainstTheHostAfterTheUpdate(array $scenario): void
    {
        $this->vault->vaultEnabled = isset($scenario['vaultSecrets']);
        $this->vault->resolved = $scenario['vaultSecrets'] ?? [];
        foreach ($scenario['templates'] ?? [] as $templateId => $macros) {
            $this->templateRepository->hostTemplates[$templateId] = new HostTemplate(
                new HostTemplateId($templateId),
                new HostTemplateName('template-' . $templateId),
                new Collection(
                    array_map(fn (array $macro): HostMacro => $this->macro($macro[0], $macro[1], $macro[2], $macro[3]), $macros),
                    HostMacro::class,
                ),
            );
        }

        $this->repository->seed(new Host(
            id: null,
            name: new HostName('server-01'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection(array_map(static fn (int $id): HostTemplateId => new HostTemplateId($id), $scenario['hostTemplates'] ?? []), HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            checkOptions: new CheckOptions(null, macros: array_map(fn (array $macro): HostMacro => $this->macro($macro[0], $macro[1], $macro[2], $macro[3]), $scenario['before'] ?? [])),
        ), self::HOST_ID);

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            actor: TestActingUsers::unrestricted(1),
            templateIds: $scenario['templateChange'] ?? new NoValue(),
            macros: $scenario['changes'],
        ));

        $after = [];
        foreach ($result->checkOptions->macros as $macro) {
            $after[$macro->name->value] = [$macro->value, $macro->isPassword];
        }
        self::assertEquals($scenario['after'], $after, 'the macros of the host');
        self::assertSame($scenario['macrosWritten'] ?? false, $this->repository->macrosReplacedHosts !== [], 'the macros are written to the database');
        self::assertSame(
            $scenario['vaultCalls'] ?? [],
            array_map(
                static fn (array $call): array => ['secrets' => $call['secrets'], 'deletes' => $call['deletes']],
                $this->vault->writeManyCalls,
            ),
            'what is written to the vault',
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function inheritanceScenarios(): iterable
    {
        $port = ['PORT', '80', false, 500];
        $change = static fn (string $name, ?string $value, bool $isPassword = false, ?int $id = null, ?HostMacroParentEnum $parent = null): HostMacroChange => new HostMacroChange(
            new HostMacroName($name),
            $value,
            $isPassword,
            $id === null ? null : new HostMacroId($id),
            $parent,
        );

        yield 'a macro equal to the inherited one is not copied' => [[
            'templates' => [1 => [$port]],
            'hostTemplates' => [1],
            'changes' => MacroChanges::upsert([$change('PORT', '80')]),
            'after' => [],
        ]];

        yield 'a macro different from the inherited one overrides it' => [[
            'templates' => [1 => [$port]],
            'hostTemplates' => [1],
            'changes' => MacroChanges::upsert([$change('PORT', '90')]),
            'after' => ['PORT' => ['90', false]],
            'macrosWritten' => true,
        ]];

        yield 'an inherited macro overridden by its id and parent' => [[
            'templates' => [1 => [$port]],
            'hostTemplates' => [1],
            'changes' => MacroChanges::upsert([$change('PORT', '90', false, 500, HostMacroParentEnum::Template)]),
            'after' => ['PORT' => ['90', false]],
            'macrosWritten' => true,
        ]];

        yield 'an inherited macro the host already overrides is overridden again by its id and parent' => [[
            'templates' => [1 => [$port]],
            'hostTemplates' => [1],
            'before' => [['PORT', '90', false, 10]],
            'changes' => MacroChanges::upsert([$change('PORT', '95', false, 500, HostMacroParentEnum::Template)]),
            'after' => ['PORT' => ['95', false]],
            'macrosWritten' => true,
        ]];

        yield 'an override set back to the inherited value is removed' => [[
            'templates' => [1 => [$port]],
            'hostTemplates' => [1],
            'before' => [['PORT', '90', false, 10]],
            'changes' => MacroChanges::upsert([$change('PORT', '80', false, 10)]),
            'after' => [],
            'macrosWritten' => true,
        ]];

        yield 'a replacement that gives back the inherited macros as they were read copies nothing' => [[
            'templates' => [1 => [$port]],
            'hostTemplates' => [1],
            'before' => [['OWN', 'x', false, 10]],
            'changes' => MacroChanges::replace([
                $change('OWN', 'x', false, 10),
                $change('PORT', '80', false, 500, HostMacroParentEnum::Template),
            ]),
            'after' => ['OWN' => ['x', false]],
        ]];

        yield 'a macro equal to the one of a template added in the same update is not copied' => [[
            'templates' => [1 => [$port], 2 => [['NEWONE', '1', false, 501]]],
            'hostTemplates' => [1],
            'templateChange' => ListChange::add([new HostTemplateId(2)]),
            'changes' => MacroChanges::upsert([$change('NEWONE', '1')]),
            'after' => [],
        ]];

        yield 'a macro equal to the one of a template removed in the same update is kept' => [[
            'templates' => [1 => [$port]],
            'hostTemplates' => [1],
            'templateChange' => ListChange::remove([new HostTemplateId(1)]),
            'changes' => MacroChanges::upsert([$change('PORT', '80')]),
            'after' => ['PORT' => ['80', false]],
            'macrosWritten' => true,
        ]];

        yield 'a macro already equal to the inherited one is left alone by an update of another macro' => [[
            'templates' => [1 => [$port]],
            'hostTemplates' => [1],
            'before' => [['PORT', '80', false, 10]],
            'changes' => MacroChanges::upsert([$change('OTHER', 'x')]),
            'after' => ['PORT' => ['80', false], 'OTHER' => ['x', false]],
            'macrosWritten' => true,
        ]];

        $reference = 'secret::vault::monitoring/hosts/template-uuid::_HOSTSECRET';

        yield 'an inherited password taken over under another name is copied under the entry of the host' => [[
            'vaultSecrets' => [$reference => 'the-template-secret'],
            'templates' => [1 => [['SECRET', $reference, true, 600]]],
            'hostTemplates' => [1],
            'changes' => MacroChanges::upsert([$change('COPY', null, true, 600, HostMacroParentEnum::Template)]),
            'after' => ['COPY' => ['secret::vault::monitoring/hosts/new-uuid::_HOSTCOPY', true]],
            'vaultCalls' => [['secrets' => ['_HOSTCOPY' => 'the-template-secret'], 'deletes' => []]],
            'macrosWritten' => true,
        ]];

        yield 'an inherited password given back unchanged is not copied' => [[
            'vaultSecrets' => [$reference => 'the-template-secret'],
            'templates' => [1 => [['SECRET', $reference, true, 600]]],
            'hostTemplates' => [1],
            'changes' => MacroChanges::replace([$change('SECRET', null, true, 600, HostMacroParentEnum::Template)]),
            'after' => [],
        ]];

        yield 'an inherited password overridden with a new secret goes to the entry of the host' => [[
            'vaultSecrets' => [$reference => 'the-template-secret'],
            'templates' => [1 => [['SECRET', $reference, true, 600]]],
            'hostTemplates' => [1],
            'changes' => MacroChanges::upsert([$change('SECRET', 'my-own-secret', true, 600, HostMacroParentEnum::Template)]),
            'after' => ['SECRET' => ['secret::vault::monitoring/hosts/new-uuid::_HOSTSECRET', true]],
            'vaultCalls' => [['secrets' => ['_HOSTSECRET' => 'my-own-secret'], 'deletes' => []]],
            'macrosWritten' => true,
        ]];
    }

    public function testAVaultFailureOnAPasswordSavesNothing(): void
    {
        $this->vault->vaultEnabled = true;
        $this->vault->writeThrows = true;
        $this->seedHost();

        try {
            $this->patch(MacroChanges::upsert([new HostMacroChange(new HostMacroName('TOKEN'), 'secret', true)]));
            self::fail('The vault failure should have been reported.');
        } catch (VaultWriteFailedException) {
            self::assertSame([], $this->repository->updatedHosts);
            self::assertSame([], $this->repository->macrosReplacedHosts);
            // Nothing was written before the failure, so there is no divergence to report.
            self::assertSame([], $this->logger->warningMessages);
        }
    }

    public function testAVaultFailureInTheMiddleOfTheSecretsSavesNothing(): void
    {
        $this->vault->vaultEnabled = true;
        $this->vault->writeThrowsOnCall = 2;
        $this->seedHost();

        $this->expectException(VaultWriteFailedException::class);

        try {
            ($this->handler)(new PatchHostCommand(
                id: new HostId(self::HOST_ID),
                actor: TestActingUsers::unrestricted(1),
                snmpCommunity: 'a-community',
                macros: MacroChanges::upsert([new HostMacroChange(new HostMacroName('TOKEN'), 'secret', true)]),
            ));
        } finally {
            self::assertCount(2, $this->vault->writeManyCalls);
            self::assertSame([], $this->repository->updatedHosts);
        }
    }

    public function testAHostThatCannotBeSavedAfterTheVaultWasWrittenIsReportedAsADivergence(): void
    {
        $this->vault->vaultEnabled = true;
        $this->seedHost();
        $this->repository->updateThrows = new \RuntimeException('database down');

        try {
            $this->patch(MacroChanges::upsert([new HostMacroChange(new HostMacroName('TOKEN'), 'secret', true)]));
            self::fail('The failure of the database should have been reported.');
        } catch (\RuntimeException) {
            self::assertCount(1, $this->vault->writeManyCalls);
            self::assertCount(1, $this->logger->warningMessages);
            self::assertStringContainsString('may now diverge', $this->logger->warningMessages[0]['message']);
        }
    }

    public function testAHostThatCannotBeSavedWithoutTouchingTheVaultIsNotReportedAsADivergence(): void
    {
        $this->vault->vaultEnabled = true;
        $this->seedHost();
        $this->repository->updateThrows = new \RuntimeException('database down');

        try {
            $this->patch(MacroChanges::upsert([new HostMacroChange(new HostMacroName('PORT'), '80', false)]));
            self::fail('The failure of the database should have been reported.');
        } catch (\RuntimeException) {
            self::assertSame([], $this->vault->writeManyCalls);
            self::assertSame([], $this->logger->warningMessages);
        }
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
            actor: TestActingUsers::unrestricted(1),
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

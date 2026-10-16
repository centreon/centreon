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

use App\MonitoringConfiguration\Application\Command\DuplicateHostCommand;
use App\MonitoringConfiguration\Application\Command\DuplicateHostCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Event\HostDuplicated;
use App\MonitoringConfiguration\Domain\Event\HostServicesDuplicationRequested;
use App\MonitoringConfiguration\Domain\Exception\DuplicatedHostNameTooLongException;
use App\MonitoringConfiguration\Domain\Exception\HostAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Application\Vault\VaultCredentialReader;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Vault\VaultPathEnum;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Shared\Double\EventBusSpy;
use Tests\App\Shared\Double\FakeVault;
use Tests\App\Shared\Infrastructure\Legacy\Double\RecordingLogger;

final class DuplicateHostCommandHandlerTest extends TestCase
{
    private FakeHostRepository $repository;

    private FakeResourceAccessRepository $resourceAccessRepository;

    private EventBusSpy $eventBus;

    private FakeVault $vault;

    private RecordingLogger $logger;

    private DuplicateHostCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new FakeHostRepository();
        $this->resourceAccessRepository = new FakeResourceAccessRepository();
        $this->eventBus = new EventBusSpy();
        $this->vault = new FakeVault();
        // Off by default: the secrets then copy verbatim, exercised by the relation-copying tests; the
        // vault-specific tests turn it on and drive the reader/writer through the fake.
        $this->vault->vaultEnabled = false;

        $this->logger = new RecordingLogger();

        $this->handler = new DuplicateHostCommandHandler(
            $this->repository,
            $this->resourceAccessRepository,
            $this->vault,
            new VaultCredentialReader($this->vault),
            new VaultCredentialWriter($this->vault),
            $this->eventBus,
            $this->logger,
        );
    }

    public function testDuplicatesHostWithFirstFreeSuffixAndCopiesEveryRelation(): void
    {
        $source = $this->storeSourceHost(1, 'web');

        ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: new UserId(42), viewerId: null));

        $copy = $this->findCopyByName('web_1');
        self::assertNotNull($copy, 'the copy is persisted under the first free "_1" suffix');
        self::assertNotSame($source->id()->value, $copy->id()->value);
        self::assertSame($source->alias?->value, $copy->alias?->value);
        self::assertSame($source->address->value, $copy->address->value);
        self::assertSame($source->activated, $copy->activated);
        self::assertSame($source->pollerId->value, $copy->pollerId->value);
        self::assertSame($this->idValues($source->templateIds), $this->idValues($copy->templateIds));
        self::assertSame($this->idValues($source->hostGroupIds), $this->idValues($copy->hostGroupIds));
        self::assertSame($this->idValues($source->categoryIds), $this->idValues($copy->categoryIds));
        self::assertSame($this->idValues($source->parentHostIds), $this->idValues($copy->parentHostIds));
        self::assertSame($this->idValues($source->childHostIds), $this->idValues($copy->childHostIds));
        // The remaining fields are forwarded straight from the source aggregate; identity assertions
        // catch a field accidentally dropped or crossed in the copy constructor (it would become a
        // fresh default instead of the source's value).
        self::assertSame($source->snmpVersion, $copy->snmpVersion);
        self::assertSame($source->snmpCommunity, $copy->snmpCommunity);
        self::assertSame($source->timezoneId, $copy->timezoneId);
        self::assertSame($source->severityId, $copy->severityId);
        self::assertSame($source->extendedInformations, $copy->extendedInformations);
        self::assertSame($source->schedulingOptions, $copy->schedulingOptions);
        self::assertSame($source->dataProcessing, $copy->dataProcessing);
        // Equal content, but a fresh instance: the copy's macros are rebuilt as its own rows.
        self::assertEquals($source->checkOptions, $copy->checkOptions);
    }

    public function testThrowsUnprocessableWhenTheSuffixWouldExceedTheNameLengthLimit(): void
    {
        // A source name already at the maximum length leaves no room for the "_<n>" suffix, so no
        // candidate is valid — a validation failure on the generated name (422), not an
        // already-taken conflict (409) nor an unmapped 500 from HostName.
        $this->storeSourceHost(1, str_repeat('a', HostName::MAX_LENGTH));

        $this->expectException(DuplicatedHostNameTooLongException::class);

        ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: new UserId(42), viewerId: null));
    }

    public function testSkipsAlreadyTakenSuffixes(): void
    {
        $this->storeSourceHost(1, 'web');
        $this->storeSourceHost(2, 'web_1');

        ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: new UserId(42), viewerId: null));

        self::assertNotNull($this->findCopyByName('web_2'), 'the next free suffix is used instead');
        self::assertCount(3, $this->repository->hosts, 'no extra copy is created for the taken suffix');
    }

    public function testThrowsNotFoundWhenSourceDoesNotExist(): void
    {
        $this->expectException(HostNotFoundException::class);

        ($this->handler)(new DuplicateHostCommand(new HostId(999), duplicatedBy: new UserId(42), viewerId: null));
    }

    public function testThrowsConflictWhenEveryCandidateNameIsTaken(): void
    {
        $this->storeSourceHost(1, 'web');
        $this->repository->forceNameUsed = true;

        $this->expectException(HostAlreadyExistsException::class);

        ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: new UserId(42), viewerId: null));
    }

    public function testCopiesTheSourceAclScopeOntoTheCopy(): void
    {
        $this->storeSourceHost(1, 'web');

        ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: new UserId(42), viewerId: null));

        $copy = $this->findCopyByName('web_1');
        self::assertNotNull($copy);
        self::assertSame(
            [['sourceHostId' => 1, 'newHostId' => $copy->id()->value]],
            $this->resourceAccessRepository->duplicatedHostAccess,
        );
    }

    public function testFiresHostDuplicatedOnceForLogAndPollerFlag(): void
    {
        $this->storeSourceHost(1, 'web');

        ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: new UserId(42), viewerId: null));

        self::assertTrue($this->eventBus->shouldHaveDispatched(HostDuplicated::class, 1));
        $event = $this->eventBus->getDispatchedEvents(HostDuplicated::class)[0];
        self::assertSame(42, $event->creatorId);

        // The event must carry the copy, not the source: the shared LogActivityEventHandler logs it as
        // an Add, so a source/copy mix-up would mislabel the action log and cannot be caught elsewhere.
        $copy = $this->findCopyByName('web_1');
        self::assertNotNull($copy);
        self::assertInstanceOf(Host::class, $event->aggregate);
        self::assertSame($copy->id()->value, $event->aggregate->id()->value);
    }

    public function testRequestsServiceDuplicationFromTheSourceOntoTheCopy(): void
    {
        $this->storeSourceHost(1, 'web');

        ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: new UserId(42), viewerId: null));

        self::assertTrue($this->eventBus->shouldHaveDispatched(HostServicesDuplicationRequested::class, 1));
        $event = $this->eventBus->getDispatchedEvents(HostServicesDuplicationRequested::class)[0];
        self::assertSame(1, $event->sourceHostId->value);
        // The actor is threaded onto the event so the deferred clone can rebuild the legacy session.
        self::assertSame(42, $event->duplicatedBy->value);

        $copy = $this->findCopyByName('web_1');
        self::assertNotNull($copy);
        self::assertSame($copy->id()->value, $event->newHostId->value);
    }

    public function testRemintsVaultedSecretsIntoAFreshVaultEntry(): void
    {
        $this->vault->vaultEnabled = true;

        $snmpReference = 'secret::vault::monitoring/hosts/src-uuid::_HOSTSNMPCOMMUNITY';
        $macroReference = 'secret::vault::monitoring/hosts/src-uuid::_HOSTPASSWORD';
        // getVaultUuid resolves the source's entry from its SNMP community reference.
        $this->vault->extractedUuids[$snmpReference] = 'src-uuid';
        // resolveAll reads each reference back to plaintext before it is rewritten.
        $this->vault->resolved[$snmpReference] = 'public';
        $this->vault->resolved[$macroReference] = 's3cr3t';
        $this->repository->hosts[1] = $this->buildVaultedHost(1, 'web', $snmpReference, $macroReference);

        ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: new UserId(42), viewerId: null));

        // The source's secrets are read back to plaintext and rewritten under a single fresh entry
        // (null UUID), never the source's — so the copy cannot share the source's vault paths.
        self::assertCount(1, $this->vault->writeManyCalls);
        $write = $this->vault->writeManyCalls[0];
        self::assertSame(VaultPathEnum::MonitoringHosts->value, $write['customPath']);
        self::assertNull($write['uuid'], 'a fresh vault entry is minted, not the source one');
        self::assertSame(
            ['_HOSTSNMPCOMMUNITY' => 'public', '_HOSTPASSWORD' => 's3cr3t'],
            $write['secrets'],
        );

        // The copy carries the fresh references, not the source's vault paths.
        $copy = $this->findCopyByName('web_1');
        self::assertNotNull($copy);
        self::assertNotNull($copy->snmpCommunity);
        self::assertNotSame($snmpReference, $copy->snmpCommunity->value);
        self::assertStringStartsWith('secret::', $copy->snmpCommunity->value);

        $macro = $copy->checkOptions->macros[0];
        self::assertNotSame($macroReference, $macro->value);
        self::assertStringStartsWith('secret::', $macro->value);
    }

    public function testDoesNotTouchTheVaultWhenNoSecretIsVaulted(): void
    {
        // Vault enabled but the source stores plaintext (e.g. the vault was turned on after it was
        // created): there is no entry to re-mint, so the values copy verbatim and the vault is untouched.
        $this->vault->vaultEnabled = true;
        $source = $this->storeSourceHost(1, 'web');

        ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: new UserId(42), viewerId: null));

        self::assertSame([], $this->vault->writeManyCalls);
        $copy = $this->findCopyByName('web_1');
        self::assertNotNull($copy);
        self::assertSame($source->snmpCommunity, $copy->snmpCommunity);
        // Equal content, but a fresh instance: the copy's macros are rebuilt as its own rows.
        self::assertEquals($source->checkOptions, $copy->checkOptions);
    }

    public function testRemintsOnlyTheVaultedSecretsAndKeepsPlaintextVerbatim(): void
    {
        $this->vault->vaultEnabled = true;

        // SNMP community is plaintext; the source's vault entry is reached through the vaulted macro.
        $macroReference = 'secret::vault::monitoring/hosts/src-uuid::_HOSTTOKEN';
        $this->vault->extractedUuids[$macroReference] = 'src-uuid';
        $this->vault->resolved[$macroReference] = 's3cr3t';
        $this->repository->hosts[1] = new Host(
            id: new HostId(1),
            name: new HostName('web'),
            alias: new HostAlias('alias-1'),
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            snmpVersion: SnmpVersionEnum::TwoC,
            snmpCommunity: new SnmpCommunity('public'),
            checkOptions: new CheckOptions(null, [], [
                // Both carry a source id, to prove neither branch (re-minted or verbatim) copies it over.
                new HostMacro(new HostMacroName('token'), $macroReference, true, new HostMacroId(7)),
                new HostMacro(new HostMacroName('plain'), 'visible-value', false, new HostMacroId(42)),
            ]),
        );

        ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: new UserId(42), viewerId: null));

        // Only the vaulted macro is re-minted: the fresh entry holds just that key.
        self::assertCount(1, $this->vault->writeManyCalls);
        $write = $this->vault->writeManyCalls[0];
        self::assertNull($write['uuid']);
        self::assertSame(['_HOSTTOKEN' => 's3cr3t'], $write['secrets']);

        $copy = $this->findCopyByName('web_1');
        self::assertNotNull($copy);
        // Plaintext SNMP community: absent from the rewrite, copied verbatim.
        self::assertSame('public', $copy->snmpCommunity?->value);

        $macrosByName = [];
        foreach ($copy->checkOptions->macros as $macro) {
            $macrosByName[$macro->name->value] = $macro;
            // The copy owns fresh rows: no source macro id on either branch.
            self::assertNull($macro->id);
        }
        // The vaulted macro gets a fresh reference; the plaintext macro is untouched.
        self::assertNotSame($macroReference, $macrosByName['TOKEN']->value);
        self::assertStringStartsWith('secret::', $macrosByName['TOKEN']->value);
        self::assertSame('visible-value', $macrosByName['PLAIN']->value);
    }

    public function testDoesNotMintAVaultEntryWhenTheNameConflicts(): void
    {
        $this->vault->vaultEnabled = true;
        $snmpReference = 'secret::vault::monitoring/hosts/src-uuid::_HOSTSNMPCOMMUNITY';
        $this->repository->hosts[1] = $this->buildVaultedHost(1, 'web', $snmpReference, 'secret::vault::monitoring/hosts/src-uuid::_HOSTPASSWORD');
        // Every candidate name is taken: the 409 fires during name generation, which now runs before
        // the vault write — so nothing is minted.
        $this->repository->forceNameUsed = true;

        try {
            ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: new UserId(42), viewerId: null));
            self::fail('expected a HostAlreadyExistsException');
        } catch (HostAlreadyExistsException) {
            // expected
        }

        self::assertSame([], $this->vault->writeManyCalls, 'the name is resolved before the vault, so a 409 mints nothing');
        self::assertSame([], $this->vault->deleteCalls);
    }

    public function testPurgesTheMintedVaultEntryWhenPersistenceFails(): void
    {
        $this->vault->vaultEnabled = true;
        $snmpReference = 'secret::vault::monitoring/hosts/src-uuid::_HOSTSNMPCOMMUNITY';
        $macroReference = 'secret::vault::monitoring/hosts/src-uuid::_HOSTPASSWORD';
        $this->vault->extractedUuids[$snmpReference] = 'src-uuid';
        $this->vault->resolved[$snmpReference] = 'public';
        $this->vault->resolved[$macroReference] = 's3cr3t';
        // The copy's freshly-minted SNMP reference (synthetic from FakeVault::writeMany) resolves to its
        // own UUID, distinct from the source's.
        $this->vault->extractedUuids['secret::vault::monitoring/hosts/new-uuid::_HOSTSNMPCOMMUNITY'] = 'new-uuid';
        $this->repository->hosts[1] = $this->buildVaultedHost(1, 'web', $snmpReference, $macroReference);
        // Fail after the vault write, inside the transaction.
        $this->resourceAccessRepository->duplicateHostAccessThrows = true;

        try {
            ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: new UserId(42), viewerId: null));
            self::fail('expected the persistence failure to propagate');
        } catch (\RuntimeException) {
            // expected
        }

        self::assertCount(1, $this->vault->writeManyCalls, 'the copy entry was minted');
        self::assertSame(
            [['customPath' => VaultPathEnum::MonitoringHosts->value, 'uuid' => 'new-uuid']],
            $this->vault->deleteCalls,
            'the minted copy entry is purged on failure, never the source one',
        );
    }

    public function testAVaultPurgeFailureDoesNotMaskThePersistenceError(): void
    {
        $this->vault->vaultEnabled = true;
        $snmpReference = 'secret::vault::monitoring/hosts/src-uuid::_HOSTSNMPCOMMUNITY';
        $macroReference = 'secret::vault::monitoring/hosts/src-uuid::_HOSTPASSWORD';
        $this->vault->extractedUuids[$snmpReference] = 'src-uuid';
        $this->vault->resolved[$snmpReference] = 'public';
        $this->vault->resolved[$macroReference] = 's3cr3t';
        // The copy's fresh entry has a distinct UUID, so the purge guard allows the delete.
        $this->vault->extractedUuids['secret::vault::monitoring/hosts/new-uuid::_HOSTSNMPCOMMUNITY'] = 'new-uuid';
        $this->repository->hosts[1] = $this->buildVaultedHost(1, 'web', $snmpReference, $macroReference);
        // Persistence fails (the real cause) AND the compensating vault purge also fails.
        $this->resourceAccessRepository->duplicateHostAccessThrows = true;
        $this->vault->deleteThrows = true;

        try {
            ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: new UserId(42), viewerId: null));
            self::fail('expected the persistence failure to propagate');
        } catch (\RuntimeException $exception) {
            // The original cause surfaces, not the swallowed purge failure.
            self::assertSame('duplicateHostAccess failed', $exception->getMessage());
        }

        // The swallowed purge failure is still recorded, with the copy's own vault entry, so the orphan
        // it leaves is directly locatable.
        $warning = $this->logger->lastRecord();
        self::assertNotNull($warning);
        self::assertSame('warning', $warning['level']);
        self::assertSame(1, $warning['context']['source_host_id'] ?? null);
        self::assertSame('new-uuid', $warning['context']['copy_vault_uuid'] ?? null);
    }

    public function testDoesNotPurgeTheSourceVaultEntryWhenTheCopySharesItAndPersistenceFails(): void
    {
        // Vault disabled: duplicateSecrets copies the source's `secret::` references verbatim, so the
        // copy carries the SOURCE's vault UUID. A purge on failure must never delete that shared entry.
        $this->vault->vaultEnabled = false;
        $snmpReference = 'secret::vault::monitoring/hosts/src-uuid::_HOSTSNMPCOMMUNITY';
        $this->vault->extractedUuids[$snmpReference] = 'src-uuid';
        $this->repository->hosts[1] = $this->buildVaultedHost(1, 'web', $snmpReference, 'secret::vault::monitoring/hosts/src-uuid::_HOSTPASSWORD');
        $this->resourceAccessRepository->duplicateHostAccessThrows = true;

        try {
            ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: new UserId(42), viewerId: null));
            self::fail('expected the persistence failure to propagate');
        } catch (\RuntimeException) {
            // expected
        }

        self::assertSame([], $this->vault->deleteCalls, 'the source entry shared by the copy is never purged');
    }

    public function testTheCopyMacrosDoNotCarryOverTheSourceMacroIds(): void
    {
        $this->repository->hosts[1] = new Host(
            id: new HostId(1),
            name: new HostName('web'),
            alias: new HostAlias('alias-1'),
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            checkOptions: new CheckOptions(null, [], [
                new HostMacro(new HostMacroName('plain'), 'visible-value', false, new HostMacroId(42)),
            ]),
        );

        ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: new UserId(42), viewerId: null));

        $macro = $this->findCopyByName('web_1')?->checkOptions->macros[0];
        self::assertNotNull($macro);
        // The copy owns a fresh row: same name and value, but no source macro id.
        self::assertSame('PLAIN', $macro->name->value);
        self::assertSame('visible-value', $macro->value);
        self::assertNull($macro->id);
    }

    private function storeSourceHost(int $id, string $name): Host
    {
        $host = $this->buildHost($id, $name);
        $this->repository->hosts[$id] = $host;

        return $host;
    }

    private function buildHost(int $id, string $name): Host
    {
        return new Host(
            id: new HostId($id),
            name: new HostName($name),
            // Derive the alias from the id, not the name: a name at HostName::MAX_LENGTH would push a
            // name-based alias past HostAlias's own length limit.
            alias: new HostAlias('alias-' . $id),
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([new HostTemplateId(5)], HostTemplateId::class),
            hostGroupIds: new Collection([new HostGroupId(3)], HostGroupId::class),
            categoryIds: new Collection([new HostCategoryId(8)], HostCategoryId::class),
            parentHostIds: new Collection([new HostId(10)], HostId::class),
            childHostIds: new Collection([new HostId(11)], HostId::class),
            snmpVersion: SnmpVersionEnum::TwoC,
            snmpCommunity: new SnmpCommunity('public'),
            timezoneId: new TimezoneId(3),
            severityId: new HostSeverityId(4),
            extendedInformations: new ExtendedInformations(note: 'a note'),
            schedulingOptions: new SchedulingOptions(),
            dataProcessing: new DataProcessing(),
            checkOptions: new CheckOptions(null),
        );
    }

    private function buildVaultedHost(int $id, string $name, string $snmpReference, string $macroReference): Host
    {
        return new Host(
            id: new HostId($id),
            name: new HostName($name),
            alias: new HostAlias('alias-' . $id),
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            snmpVersion: SnmpVersionEnum::TwoC,
            snmpCommunity: new SnmpCommunity($snmpReference),
            checkOptions: new CheckOptions(null, [], [
                new HostMacro(new HostMacroName('password'), $macroReference, true),
            ]),
        );
    }

    private function findCopyByName(string $name): ?Host
    {
        foreach ($this->repository->hosts as $host) {
            if ($host->name->value === $name) {
                return $host;
            }
        }

        return null;
    }

    /**
     * @param Collection<HostTemplateId>|Collection<HostGroupId>|Collection<HostCategoryId>|Collection<HostId> $collection
     *
     * @return list<int>
     */
    private function idValues(Collection $collection): array
    {
        return array_values(array_map(
            static fn (HostTemplateId|HostGroupId|HostCategoryId|HostId $id): int => $id->value,
            $collection->toArray(),
        ));
    }
}

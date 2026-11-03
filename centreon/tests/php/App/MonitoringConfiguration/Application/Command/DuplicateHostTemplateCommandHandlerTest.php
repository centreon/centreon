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

use App\MonitoringConfiguration\Application\Command\DuplicateHostTemplateCommand;
use App\MonitoringConfiguration\Application\Command\DuplicateHostTemplateCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Event\HostTemplateDuplicated;
use App\MonitoringConfiguration\Domain\Exception\DuplicatedHostTemplateNameTooLongException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateLockedException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\VaultWriteFailedException;
use App\Shared\Application\Vault\VaultCredentialReader;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;
use Tests\App\Shared\Double\EventBusSpy;
use Tests\App\Shared\Double\FakeVault;
use Tests\App\Shared\Infrastructure\Legacy\Double\RecordingLogger;

final class DuplicateHostTemplateCommandHandlerTest extends TestCase
{
    private const SOURCE_SNMP = 'secret::vault::monitoring/hosts/source-uuid::_HOSTSNMPCOMMUNITY';
    private const SOURCE_PASSWORD = 'secret::vault::monitoring/hosts/source-uuid::_HOSTPWD';

    private FakeHostTemplateRepository $repository;

    private EventBusSpy $eventBus;

    private FakeVault $vault;

    private RecordingLogger $logger;

    private DuplicateHostTemplateCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new FakeHostTemplateRepository();
        $this->eventBus = new EventBusSpy();
        $this->vault = new FakeVault();
        $this->vault->vaultEnabled = false;

        $this->logger = new RecordingLogger();

        $this->handler = new DuplicateHostTemplateCommandHandler(
            $this->repository,
            $this->vault,
            new VaultCredentialReader($this->vault),
            new VaultCredentialWriter($this->vault),
            $this->eventBus,
            $this->logger,
        );
    }

    public function testItThrowsWhenTheTemplateDoesNotExist(): void
    {
        $this->expectException(HostTemplateNotFoundException::class);

        ($this->handler)(new DuplicateHostTemplateCommand(new HostTemplateId(999), null, TestActingUsers::unrestricted()));
    }

    public function testItRefusesALockedTemplate(): void
    {
        $this->seed(7, 'locked', isLocked: true);

        $this->expectException(HostTemplateLockedException::class);

        ($this->handler)(new DuplicateHostTemplateCommand(new HostTemplateId(7), null, TestActingUsers::unrestricted()));
    }

    public function testWithoutANameTheCopyTakesTheFirstFreeSuffix(): void
    {
        $this->seed(7, 'generic');
        $this->repository->hostNames = ['generic_1'];

        ($this->handler)(new DuplicateHostTemplateCommand(new HostTemplateId(7), null, TestActingUsers::unrestricted()));

        $copy = $this->onlyCopy();
        self::assertSame('generic_2', $copy->name->value);
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostTemplateDuplicated::class, 1));
    }

    public function testTheCopyTakesTheGivenName(): void
    {
        $this->seed(7, 'generic');

        ($this->handler)(new DuplicateHostTemplateCommand(new HostTemplateId(7), new HostTemplateName('custom'), TestActingUsers::unrestricted()));

        self::assertSame('custom', $this->onlyCopy()->name->value);
    }

    public function testAGivenNameUsedByAHostIsAConflictAndNothingIsCreated(): void
    {
        $this->seed(7, 'generic');
        $this->repository->hostNames = ['taken'];

        try {
            ($this->handler)(new DuplicateHostTemplateCommand(new HostTemplateId(7), new HostTemplateName('taken'), TestActingUsers::unrestricted()));
            self::fail('A name already used must be refused.');
        } catch (HostTemplateAlreadyExistsException) {
            self::assertSame([], $this->repository->copiedFrom);
        }
    }

    public function testEveryTakenSuffixIsAConflictAndNothingIsCreated(): void
    {
        $this->seed(7, 'generic');
        $this->repository->hostNames = array_map(static fn (int $suffix): string => "generic_{$suffix}", range(1, 999));

        try {
            ($this->handler)(new DuplicateHostTemplateCommand(new HostTemplateId(7), null, TestActingUsers::unrestricted()));
            self::fail('An exhausted suffix range must be a conflict.');
        } catch (HostTemplateAlreadyExistsException $exception) {
            self::assertSame('Every name from "generic_1" to "generic_999" is already used by a host or host template.', $exception->getMessage());
            self::assertSame([], $this->repository->copiedFrom);
        }
    }

    public function testTheLastFreeSuffixIsTaken(): void
    {
        $this->seed(7, 'generic');
        $this->repository->hostNames = array_map(static fn (int $suffix): string => "generic_{$suffix}", range(1, 998));

        ($this->handler)(new DuplicateHostTemplateCommand(new HostTemplateId(7), null, TestActingUsers::unrestricted()));

        self::assertSame('generic_999', $this->onlyCopy()->name->value);
    }

    public function testAGeneratedNameOfExactlyTheMaximumLengthIsAccepted(): void
    {
        $this->seed(7, str_repeat('a', HostTemplateName::MAX_LENGTH - 2));

        ($this->handler)(new DuplicateHostTemplateCommand(new HostTemplateId(7), null, TestActingUsers::unrestricted()));

        self::assertSame(HostTemplateName::MAX_LENGTH, mb_strlen($this->onlyCopy()->name->value));
    }

    public function testAGeneratedNameOverTheLimitIsAValidationFailure(): void
    {
        $this->seed(7, str_repeat('a', HostTemplateName::MAX_LENGTH - 1));

        $this->expectException(DuplicatedHostTemplateNameTooLongException::class);

        ($this->handler)(new DuplicateHostTemplateCommand(new HostTemplateId(7), null, TestActingUsers::unrestricted()));
    }

    public function testTheCopyGetsItsOwnVaultEntry(): void
    {
        $this->seedVaulted();

        ($this->handler)(new DuplicateHostTemplateCommand(new HostTemplateId(7), null, TestActingUsers::unrestricted()));

        self::assertCount(1, $this->vault->writeManyCalls);
        self::assertNull($this->vault->writeManyCalls[0]['uuid']);
        self::assertSame(
            ['_HOSTSNMPCOMMUNITY' => 'community', '_HOSTPWD' => 'secret'],
            $this->vault->writeManyCalls[0]['secrets'],
        );

        $copy = $this->onlyCopy();
        self::assertSame('secret::vault::monitoring/hosts/new-uuid::_HOSTSNMPCOMMUNITY', $copy->snmpCommunity?->value);
        $macros = $copy->macros->toArray();
        self::assertSame('secret::vault::monitoring/hosts/new-uuid::_HOSTPWD', $macros[0]->value);
        self::assertNull($macros[0]->id);
        self::assertSame('plain', $macros[1]->value);
    }

    public function testAFailedCopyPurgesTheEntryMintedForIt(): void
    {
        $this->seedVaulted();
        $this->repository->addCopyThrows = new \RuntimeException('insert failed');

        try {
            ($this->handler)(new DuplicateHostTemplateCommand(new HostTemplateId(7), null, TestActingUsers::unrestricted()));
            self::fail('The insert failure must be rethrown.');
        } catch (\RuntimeException $exception) {
            self::assertSame('insert failed', $exception->getMessage());
        }

        self::assertSame([['customPath' => 'monitoring/hosts', 'uuid' => 'new-uuid']], $this->vault->deleteCalls);
        self::assertSame([], $this->logger->records());
    }

    public function testAFailedPurgeIsLoggedAndTheOriginalCauseRethrown(): void
    {
        $this->seedVaulted();
        $this->repository->addCopyThrows = new \RuntimeException('insert failed');
        $this->vault->deleteThrows = true;

        try {
            ($this->handler)(new DuplicateHostTemplateCommand(new HostTemplateId(7), null, TestActingUsers::unrestricted()));
            self::fail('The insert failure must be rethrown.');
        } catch (\RuntimeException $exception) {
            self::assertSame('insert failed', $exception->getMessage());
        }

        self::assertCount(1, $this->logger->records());
        self::assertSame('warning', $this->logger->lastRecord()['level'] ?? null);
    }

    public function testAVaultWriteFailureCreatesNothing(): void
    {
        $this->seedVaulted();
        $this->vault->writeThrows = true;

        try {
            ($this->handler)(new DuplicateHostTemplateCommand(new HostTemplateId(7), null, TestActingUsers::unrestricted()));
            self::fail('The vault failure must be reported.');
        } catch (VaultWriteFailedException) {
            self::assertSame([], $this->repository->copiedFrom);
        }
    }

    public function testAFailedCopyNeverPurgesTheSourceEntryItShares(): void
    {
        $this->seedVaulted();
        $this->vault->vaultEnabled = false;
        $this->repository->addCopyThrows = new \RuntimeException('insert failed');

        try {
            ($this->handler)(new DuplicateHostTemplateCommand(new HostTemplateId(7), null, TestActingUsers::unrestricted()));
            self::fail('The insert failure must be rethrown.');
        } catch (\RuntimeException $exception) {
            self::assertSame('insert failed', $exception->getMessage());
        }

        self::assertSame([], $this->vault->writeManyCalls);
        self::assertSame([], $this->vault->deleteCalls);
    }

    public function testATakenNameIsRefusedBeforeAnyVaultWrite(): void
    {
        $this->seedVaulted();
        $this->repository->hostNames = ['taken'];

        try {
            ($this->handler)(new DuplicateHostTemplateCommand(new HostTemplateId(7), new HostTemplateName('taken'), TestActingUsers::unrestricted()));
            self::fail('A taken name must be refused.');
        } catch (HostTemplateAlreadyExistsException) {
            self::assertSame([], $this->vault->writeManyCalls);
        }
    }

    public function testALockedSourceIsRefusedBeforeAnyVaultWrite(): void
    {
        $this->seedVaulted(isLocked: true);

        try {
            ($this->handler)(new DuplicateHostTemplateCommand(new HostTemplateId(7), null, TestActingUsers::unrestricted()));
            self::fail('A locked source must be refused.');
        } catch (HostTemplateLockedException) {
            self::assertSame([], $this->vault->writeManyCalls);
        }
    }

    private function seedVaulted(bool $isLocked = false): void
    {
        $this->vault->vaultEnabled = true;
        $this->vault->resolved = [self::SOURCE_SNMP => 'community', self::SOURCE_PASSWORD => 'secret'];
        $this->repository->hostTemplates[7] = new HostTemplate(
            new HostTemplateId(7),
            new HostTemplateName('generic'),
            macros: new Collection([
                new HostMacro(new HostMacroName('PWD'), self::SOURCE_PASSWORD, isPassword: true, id: new HostMacroId(1)),
                new HostMacro(new HostMacroName('PLAIN'), 'plain', isPassword: false, id: new HostMacroId(2)),
            ], HostMacro::class),
            isLocked: $isLocked,
            snmpCommunity: new SnmpCommunity(self::SOURCE_SNMP),
        );
    }

    private function seed(int $id, string $name, bool $isLocked = false): void
    {
        $this->repository->hostTemplates[$id] = new HostTemplate(new HostTemplateId($id), new HostTemplateName($name), isLocked: $isLocked);
    }

    private function onlyCopy(): HostTemplate
    {
        self::assertCount(1, $this->repository->copiedFrom);
        $copyId = array_key_first($this->repository->copiedFrom);
        self::assertSame(7, $this->repository->copiedFrom[$copyId]);

        return $this->repository->hostTemplates[$copyId];
    }
}

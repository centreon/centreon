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

use App\MonitoringConfiguration\Application\Command\ServiceMacroChanges;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
use App\MonitoringConfiguration\Domain\Event\ServiceVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\DuplicateServiceMacroNameException;
use App\MonitoringConfiguration\Domain\Exception\ReservedServiceMacroNameException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroValueIsAVaultReferenceException;
use App\MonitoringConfiguration\Domain\Exception\VaultWriteFailedException;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Exception\AggregateConflictException;
use App\Shared\Domain\Exception\AggregateNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The own macros of a service, replaced, written or removed. The stored service owns "port" (id 3) and "user"
 * (id 4).
 */
final class PatchServiceCommandHandlerMacrosTest extends PatchServiceCommandHandlerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->store(macros: [
            new ServiceMacro(new ServiceMacroName('port'), '80', isPassword: false, id: new ServiceMacroId(3)),
            new ServiceMacro(new ServiceMacroName('user'), 'admin', isPassword: false, id: new ServiceMacroId(4)),
        ]);
    }

    /**
     * @param list<array{?int, string, string}> $expected id, name and value of each own macro written
     */
    #[DataProvider('macroChangeProvider')]
    public function testTheMacrosAreReplacedWrittenAndRemoved(ServiceMacroChanges $changes, array $expected): void
    {
        $this->patch($this->command(macros: $changes));

        self::assertSame(
            $expected,
            array_map(static fn (ServiceMacro $macro): array => [$macro->id?->value, $macro->name->value, $macro->value], $this->written()->checkOptions->macros),
        );
        $this->massChange();
    }

    /**
     * @return iterable<string, array{ServiceMacroChanges, list<array{?int, string, string}>}>
     */
    public static function macroChangeProvider(): iterable
    {
        yield 'replaced: the ones left out are removed' => [
            ServiceMacroChanges::replace([
                new ServiceMacroChange(new ServiceMacroName('port'), '443', isPassword: false, id: new ServiceMacroId(3)),
                new ServiceMacroChange(new ServiceMacroName('path'), '/', isPassword: false),
            ]),
            [[3, 'PORT', '443'], [null, 'PATH', '/']],
        ];

        yield 'written: the other ones are kept' => [
            ServiceMacroChanges::upsert([
                new ServiceMacroChange(new ServiceMacroName('port'), '443', isPassword: false, id: new ServiceMacroId(3)),
                new ServiceMacroChange(new ServiceMacroName('path'), '/', isPassword: false),
            ]),
            [[4, 'USER', 'admin'], [3, 'PORT', '443'], [null, 'PATH', '/']],
        ];

        yield 'removed by id' => [
            ServiceMacroChanges::remove([new ServiceMacroId(3)]),
            [[4, 'USER', 'admin']],
        ];

        yield 'all removed by an empty replacement' => [ServiceMacroChanges::replace([]), []];
    }

    /**
     * @param class-string<AggregateNotFoundException|AggregateConflictException> $exceptionClass
     * @param array<string, mixed> $criteria
     */
    #[DataProvider('refusedMacroChangeProvider')]
    public function testARefusedMacroChangeWritesNothing(ServiceMacroChanges $changes, string $exceptionClass, array $criteria): void
    {
        try {
            $this->patch($this->command(macros: $changes));
            self::fail('The macros must be refused.');
        } catch (AggregateNotFoundException|AggregateConflictException $exception) {
            self::assertInstanceOf($exceptionClass, $exception);
            self::assertSame($criteria, $exception->criteria);
        }

        $this->assertNothingWritten();
        self::assertSame([], $this->vault->writeManyCalls);
    }

    /**
     * @return iterable<string, array{ServiceMacroChanges, class-string, array<string, mixed>}>
     */
    public static function refusedMacroChangeProvider(): iterable
    {
        yield 'a removal of an id the service does not own' => [
            ServiceMacroChanges::remove([new ServiceMacroId(3), new ServiceMacroId(99)]),
            ServiceMacroNotFoundException::class,
            ['checkOptions' => [99]],
        ];

        yield 'a new macro under a name the service already has' => [
            ServiceMacroChanges::upsert([new ServiceMacroChange(new ServiceMacroName('user'), 'root', isPassword: false)]),
            DuplicateServiceMacroNameException::class,
            ['checkOptions' => ['USER']],
        ];

        yield 'a rename onto a name the service already has' => [
            ServiceMacroChanges::upsert([new ServiceMacroChange(new ServiceMacroName('user'), '80', isPassword: false, id: new ServiceMacroId(3))]),
            DuplicateServiceMacroNameException::class,
            ['checkOptions' => ['USER']],
        ];

        yield 'a reserved name' => [
            ServiceMacroChanges::upsert([
                new ServiceMacroChange(new ServiceMacroName('pwd'), 's3cr3t', isPassword: true),
                new ServiceMacroChange(new ServiceMacroName('criticality_level'), '1', isPassword: false),
            ]),
            ReservedServiceMacroNameException::class,
            ['checkOptions' => ['CRITICALITY_LEVEL']],
        ];

        yield 'a vault reference as a value' => [
            ServiceMacroChanges::replace([new ServiceMacroChange(new ServiceMacroName('pwd'), self::SERVICE_PWD, isPassword: true)]),
            ServiceMacroValueIsAVaultReferenceException::class,
            ['checkOptions' => ['PWD']],
        ];
    }

    /**
     * The macros are resolved against the template the same update gives the service.
     */
    public function testAMacroOfTheTemplateTheUpdateGivesIsOverridden(): void
    {
        $this->serviceTemplateRepository->templates[30] = new ServiceTemplate(
            new ServiceTemplateId(30),
            new ServiceTemplateName('template-30'),
            checkCommandId: new CommandId(self::CHECK_COMMAND_ID),
            macros: new Collection([new ServiceMacro(new ServiceMacroName('url'), '/', isPassword: false, id: new ServiceMacroId(11))], ServiceMacro::class),
        );
        $this->serviceTemplateRepository->names[30] = 'template-30';

        $this->patch($this->command(templateId: 30, macros: ServiceMacroChanges::upsert([
            new ServiceMacroChange(new ServiceMacroName('url'), '/health', isPassword: false, id: new ServiceMacroId(11), parent: ServiceMacroParentEnum::Template),
        ])));

        self::assertSame(
            ['PORT', 'USER', 'URL'],
            array_map(static fn (ServiceMacro $macro): string => $macro->name->value, $this->written()->checkOptions->macros),
        );
    }

    /**
     * A password resent without value keeps its stored secret: nothing to write.
     */
    public function testAPasswordResentWithoutValueChangesNothing(): void
    {
        $this->store(macros: [new ServiceMacro(new ServiceMacroName('pwd'), self::SERVICE_PWD, isPassword: true, id: new ServiceMacroId(5))]);

        $this->patch($this->command(macros: ServiceMacroChanges::upsert([
            new ServiceMacroChange(new ServiceMacroName('pwd'), null, isPassword: true, id: new ServiceMacroId(5)),
        ])));

        $this->assertNothingWritten();
        self::assertSame([], $this->vault->writeManyCalls);
    }

    /**
     * A new password goes under the entry the service already has, before the save; rewritten under the same key,
     * it keeps the same reference and is still a change.
     */
    public function testAPasswordIsWrittenToTheServiceEntry(): void
    {
        $this->store(macros: [new ServiceMacro(new ServiceMacroName('pwd'), self::SERVICE_PWD, isPassword: true, id: new ServiceMacroId(5))]);

        $this->patch($this->command(macros: ServiceMacroChanges::upsert([
            new ServiceMacroChange(new ServiceMacroName('pwd'), 'n3w', isPassword: true, id: new ServiceMacroId(5)),
        ])));

        self::assertSame(
            [['customPath' => 'monitoring/services', 'secrets' => ['_SERVICEPWD' => 'n3w'], 'uuid' => 'svc-uuid', 'deletes' => []]],
            $this->vault->writeManyCalls,
        );
        self::assertSame(self::SERVICE_PWD, $this->written()->checkOptions->macros[0]->value);
        $this->massChange();
    }

    /**
     * An unreachable vault saves nothing and fires nothing.
     */
    public function testAVaultFailureSavesNothing(): void
    {
        $this->vault->writeThrows = true;

        try {
            $this->patch($this->command(name: 'pong', macros: ServiceMacroChanges::upsert([new ServiceMacroChange(new ServiceMacroName('pwd'), 's3cr3t', isPassword: true)])));
            self::fail('Expected VaultWriteFailedException');
        } catch (VaultWriteFailedException $exception) {
            self::assertInstanceOf(\RuntimeException::class, $exception->getPrevious());
        }

        $this->assertNothingWritten();
        self::assertSame([], $this->logger->warningMessages);
    }

    /**
     * A failure after a successful vault write leaves the vault ahead of the database: it is logged as a warning, and
     * the failure is passed on.
     */
    public function testAFailureAfterTheVaultWriteIsLoggedAsAWarning(): void
    {
        $failure = new \RuntimeException('database down');
        $this->serviceRepository->updateThrows = $failure;

        try {
            $this->patch($this->command(macros: ServiceMacroChanges::upsert([new ServiceMacroChange(new ServiceMacroName('pwd'), 's3cr3t', isPassword: true)])));
            self::fail('Expected the update failure');
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertCount(1, $this->logger->warningMessages);
        self::assertSame(self::SERVICE_ID, $this->logger->warningMessages[0]['context']['service_id']);
    }

    public function testAFailureWithoutVaultWriteIsNotLogged(): void
    {
        $this->serviceRepository->updateThrows = new \RuntimeException('database down');

        try {
            $this->patch($this->command(macros: ServiceMacroChanges::remove([new ServiceMacroId(3)])));
            self::fail('Expected the update failure');
        } catch (\RuntimeException) {
        }

        self::assertSame([], $this->logger->warningMessages);
    }

    /**
     * The last password removed leaves the service's vault entry without any secret: its key is deleted before the
     * save, and the entry purged once the save is committed, on a best-effort basis.
     */
    public function testAReleasedEntryIsPurgedAfterTheSave(): void
    {
        $this->store(macros: [new ServiceMacro(new ServiceMacroName('pwd'), self::SERVICE_PWD, isPassword: true, id: new ServiceMacroId(5))]);

        $this->patch($this->command(macros: ServiceMacroChanges::remove([new ServiceMacroId(5)])));

        self::assertSame(['_SERVICEPWD'], $this->vault->writeManyCalls[0]['deletes']);
        $purges = $this->eventBus->getDispatchedEvents(ServiceVaultPurgeRequested::class);
        self::assertCount(1, $purges);
        self::assertTrue($purges[0]->bestEffort);
        self::assertSame(self::SERVICE_PWD, $purges[0]->service->checkOptions->macros[0]->value);
    }

    /**
     * An update leaving the macros alone never reaches the vault, even for a service holding a password.
     */
    public function testAnUpdateOfAnotherFieldLeavesTheVaultAlone(): void
    {
        $this->store(macros: [new ServiceMacro(new ServiceMacroName('pwd'), self::SERVICE_PWD, isPassword: true, id: new ServiceMacroId(5))]);

        $this->patch($this->command(name: 'pong'));

        self::assertSame([], $this->vault->writeManyCalls);
        self::assertSame([], $this->eventBus->getDispatchedEvents(ServiceVaultPurgeRequested::class));
        self::assertSame(self::SERVICE_PWD, $this->written()->checkOptions->macros[0]->value);
    }
}

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
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Event\HostTemplateDuplicated;
use App\MonitoringConfiguration\Domain\Exception\DuplicatedHostTemplateNameTooLongException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateLockedException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\Shared\Application\Vault\VaultCredentialReader;
use App\Shared\Application\Vault\VaultCredentialWriter;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;
use Tests\App\Shared\Double\EventBusSpy;
use Tests\App\Shared\Double\FakeVault;

final class DuplicateHostTemplateCommandHandlerTest extends TestCase
{
    private FakeHostTemplateRepository $repository;

    private EventBusSpy $eventBus;

    private DuplicateHostTemplateCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new FakeHostTemplateRepository();
        $this->eventBus = new EventBusSpy();
        $vault = new FakeVault();
        $vault->vaultEnabled = false;

        $this->handler = new DuplicateHostTemplateCommandHandler(
            $this->repository,
            $vault,
            new VaultCredentialReader($vault),
            new VaultCredentialWriter($vault),
            $this->eventBus,
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

    public function testAGeneratedNameOverTheLimitIsAValidationFailure(): void
    {
        $this->seed(7, str_repeat('a', HostTemplateName::MAX_LENGTH - 1));

        $this->expectException(DuplicatedHostTemplateNameTooLongException::class);

        ($this->handler)(new DuplicateHostTemplateCommand(new HostTemplateId(7), null, TestActingUsers::unrestricted()));
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

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

use App\MonitoringConfiguration\Application\Command\CreateHostTemplateCommand;
use App\MonitoringConfiguration\Application\Command\CreateHostTemplateCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategory;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryName;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\Timezone;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneName;
use App\MonitoringConfiguration\Domain\Event\HostTemplateCreated;
use App\MonitoringConfiguration\Domain\Exception\HostCategoryNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostSeverityNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateMacroNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateMacroValueIsAVaultReferenceException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\LinkedServiceTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimezoneNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\UnlicensedHostTemplatesException;
use App\MonitoringConfiguration\Domain\Exception\UnlicensedLinkedServiceTemplatesException;
use App\MonitoringConfiguration\Domain\Exception\VaultWriteFailedException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\OptionRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Aggregate\ActingUser;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\VaultInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostCategoryRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostSeverityRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeOptionRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimezoneRepository;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;
use Tests\App\Shared\Double\EventBusSpy;
use Tests\App\Shared\Double\FakeVault;

final class CreateHostTemplateCommandHandlerTest extends KernelTestCase
{
    private const PARENT_SECRET = 'secret::vault::monitoring/hosts/parent-uuid::_HOSTPWD';

    private CreateHostTemplateCommandHandler $handler;

    private FakeHostTemplateRepository $repository;

    private FakeServiceTemplateRepository $serviceTemplateRepository;

    private FakeHostCategoryRepository $hostCategoryRepository;

    private FakeHostSeverityRepository $hostSeverityRepository;

    private FakeTimezoneRepository $timezoneRepository;

    private FakeOptionRepository $optionRepository;

    private FakeResourceAccessRepository $resourceAccessRepository;

    private FakeVault $vault;

    private EventBusSpy $eventBus;

    protected function setUp(): void
    {
        $container = self::getContainer();

        $this->repository = new FakeHostTemplateRepository();
        $this->serviceTemplateRepository = new FakeServiceTemplateRepository();
        $this->hostCategoryRepository = new FakeHostCategoryRepository();
        $this->hostSeverityRepository = new FakeHostSeverityRepository();
        $this->timezoneRepository = new FakeTimezoneRepository();
        $this->optionRepository = new FakeOptionRepository();
        $this->resourceAccessRepository = new FakeResourceAccessRepository();
        $this->vault = new FakeVault();
        $this->vault->vaultEnabled = false;

        $this->eventBus = new EventBusSpy();

        $container->set(HostTemplateRepository::class, $this->repository);
        $container->set(ServiceTemplateRepository::class, $this->serviceTemplateRepository);
        $container->set(HostCategoryRepository::class, $this->hostCategoryRepository);
        $container->set(HostSeverityRepository::class, $this->hostSeverityRepository);
        $container->set(TimezoneRepository::class, $this->timezoneRepository);
        $container->set(CommandRepository::class, new FakeCommandRepository());
        $container->set(OptionRepository::class, $this->optionRepository);
        $container->set(ResourceAccessRepository::class, $this->resourceAccessRepository);
        $container->set(VaultInterface::class, $this->vault);
        $container->set(VaultCredentialWriter::class, new VaultCredentialWriter($this->vault));
        $container->set(EventBus::class, $this->eventBus);

        /** @var CreateHostTemplateCommandHandler $handler */
        $handler = $container->get(CreateHostTemplateCommandHandler::class);
        $this->handler = $handler;
    }

    public function testItCreatesTheTemplateAndFiresItsCreation(): void
    {
        $this->addTemplate(7);
        $this->serviceTemplateRepository->names[20] = 'linked';

        $template = ($this->handler)($this->command(
            templateIds: [7],
            linkedServiceTemplateIds: [20],
            macroChanges: [new HostTemplateMacroChange(new HostMacroName('port'), '443', false)],
        ));

        self::assertSame([$template->id()->value], $this->repository->addedIds);
        self::assertSame('tpl', $template->name->value);
        self::assertSame('Alias', $template->alias?->value);
        self::assertNull($template->checkOptions->checkCommandId);
        self::assertSame([], $template->checkOptions->args);
        self::assertSame(['PORT'], $this->macroNames($template));
        self::assertSame([7], array_map(static fn (HostTemplateId $id): int => $id->value, $template->parentTemplateIds->toArray()));
        self::assertSame([20], array_map(static fn (ServiceTemplateId $id): int => $id->value, $template->linkedServiceTemplateIds->toArray()));
        $events = $this->eventBus->getDispatchedEvents(HostTemplateCreated::class);
        self::assertCount(1, $events);
        self::assertSame($template, $events[0]->aggregate);
        self::assertSame(5, $events[0]->creatorId);
    }

    public function testANameUsedByAHostIsRefused(): void
    {
        $this->repository->hostNames = ['tpl'];

        $this->expectException(HostTemplateAlreadyExistsException::class);

        ($this->handler)($this->command());
    }

    public function testANameUsedByATemplateIsRefusedAndNothingIsWritten(): void
    {
        $this->addTemplate(7, 'tpl');
        $this->vault->vaultEnabled = true;

        try {
            ($this->handler)($this->command(snmpCommunity: 'public'));
            self::fail('A taken name must be refused.');
        } catch (HostTemplateAlreadyExistsException) {
        }

        self::assertSame([], $this->repository->addedIds);
        self::assertSame([], $this->vault->writeManyCalls);
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostTemplateCreated::class));
    }

    public function testAnUnknownParentIsRefused(): void
    {
        try {
            ($this->handler)($this->command(templateIds: [404]));
            self::fail('An unknown parent must be refused.');
        } catch (HostTemplateNotFoundException $exception) {
            self::assertSame(['templateIds' => [404]], $exception->criteria);
        }
    }

    public function testAParentTheLicenseNoLongerAllowsIsRefused(): void
    {
        $this->addTemplate(7);
        $this->addTemplate(8);
        $this->repository->monitoringConnectorTemplateIds = [8];

        try {
            ($this->handler)($this->command(templateIds: [7, 8]));
            self::fail('An unlicensed parent must be refused.');
        } catch (UnlicensedHostTemplatesException $exception) {
            self::assertSame(['templateIds' => [8]], $exception->criteria);
        }

        self::assertSame([], $this->repository->addedIds);
    }

    public function testAnUnknownServiceTemplateIsRefused(): void
    {
        try {
            ($this->handler)($this->command(linkedServiceTemplateIds: [404]));
            self::fail('An unknown service template must be refused.');
        } catch (LinkedServiceTemplateNotFoundException $exception) {
            self::assertSame(['linkedServiceTemplateIds' => [404]], $exception->criteria);
        }
    }

    public function testAServiceTemplateTheLicenseNoLongerAllowsIsRefused(): void
    {
        $this->serviceTemplateRepository->names = [20 => 'allowed', 21 => 'connector'];
        $this->serviceTemplateRepository->monitoringConnectorTemplateIds = [21];

        try {
            ($this->handler)($this->command(linkedServiceTemplateIds: [20, 21]));
            self::fail('An unlicensed service template must be refused.');
        } catch (UnlicensedLinkedServiceTemplatesException $exception) {
            self::assertSame(['linkedServiceTemplateIds' => [21]], $exception->criteria);
        }

        self::assertSame([], $this->repository->addedIds);
    }

    public function testAServiceTemplateTheLicenseStillAllowsIsAccepted(): void
    {
        $this->serviceTemplateRepository->names = [20 => 'allowed', 21 => 'connector'];
        $this->serviceTemplateRepository->monitoringConnectorTemplateIds = [21];

        $template = ($this->handler)($this->command(linkedServiceTemplateIds: [20]));

        self::assertCount(1, $template->linkedServiceTemplateIds);
    }

    public function testACategoryOutsideTheActorScopeIsRefused(): void
    {
        $this->hostCategoryRepository->hostCategories[7] = new HostCategory(new HostCategoryId(7), new HostCategoryName('Production'));
        $this->resourceAccessRepository->hostCategoryScope = AccessScope::restrictedTo(HostCategoryId::class, []);

        $this->expectException(HostCategoryNotFoundException::class);

        ($this->handler)($this->command(categoryIds: [7], actor: TestActingUsers::restricted(9)));
    }

    public function testASeverityOutsideTheActorScopeIsRefused(): void
    {
        $this->hostSeverityRepository->hostSeverities[7] = new HostSeverityName('Critical');
        $this->resourceAccessRepository->hostSeverityScope = AccessScope::restrictedTo(HostSeverityId::class, []);

        $this->expectException(HostSeverityNotFoundException::class);

        ($this->handler)($this->command(severityId: 7, actor: TestActingUsers::restricted(9)));
    }

    public function testAnUnknownTimezoneIsRefused(): void
    {
        $this->expectException(TimezoneNotFoundException::class);

        ($this->handler)($this->command(timezoneId: 404));
    }

    public function testTheAcceptedReferencesAreKept(): void
    {
        $this->hostCategoryRepository->hostCategories[7] = new HostCategory(new HostCategoryId(7), new HostCategoryName('Production'));
        $this->hostSeverityRepository->hostSeverities[8] = new HostSeverityName('Critical');
        $this->timezoneRepository->timezones[9] = new Timezone(new TimezoneId(9), new TimezoneName('Europe/Paris'));

        $template = ($this->handler)($this->command(categoryIds: [7], severityId: 8, timezoneId: 9));

        self::assertCount(1, $template->categoryIds);
        self::assertSame(8, $template->severityId?->value);
        self::assertSame(9, $template->timezoneId?->value);
    }

    public function testTheSnmpCommunityAndPasswordMacrosShareOneVaultEntryWrittenBeforeTheSave(): void
    {
        $this->vault->vaultEnabled = true;
        $this->vault->writtenPaths['_HOSTSNMPCOMMUNITY'] = 'secret::vault::monitoring/hosts/shared-uuid::_HOSTSNMPCOMMUNITY';

        $template = ($this->handler)($this->command(
            snmpCommunity: 'public',
            macroChanges: [new HostTemplateMacroChange(new HostMacroName('pwd'), 's3cr3t', true)],
        ));

        self::assertSame('secret::vault::monitoring/hosts/shared-uuid::_HOSTSNMPCOMMUNITY', $template->snmpCommunity?->value);
        self::assertSame(['secret::vault::monitoring/hosts/shared-uuid::_HOSTPWD'], $this->macroValues($template));
        $macroWrites = array_values(array_filter(
            $this->vault->writeManyCalls,
            static fn (array $call): bool => array_key_exists('_HOSTPWD', $call['secrets']),
        ));
        self::assertSame('shared-uuid', $macroWrites[0]['uuid'] ?? null);
    }

    public function testWithoutAVaultTheSecretsAreStoredAsSent(): void
    {
        $template = ($this->handler)($this->command(
            snmpCommunity: 'public',
            macroChanges: [new HostTemplateMacroChange(new HostMacroName('pwd'), 's3cr3t', true)],
        ));

        self::assertSame('public', $template->snmpCommunity?->value);
        self::assertSame(['s3cr3t'], $this->macroValues($template));
    }

    public function testAVaultFailureSavesNothing(): void
    {
        $this->vault->vaultEnabled = true;
        $this->vault->writeThrows = true;

        try {
            ($this->handler)($this->command(snmpCommunity: 'public'));
            self::fail('A vault failure must stop the creation.');
        } catch (VaultWriteFailedException) {
        }

        self::assertSame([], $this->repository->addedIds);
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostTemplateCreated::class));
    }

    public function testAFailedSavePurgesTheVaultEntryMintedForTheCommunity(): void
    {
        $this->vault->vaultEnabled = true;
        $this->repository->addThrows = new \RuntimeException('The save failed.');

        try {
            ($this->handler)($this->command(snmpCommunity: 'public'));
            self::fail('The save failure must reach the caller.');
        } catch (\RuntimeException $exception) {
            self::assertSame('The save failed.', $exception->getMessage());
        }

        self::assertSame(['new-uuid'], array_column($this->vault->deleteCalls, 'uuid'));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostTemplateCreated::class));
    }

    public function testAFailedSavePurgesTheVaultEntryMintedForThePasswordMacros(): void
    {
        $this->vault->vaultEnabled = true;
        $this->repository->addThrows = new \RuntimeException('The save failed.');

        try {
            ($this->handler)($this->command(macroChanges: [new HostTemplateMacroChange(new HostMacroName('pwd'), 's3cr3t', true)]));
            self::fail('The save failure must reach the caller.');
        } catch (\RuntimeException) {
        }

        self::assertSame(['new-uuid'], array_column($this->vault->deleteCalls, 'uuid'));
    }

    public function testAMacroWriteFailureAfterTheCommunityWritePurgesTheCommunityEntry(): void
    {
        $this->vault->vaultEnabled = true;
        $this->vault->writeThrowsOnCall = 2;

        try {
            ($this->handler)($this->command(
                snmpCommunity: 'public',
                macroChanges: [new HostTemplateMacroChange(new HostMacroName('pwd'), 's3cr3t', true)],
            ));
            self::fail('A vault failure must stop the creation.');
        } catch (VaultWriteFailedException) {
        }

        self::assertSame(['new-uuid'], array_column($this->vault->deleteCalls, 'uuid'));
        self::assertSame([], $this->repository->addedIds);
    }

    public function testAFailingPurgeDoesNotHideTheCause(): void
    {
        $this->vault->vaultEnabled = true;
        $this->vault->deleteThrows = true;

        $this->repository->addThrows = new \RuntimeException('The save failed.');

        try {
            ($this->handler)($this->command(snmpCommunity: 'public'));
            self::fail('The save failure must reach the caller.');
        } catch (\RuntimeException $exception) {
            self::assertSame('The save failed.', $exception->getMessage());
        }

        self::assertCount(1, $this->vault->deleteCalls);
    }

    public function testWithoutAVaultAFailedSavePurgesNothing(): void
    {
        $this->repository->addThrows = new \RuntimeException('The save failed.');

        try {
            ($this->handler)($this->command(snmpCommunity: 'public'));
            self::fail('The save failure must reach the caller.');
        } catch (\RuntimeException) {
        }

        self::assertSame([], $this->vault->deleteCalls);
    }

    public function testWithoutAVaultAFailedSaveNeverPurgesAnInheritedEntry(): void
    {
        $this->repository->addThrows = new \RuntimeException('The save failed.');
        $this->addTemplate(7, macros: [new HostMacro(new HostMacroName('pwd'), self::PARENT_SECRET, true, new HostMacroId(70))]);

        try {
            ($this->handler)($this->command(
                templateIds: [7],
                macroChanges: [new HostTemplateMacroChange(new HostMacroName('renamed'), null, true, 70, HostMacroParentEnum::Template)],
            ));
            self::fail('The save failure must reach the caller.');
        } catch (\RuntimeException $exception) {
            self::assertSame('The save failed.', $exception->getMessage());
        }

        self::assertSame([], $this->vault->deleteCalls);
    }

    public function testWithoutAVaultAFailedSaveNeverPurgesACommunityReference(): void
    {
        $this->repository->addThrows = new \RuntimeException('The save failed.');

        try {
            ($this->handler)($this->command(snmpCommunity: 'secret::hashicorp_vault::monitoring/hosts/stored-uuid::_HOSTSNMPCOMMUNITY'));
            self::fail('The save failure must reach the caller.');
        } catch (\RuntimeException $exception) {
            self::assertSame('The save failed.', $exception->getMessage());
        }

        self::assertSame([], $this->vault->deleteCalls);
    }

    public function testAMacroValueThatIsAVaultReferenceIsRefused(): void
    {
        $this->expectException(HostTemplateMacroValueIsAVaultReferenceException::class);

        ($this->handler)($this->command(macroChanges: [
            new HostTemplateMacroChange(new HostMacroName('pwd'), self::PARENT_SECRET, true),
        ]));
    }

    public function testAnInheritedPasswordKeptByIdIsCopiedUnderTheTemplateOwnEntry(): void
    {
        $this->vault->vaultEnabled = true;
        $this->vault->resolved[self::PARENT_SECRET] = 'parent-secret';
        $this->addTemplate(7, macros: [new HostMacro(new HostMacroName('pwd'), self::PARENT_SECRET, true, new HostMacroId(70))]);

        $template = ($this->handler)($this->command(
            templateIds: [7],
            macroChanges: [new HostTemplateMacroChange(new HostMacroName('renamed'), null, true, 70, HostMacroParentEnum::Template)],
        ));

        self::assertSame(['RENAMED'], $this->macroNames($template));
        self::assertSame('parent-secret', $this->vault->writeManyCalls[0]['secrets']['_HOSTRENAMED'] ?? null);
        self::assertStringNotContainsString('parent-uuid', $this->macroValues($template)[0]);
    }

    public function testAMacroEqualToTheInheritedOneIsNotStored(): void
    {
        $this->addTemplate(7, macros: [new HostMacro(new HostMacroName('port'), '80', false, new HostMacroId(70))]);

        $template = ($this->handler)($this->command(
            templateIds: [7],
            macroChanges: [new HostTemplateMacroChange(new HostMacroName('port'), '80', false, 70, HostMacroParentEnum::Template)],
        ));

        self::assertSame([], $this->macroNames($template));
    }

    public function testAMacroReferringToNothingInheritedIsRefused(): void
    {
        $this->expectException(HostTemplateMacroNotFoundException::class);

        ($this->handler)($this->command(macroChanges: [
            new HostTemplateMacroChange(new HostMacroName('port'), '80', false, 70, HostMacroParentEnum::Template),
        ]));
    }

    public function testTheAdditiveInheritanceFlagsAreDroppedWhenTheOptionIsDisabled(): void
    {
        $this->optionRepository->options['inheritance_mode'] = '3';

        $template = ($this->handler)($this->command(notifications: $this->notifications()));

        self::assertInstanceOf(Notifications::class, $template->notifications);
        self::assertFalse($template->notifications->contactAdditiveInheritance);
        self::assertFalse($template->notifications->contactGroupAdditiveInheritance);
        self::assertSame(TriStateEnum::True, $template->notifications->enabled);
        self::assertCount(1, $template->notifications->contactIds);
    }

    public function testTheAdditiveInheritanceFlagsAreKeptWhenTheOptionIsEnabled(): void
    {
        $this->optionRepository->options['inheritance_mode'] = '1';

        $template = ($this->handler)($this->command(notifications: $this->notifications()));

        self::assertTrue($template->notifications?->contactAdditiveInheritance);
        self::assertTrue($template->notifications->contactGroupAdditiveInheritance);
    }

    /**
     * @param list<int> $templateIds
     * @param list<int> $linkedServiceTemplateIds
     * @param list<int> $categoryIds
     * @param list<string> $checkCommandArgs
     * @param list<HostTemplateMacroChange> $macroChanges
     */
    private function command(
        array $templateIds = [],
        array $linkedServiceTemplateIds = [],
        array $categoryIds = [],
        ?int $severityId = null,
        ?int $timezoneId = null,
        ?string $snmpCommunity = null,
        array $checkCommandArgs = [],
        array $macroChanges = [],
        ?Notifications $notifications = null,
        ?ActingUser $actor = null,
    ): CreateHostTemplateCommand {
        return new CreateHostTemplateCommand(
            name: new HostTemplateName('tpl'),
            alias: new HostAlias('Alias'),
            actor: $actor ?? TestActingUsers::unrestricted(5),
            snmpCommunity: $snmpCommunity,
            timezoneId: $timezoneId !== null ? new TimezoneId($timezoneId) : null,
            severityId: $severityId !== null ? new HostSeverityId($severityId) : null,
            templateIds: new Collection(array_map(static fn (int $id): HostTemplateId => new HostTemplateId($id), $templateIds), HostTemplateId::class),
            categoryIds: new Collection(array_map(static fn (int $id): HostCategoryId => new HostCategoryId($id), $categoryIds), HostCategoryId::class),
            linkedServiceTemplateIds: new Collection(
                array_map(static fn (int $id): ServiceTemplateId => new ServiceTemplateId($id), $linkedServiceTemplateIds),
                ServiceTemplateId::class,
            ),
            checkCommandArgs: $checkCommandArgs,
            macroChanges: new Collection($macroChanges, HostTemplateMacroChange::class),
            notifications: $notifications,
        );
    }

    private function notifications(): Notifications
    {
        return new Notifications(
            enabled: TriStateEnum::True,
            contactIds: new Collection([new NotificationContactId(7)], NotificationContactId::class),
            contactGroupIds: new Collection([new ContactGroupId(9)], ContactGroupId::class),
            contactAdditiveInheritance: true,
            contactGroupAdditiveInheritance: true,
        );
    }

    /**
     * @param list<HostMacro> $macros
     */
    private function addTemplate(int $id, string $name = '', array $macros = [], ?int $commandId = null): void
    {
        $this->repository->hostTemplates[$id] = new HostTemplate(
            new HostTemplateId($id),
            new HostTemplateName($name !== '' ? $name : 'parent-' . $id),
            new CheckOptions(
                $commandId !== null ? new CommandId($commandId) : null,
                macros: $macros,
            ),
        );
    }

    /**
     * @return list<string>
     */
    private function macroNames(HostTemplate $template): array
    {
        return array_map(static fn (HostMacro $macro): string => $macro->name->value, $template->checkOptions->macros);
    }

    /**
     * @return list<string>
     */
    private function macroValues(HostTemplate $template): array
    {
        return array_map(static fn (HostMacro $macro): string => $macro->value, $template->checkOptions->macros);
    }
}

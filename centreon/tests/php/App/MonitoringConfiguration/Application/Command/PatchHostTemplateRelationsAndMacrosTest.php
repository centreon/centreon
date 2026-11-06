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
use App\MonitoringConfiguration\Application\Command\NotificationsChanges;
use App\MonitoringConfiguration\Application\Command\PatchHostTemplateCommand;
use App\MonitoringConfiguration\Application\Command\PatchHostTemplateCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupName;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategory;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryName;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactName;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Event\HostTemplateServicesCleanupRequested;
use App\MonitoringConfiguration\Domain\Event\HostTemplateUpdated;
use App\MonitoringConfiguration\Domain\Event\HostTemplateVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\CircularHostTemplateInheritanceException;
use App\MonitoringConfiguration\Domain\Exception\DuplicateHostTemplateMacroNameException;
use App\MonitoringConfiguration\Domain\Exception\ExclusiveNotificationOptionException;
use App\MonitoringConfiguration\Domain\Exception\HostMacroValueIsAVaultReferenceException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateMacroNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\LinkedServiceTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\UnlicensedHostTemplatesException;
use App\MonitoringConfiguration\Domain\Exception\UnlicensedLinkedServiceTemplatesException;
use App\MonitoringConfiguration\Domain\Exception\VaultWriteFailedException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
use App\MonitoringConfiguration\Domain\Repository\OptionRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\VaultInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeContactGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostCategoryRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeNotificationContactRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeOptionRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceTemplateRepository;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;
use Tests\App\Shared\Double\EventBusSpy;
use Tests\App\Shared\Double\FakeVault;
use Tests\App\Shared\Infrastructure\Messenger\Double\LoggerSpy;

/**
 * The lists and the macros of a template update: what each one changes, what is saved, what is logged.
 */
final class PatchHostTemplateRelationsAndMacrosTest extends KernelTestCase
{
    private const VAULTED_COMMUNITY = 'secret::vault::monitoring/hosts/tpl-uuid::_HOSTSNMPCOMMUNITY';

    private PatchHostTemplateCommandHandler $handler;

    private FakeHostTemplateRepository $repository;

    private FakeHostCategoryRepository $hostCategoryRepository;

    private FakeServiceTemplateRepository $serviceTemplateRepository;

    private FakeNotificationContactRepository $contactRepository;

    private FakeContactGroupRepository $contactGroupRepository;

    private FakeResourceAccessRepository $resourceAccessRepository;

    private FakeVault $vault;

    private EventBusSpy $eventBus;

    private LoggerSpy $logger;

    protected function setUp(): void
    {
        $container = self::getContainer();

        $this->repository = new FakeHostTemplateRepository();
        $this->hostCategoryRepository = new FakeHostCategoryRepository();
        $this->serviceTemplateRepository = new FakeServiceTemplateRepository();
        $this->contactRepository = new FakeNotificationContactRepository();
        $this->contactGroupRepository = new FakeContactGroupRepository();
        $this->resourceAccessRepository = new FakeResourceAccessRepository();
        $this->vault = new FakeVault();
        $this->vault->vaultEnabled = false;

        $this->eventBus = new EventBusSpy();
        $this->logger = new LoggerSpy();

        $container->set(HostTemplateRepository::class, $this->repository);
        $container->set(HostCategoryRepository::class, $this->hostCategoryRepository);
        $container->set(ServiceTemplateRepository::class, $this->serviceTemplateRepository);
        $container->set(NotificationContactRepository::class, $this->contactRepository);
        $container->set(ContactGroupRepository::class, $this->contactGroupRepository);
        $container->set(CommandRepository::class, new FakeCommandRepository());
        $container->set(OptionRepository::class, new FakeOptionRepository());
        $container->set(ResourceAccessRepository::class, $this->resourceAccessRepository);
        $container->set(VaultInterface::class, $this->vault);
        $container->set(VaultCredentialWriter::class, new VaultCredentialWriter($this->vault));
        $container->set(EventBus::class, $this->eventBus);
        $container->set(LoggerInterface::class, $this->logger);

        /** @var PatchHostTemplateCommandHandler $handler */
        $handler = $container->get(PatchHostTemplateCommandHandler::class);
        $this->handler = $handler;

        $this->addTemplate(1);
        $this->addTemplate(2);
        $this->addTemplate(3);
    }

    public function testReplacingTheParentsSavesTheRelationsWithoutALogRow(): void
    {
        $this->addTemplate(7, parentIds: [1]);

        $template = ($this->handler)($this->command(templateIds: ListChange::replace([new HostTemplateId(2), new HostTemplateId(1)])));

        self::assertSame([2, 1], $this->idValues($template->parentTemplateIds));
        self::assertSame([7], $this->repository->updatedIds);
        self::assertSame([7], $this->repository->replacedRelationIds);
        self::assertSame([], $this->repository->replacedMacroIds);
        self::assertFalse($this->onlyUpdate()->loggable);
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostTemplateServicesCleanupRequested::class));
    }

    public function testAddedParentsGoAfterTheCurrentOnes(): void
    {
        $this->addTemplate(7, parentIds: [2]);

        $template = ($this->handler)($this->command(templateIds: ListChange::add([new HostTemplateId(1), new HostTemplateId(2)])));

        self::assertSame([2, 1], $this->idValues($template->parentTemplateIds));
    }

    public function testRemovedParentsAreDropped(): void
    {
        $this->addTemplate(7, parentIds: [1, 2]);

        $template = ($this->handler)($this->command(templateIds: ListChange::remove([new HostTemplateId(1)])));

        self::assertSame([2], $this->idValues($template->parentTemplateIds));
    }

    public function testTheSameParentsResentChangeNothing(): void
    {
        $this->addTemplate(7, parentIds: [1, 2]);

        ($this->handler)($this->command(templateIds: ListChange::replace([new HostTemplateId(1), new HostTemplateId(2)])));

        self::assertSame([], $this->repository->updatedIds);
        self::assertSame([], $this->eventBus->getDispatchedEvents(HostTemplateUpdated::class));
    }

    public function testReorderingTheParentsIsAChange(): void
    {
        $this->addTemplate(7, parentIds: [1, 2]);

        ($this->handler)($this->command(templateIds: ListChange::replace([new HostTemplateId(2), new HostTemplateId(1)])));

        self::assertSame([7], $this->repository->replacedRelationIds);
    }

    public function testAnUnknownParentIsRefused(): void
    {
        $this->addTemplate(7);

        $this->expectException(HostTemplateNotFoundException::class);

        ($this->handler)($this->command(templateIds: ListChange::add([new HostTemplateId(404)])));
    }

    public function testAnAddedParentTheLicenseNoLongerAllowsIsRefused(): void
    {
        $this->addTemplate(7);
        $this->repository->monitoringConnectorTemplateIds = [2];

        try {
            ($this->handler)($this->command(templateIds: ListChange::add([new HostTemplateId(2)])));
            self::fail('An unlicensed parent must be refused.');
        } catch (UnlicensedHostTemplatesException $exception) {
            self::assertSame(['templateIds' => [2]], $exception->criteria);
        }
    }

    public function testAnUnlicensedParentTheTemplateAlreadyHasIsKept(): void
    {
        $this->addTemplate(7, parentIds: [2]);
        $this->repository->monitoringConnectorTemplateIds = [2];

        $template = ($this->handler)($this->command(templateIds: ListChange::replace([new HostTemplateId(2), new HostTemplateId(1)])));

        self::assertSame([2, 1], $this->idValues($template->parentTemplateIds));
    }

    public function testTheTemplateCannotBeItsOwnParent(): void
    {
        $this->addTemplate(7);

        try {
            ($this->handler)($this->command(templateIds: ListChange::replace([new HostTemplateId(1), new HostTemplateId(7)])));
            self::fail('A template must not inherit from itself.');
        } catch (CircularHostTemplateInheritanceException $exception) {
            self::assertSame(['templateIds' => [7]], $exception->criteria);
        }

        self::assertSame([], $this->repository->updatedIds);
    }

    public function testADescendantCannotBecomeAParent(): void
    {
        $this->addTemplate(7);
        $this->addTemplate(8, parentIds: [7]);
        $this->addTemplate(9, parentIds: [8]);

        try {
            ($this->handler)($this->command(templateIds: ListChange::add([new HostTemplateId(9)])));
            self::fail('A loop must be refused.');
        } catch (CircularHostTemplateInheritanceException $exception) {
            self::assertSame(['templateIds' => [9]], $exception->criteria);
        }
    }

    public function testCategoriesTheActorCannotSeeStayOnAReplacement(): void
    {
        $this->addTemplate(7, categoryIds: [10, 11]);
        $this->addCategory(10);
        $this->addCategory(12);
        $this->resourceAccessRepository->hostCategoryScope = AccessScope::restrictedTo(HostCategoryId::class, [new HostCategoryId(10), new HostCategoryId(12)]);

        $template = ($this->handler)($this->command(categoryIds: ListChange::replace([new HostCategoryId(12)]), restricted: true));

        self::assertSame([11, 12], $this->sortedIdValues($template->categoryIds));
    }

    public function testACategoryTheActorCannotSeeIsRefused(): void
    {
        $this->addTemplate(7);
        $this->addCategory(11);
        $this->resourceAccessRepository->hostCategoryScope = AccessScope::restrictedTo(HostCategoryId::class, [new HostCategoryId(10)]);

        $this->expectException(\App\MonitoringConfiguration\Domain\Exception\HostCategoryNotFoundException::class);

        ($this->handler)($this->command(categoryIds: ListChange::add([new HostCategoryId(11)]), restricted: true));
    }

    public function testAReplacementKeepsTheContactsAndGroupsTheActorCannotSee(): void
    {
        $this->addTemplate(7);
        $template = $this->repository->hostTemplates[7];
        $this->repository->hostTemplates[7] = $template->with(notifications: ($template->notifications ?? Notifications::default())->withContacts(
            new Collection([new NotificationContactId(30), new NotificationContactId(31)], NotificationContactId::class),
            new Collection([new ContactGroupId(40), new ContactGroupId(41)], ContactGroupId::class),
        ));
        $this->contactRepository->names = [32 => new NotificationContactName('visible')];
        $this->contactGroupRepository->names = [42 => new ContactGroupName('visible')];
        $this->resourceAccessRepository->accessibleContactIds = new Collection([new NotificationContactId(31), new NotificationContactId(32)], NotificationContactId::class);
        $this->resourceAccessRepository->accessibleContactGroupIds = new Collection([new ContactGroupId(41), new ContactGroupId(42)], ContactGroupId::class);

        $template = ($this->handler)($this->command(
            contactIds: ListChange::replace([new NotificationContactId(32)]),
            contactGroupIds: ListChange::replace([new ContactGroupId(42)]),
            restricted: true,
        ));

        self::assertSame([30, 32], $this->sortedIdValues($template->notifications->contactIds ?? new Collection([], NotificationContactId::class)));
        self::assertSame([40, 42], $this->sortedIdValues($template->notifications->contactGroupIds ?? new Collection([], ContactGroupId::class)));
    }

    public function testAnUnknownLinkedServiceTemplateIsRefused(): void
    {
        $this->addTemplate(7);

        $this->expectException(LinkedServiceTemplateNotFoundException::class);

        ($this->handler)($this->command(linkedServiceTemplateIds: ListChange::add([new ServiceTemplateId(404)])));
    }

    public function testAnAddedLinkedServiceTemplateTheLicenseNoLongerAllowsIsRefused(): void
    {
        $this->addTemplate(7);
        $this->serviceTemplateRepository->names = [20 => 'allowed', 21 => 'connector'];
        $this->serviceTemplateRepository->monitoringConnectorTemplateIds = [21];

        try {
            ($this->handler)($this->command(linkedServiceTemplateIds: ListChange::add([new ServiceTemplateId(20), new ServiceTemplateId(21)])));
            self::fail('An unlicensed service template must be refused.');
        } catch (UnlicensedLinkedServiceTemplatesException $exception) {
            self::assertSame(['linkedServiceTemplateIds' => [21]], $exception->criteria);
        }
    }

    public function testAnUnlicensedLinkedServiceTemplateAlreadyLinkedIsKept(): void
    {
        $this->addTemplate(7, linkedServiceTemplateIds: [21]);
        $this->serviceTemplateRepository->names = [20 => 'allowed', 21 => 'connector'];
        $this->serviceTemplateRepository->monitoringConnectorTemplateIds = [21];

        $template = ($this->handler)($this->command(linkedServiceTemplateIds: ListChange::replace([new ServiceTemplateId(21), new ServiceTemplateId(20)])));

        self::assertSame([21, 20], $this->idValues($template->linkedServiceTemplateIds));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostTemplateServicesCleanupRequested::class));
    }

    public function testAContactsOnlyChangeIsSavedWithoutALogRow(): void
    {
        $this->addTemplate(7);
        $this->contactRepository->names = [30 => new NotificationContactName('contact')];
        $this->contactGroupRepository->names = [40 => new ContactGroupName('group')];

        $template = ($this->handler)($this->command(
            contactIds: ListChange::add([new NotificationContactId(30)]),
            contactGroupIds: ListChange::add([new ContactGroupId(40)]),
        ));

        self::assertSame([30], $this->idValues($template->notifications->contactIds ?? new Collection([], NotificationContactId::class)));
        self::assertSame([40], $this->idValues($template->notifications->contactGroupIds ?? new Collection([], ContactGroupId::class)));
        self::assertSame([7], $this->repository->replacedRelationIds);
        self::assertFalse($this->onlyUpdate()->loggable);
    }

    public function testContactsGivenToATemplateWithoutNotificationsGetTheDefaults(): void
    {
        $this->repository->hostTemplates[7] = new HostTemplate(new HostTemplateId(7), new HostTemplateName('tpl-7'));
        $this->contactRepository->names = [30 => new NotificationContactName('contact')];

        $template = ($this->handler)($this->command(contactIds: ListChange::add([new NotificationContactId(30)])));

        self::assertSame([30], $this->idValues($template->notifications->contactIds ?? new Collection([], NotificationContactId::class)));
        self::assertTrue($template->hasSameLoggedConfigurationAs($this->repository->hostTemplates[7]->with(notifications: Notifications::default())));
        self::assertFalse($this->onlyUpdate()->loggable);
    }

    public function testAnOptionsChangeIsLogged(): void
    {
        $this->addTemplate(7);

        $template = ($this->handler)($this->command(
            notificationOptions: $this->addedOptions(NotificationOptionEnum::Down),
        ));

        self::assertSame([NotificationOptionEnum::Down, NotificationOptionEnum::Recovery], $template->notifications?->options);
        self::assertSame([], $this->repository->replacedRelationIds);
        self::assertTrue($this->onlyUpdate()->loggable);
    }

    public function testTheSameOptionsInAnotherOrderChangeNothing(): void
    {
        $this->addTemplate(7, options: [NotificationOptionEnum::Down, NotificationOptionEnum::Recovery]);

        ($this->handler)($this->command(
            notificationOptions: $this->replacedOptions(NotificationOptionEnum::Recovery, NotificationOptionEnum::Down),
        ));

        self::assertSame([], $this->repository->updatedIds);
    }

    public function testNoneCannotBeAddedToOtherOptions(): void
    {
        $this->addTemplate(7);

        $this->expectException(ExclusiveNotificationOptionException::class);

        ($this->handler)($this->command(
            notificationOptions: $this->addedOptions(NotificationOptionEnum::None),
        ));
    }

    public function testAMacrosOnlyChangeIsSavedWithoutALogRow(): void
    {
        $this->addTemplate(7, macros: [$this->macro(70, 'PORT', '80')]);

        $template = ($this->handler)($this->command(macros: MacroChanges::upsert([
            new HostMacroChange(new HostMacroName('timeout'), '10', false),
        ])));

        self::assertSame(['PORT', 'TIMEOUT'], $this->macroNames($template));
        self::assertSame([7], $this->repository->replacedMacroIds);
        self::assertSame([], $this->repository->replacedRelationIds);
        self::assertFalse($this->onlyUpdate()->loggable);
    }

    public function testAnOwnMacroIsUpdatedInPlace(): void
    {
        $this->addTemplate(7, macros: [$this->macro(70, 'PORT', '80')]);

        $template = ($this->handler)($this->command(macros: MacroChanges::upsert([
            new HostMacroChange(new HostMacroName('port'), '443', false, new HostMacroId(70)),
        ])));

        $macro = $template->checkOptions->macros[0] ?? null;
        self::assertInstanceOf(HostMacro::class, $macro);
        self::assertSame([70, '443'], [$macro->id?->value, $macro->value]);
    }

    public function testAReplacementRemovesTheMacrosLeftOut(): void
    {
        $this->addTemplate(7, macros: [$this->macro(70, 'PORT', '80'), $this->macro(71, 'TIMEOUT', '10')]);

        $template = ($this->handler)($this->command(macros: MacroChanges::replace([
            new HostMacroChange(new HostMacroName('port'), '80', false, new HostMacroId(70)),
        ])));

        self::assertSame(['PORT'], $this->macroNames($template));
    }

    public function testAMacroIsRemovedById(): void
    {
        $this->addTemplate(7, macros: [$this->macro(70, 'PORT', '80'), $this->macro(71, 'TIMEOUT', '10')]);

        $template = ($this->handler)($this->command(macros: MacroChanges::remove([new HostMacroId(71)])));

        self::assertSame(['PORT'], $this->macroNames($template));
    }

    public function testRemovingAMacroTheTemplateDoesNotOwnIsRefused(): void
    {
        $this->addTemplate(7, macros: [$this->macro(70, 'PORT', '80')]);
        $this->addTemplate(8, macros: [$this->macro(80, 'OTHER', 'x')]);

        try {
            ($this->handler)($this->command(macros: MacroChanges::remove([new HostMacroId(80)])));
            self::fail('A macro of another template must not be removed.');
        } catch (HostTemplateMacroNotFoundException $exception) {
            self::assertSame(['checkOptions' => [80]], $exception->criteria);
        }
    }

    public function testAnUpsertGivingTwoMacrosOneNameIsRefused(): void
    {
        $this->addTemplate(7, macros: [$this->macro(70, 'PORT', '80'), $this->macro(71, 'TIMEOUT', '10')]);

        $this->expectException(DuplicateHostTemplateMacroNameException::class);

        ($this->handler)($this->command(macros: MacroChanges::upsert([
            new HostMacroChange(new HostMacroName('port'), '10', false, new HostMacroId(71)),
        ])));
    }

    public function testAVaultReferenceAsAMacroValueIsRefused(): void
    {
        $this->addTemplate(7);

        $this->expectException(HostMacroValueIsAVaultReferenceException::class);

        ($this->handler)($this->command(macros: MacroChanges::upsert([
            new HostMacroChange(new HostMacroName('pwd'), 'secret::vault::monitoring/hosts/x::_HOSTPWD', true),
        ])));
    }

    public function testAPasswordMacroGoesToTheTemplateVaultEntry(): void
    {
        $this->vault->vaultEnabled = true;
        $this->addTemplate(7, snmpCommunity: self::VAULTED_COMMUNITY);

        $template = ($this->handler)($this->command(macros: MacroChanges::upsert([
            new HostMacroChange(new HostMacroName('pwd'), 's3cr3t', true),
        ])));

        self::assertSame(['secret::vault::monitoring/hosts/tpl-uuid::_HOSTPWD'], array_map(
            static fn (HostMacro $macro): string => $macro->value,
            $template->checkOptions->macros,
        ));
        self::assertSame('tpl-uuid', $this->vault->writeManyCalls[0]['uuid'] ?? null);
    }

    public function testAPasswordResentWithAValueCountsAsAChangeWithoutALogRow(): void
    {
        $this->vault->vaultEnabled = true;
        $stored = 'secret::vault::monitoring/hosts/tpl-uuid::_HOSTPWD';
        $this->vault->writtenPaths['_HOSTPWD'] = $stored;
        $this->addTemplate(7, snmpCommunity: self::VAULTED_COMMUNITY, macros: [new HostMacro(new HostMacroName('PWD'), $stored, true, new HostMacroId(70))]);

        ($this->handler)($this->command(macros: MacroChanges::upsert([
            new HostMacroChange(new HostMacroName('pwd'), 'rotated', true, new HostMacroId(70)),
        ])));

        self::assertSame([7], $this->repository->updatedIds);
        self::assertFalse($this->onlyUpdate()->loggable);
    }

    public function testAFailedSavePurgesTheVaultEntryMintedForThePasswords(): void
    {
        $this->vault->vaultEnabled = true;
        $this->addTemplate(7);
        $this->repository->updateThrows = new \RuntimeException('The save failed.');

        try {
            ($this->handler)($this->command(macros: MacroChanges::upsert([
                new HostMacroChange(new HostMacroName('pwd'), 's3cr3t', true),
            ])));
            self::fail('The save failure must reach the caller.');
        } catch (\RuntimeException $exception) {
            self::assertSame('The save failed.', $exception->getMessage());
        }

        self::assertSame(['new-uuid'], array_column($this->vault->deleteCalls, 'uuid'));
    }

    public function testAFailedPasswordWriteAfterANewCommunityPurgesTheCommunityEntry(): void
    {
        $this->vault->vaultEnabled = true;
        $this->vault->writeThrowsOnCall = 2;
        $this->addTemplate(7);

        try {
            ($this->handler)(new PatchHostTemplateCommand(
                new HostTemplateId(7),
                TestActingUsers::unrestricted(5),
                snmpCommunity: 'new-community',
                macros: MacroChanges::upsert([new HostMacroChange(new HostMacroName('pwd'), 's3cr3t', true)]),
            ));
            self::fail('A vault failure must stop the update.');
        } catch (VaultWriteFailedException) {
        }

        self::assertSame(['new-uuid'], array_column($this->vault->deleteCalls, 'uuid'));
        self::assertSame([], $this->repository->updatedIds);
    }

    public function testAFailedSaveAfterWritingAnExistingEntryIsLoggedAndPurgesNothing(): void
    {
        $this->vault->vaultEnabled = true;
        $this->addTemplate(7, snmpCommunity: self::VAULTED_COMMUNITY);
        $this->repository->updateThrows = new \RuntimeException('The save failed.');

        try {
            ($this->handler)($this->command(macros: MacroChanges::upsert([
                new HostMacroChange(new HostMacroName('pwd'), 's3cr3t', true),
            ])));
            self::fail('The save failure must reach the caller.');
        } catch (\RuntimeException) {
        }

        self::assertSame([], $this->vault->deleteCalls);
        self::assertCount(1, $this->logger->warningMessages);
    }

    public function testAnInheritedPasswordIsNeverWrittenToItsParentEntry(): void
    {
        $this->vault->vaultEnabled = true;
        $parentSecret = 'secret::vault::monitoring/hosts/parent-uuid::_HOSTPWD';
        $this->vault->resolved[$parentSecret] = 'parent-secret';
        $this->addTemplate(1, macros: [new HostMacro(new HostMacroName('PWD'), $parentSecret, true, new HostMacroId(10))]);
        $this->addTemplate(7, parentIds: [1]);

        $template = ($this->handler)($this->command(macros: MacroChanges::upsert([
            new HostMacroChange(new HostMacroName('pwd_copy'), null, true, new HostMacroId(10), \App\MonitoringConfiguration\Domain\Aggregate\HostMacroParentEnum::Template),
            new HostMacroChange(new HostMacroName('other'), 'x', true),
        ])));

        self::assertNotContains('parent-uuid', array_column($this->vault->writeManyCalls, 'uuid'));
        self::assertSame(
            ['PWD_COPY' => 'secret::vault::monitoring/hosts/new-uuid::_HOSTPWD_COPY', 'OTHER' => 'secret::vault::monitoring/hosts/new-uuid::_HOSTOTHER'],
            array_combine(
                array_map(static fn (HostMacro $macro): string => $macro->name->value, $template->checkOptions->macros),
                array_map(static fn (HostMacro $macro): string => $macro->value, $template->checkOptions->macros),
            ),
        );
    }

    public function testAFailedPasswordWriteNeverPurgesTheParentEntryOfACopiedPassword(): void
    {
        $this->vault->vaultEnabled = true;
        $this->vault->writeThrows = true;

        $parentSecret = 'secret::vault::monitoring/hosts/parent-uuid::_HOSTPWD';
        $this->vault->resolved[$parentSecret] = 'parent-secret';
        $this->addTemplate(1, macros: [new HostMacro(new HostMacroName('PWD'), $parentSecret, true, new HostMacroId(10))]);
        $this->addTemplate(7, parentIds: [1]);

        try {
            ($this->handler)($this->command(macros: MacroChanges::upsert([
                new HostMacroChange(new HostMacroName('pwd_copy'), null, true, new HostMacroId(10), \App\MonitoringConfiguration\Domain\Aggregate\HostMacroParentEnum::Template),
            ])));
            self::fail('A vault failure must stop the update.');
        } catch (VaultWriteFailedException) {
        }

        self::assertSame([], $this->vault->deleteCalls);
    }

    public function testWithoutAVaultAFailedSaveNeverPurgesTheParentEntryOfACopiedPassword(): void
    {
        $this->repository->updateThrows = new \RuntimeException('The save failed.');
        $parentSecret = 'secret::vault::monitoring/hosts/parent-uuid::_HOSTPWD';
        $this->addTemplate(1, macros: [new HostMacro(new HostMacroName('PWD'), $parentSecret, true, new HostMacroId(10))]);
        $this->addTemplate(7, parentIds: [1]);

        try {
            ($this->handler)($this->command(macros: MacroChanges::upsert([
                new HostMacroChange(new HostMacroName('pwd_copy'), null, true, new HostMacroId(10), \App\MonitoringConfiguration\Domain\Aggregate\HostMacroParentEnum::Template),
            ])));
            self::fail('The save failure must reach the caller.');
        } catch (\RuntimeException $exception) {
            self::assertSame('The save failed.', $exception->getMessage());
        }

        self::assertSame([], $this->vault->deleteCalls);
    }

    public function testClearingTheCommunityKeepsTheEntryAPasswordMacroStillUses(): void
    {
        $this->vault->vaultEnabled = true;
        $this->addTemplate(7, macros: [new HostMacro(new HostMacroName('PWD'), 'secret::vault::monitoring/hosts/tpl-uuid::_HOSTPWD', true, new HostMacroId(70))], snmpCommunity: self::VAULTED_COMMUNITY);

        ($this->handler)(new PatchHostTemplateCommand(new HostTemplateId(7), TestActingUsers::unrestricted(5), snmpCommunity: null));

        self::assertSame(['_HOSTSNMPCOMMUNITY'], $this->vault->writeManyCalls[0]['deletes']);
        self::assertSame([], $this->eventBus->getDispatchedEvents(HostTemplateVaultPurgeRequested::class));
    }

    public function testRemovingTheLastVaultedPasswordClearsItsKeyAndPurgesTheEntry(): void
    {
        $this->vault->vaultEnabled = true;
        $this->addTemplate(7, macros: [new HostMacro(new HostMacroName('PWD'), 'secret::vault::monitoring/hosts/tpl-uuid::_HOSTPWD', true, new HostMacroId(70))]);

        ($this->handler)($this->command(macros: MacroChanges::remove([new HostMacroId(70)])));

        self::assertSame(['_HOSTPWD'], $this->vault->writeManyCalls[0]['deletes']);
        self::assertCount(1, $this->eventBus->getDispatchedEvents(HostTemplateVaultPurgeRequested::class));
    }

    /**
     * @return ListChange<NotificationOptionEnum>
     */
    private function addedOptions(NotificationOptionEnum ...$options): ListChange
    {
        return ListChange::add(array_values($options));
    }

    /**
     * @return ListChange<NotificationOptionEnum>
     */
    private function replacedOptions(NotificationOptionEnum ...$options): ListChange
    {
        return ListChange::replace(array_values($options));
    }

    private function onlyUpdate(): HostTemplateUpdated
    {
        $events = $this->eventBus->getDispatchedEvents(HostTemplateUpdated::class);
        self::assertCount(1, $events);

        return $events[0];
    }

    /**
     * @param ListChange<HostTemplateId>|null $templateIds
     * @param ListChange<HostCategoryId>|null $categoryIds
     * @param ListChange<ServiceTemplateId>|null $linkedServiceTemplateIds
     * @param ListChange<NotificationContactId>|null $contactIds
     * @param ListChange<ContactGroupId>|null $contactGroupIds
     * @param ListChange<NotificationOptionEnum>|null $notificationOptions
     */
    private function command(
        ?ListChange $templateIds = null,
        ?ListChange $categoryIds = null,
        ?ListChange $linkedServiceTemplateIds = null,
        ?ListChange $contactIds = null,
        ?ListChange $contactGroupIds = null,
        ?ListChange $notificationOptions = null,
        ?MacroChanges $macros = null,
        bool $restricted = false,
    ): PatchHostTemplateCommand {
        $noValue = new \App\Shared\Domain\NoValue();

        return new PatchHostTemplateCommand(
            new HostTemplateId(7),
            $restricted ? TestActingUsers::restricted(5) : TestActingUsers::unrestricted(5),
            notifications: $notificationOptions instanceof ListChange ? new NotificationsChanges(options: $notificationOptions) : $noValue,
            templateIds: $templateIds ?? $noValue,
            categoryIds: $categoryIds ?? $noValue,
            linkedServiceTemplateIds: $linkedServiceTemplateIds ?? $noValue,
            contactIds: $contactIds ?? $noValue,
            contactGroupIds: $contactGroupIds ?? $noValue,
            macros: $macros ?? $noValue,
        );
    }

    /**
     * @param list<int> $parentIds
     * @param list<int> $categoryIds
     * @param list<int> $linkedServiceTemplateIds
     * @param list<HostMacro> $macros
     * @param list<NotificationOptionEnum> $options
     */
    private function addTemplate(
        int $id,
        array $parentIds = [],
        array $categoryIds = [],
        array $linkedServiceTemplateIds = [],
        array $macros = [],
        ?string $snmpCommunity = null,
        array $options = [NotificationOptionEnum::Recovery],
    ): void {
        $this->repository->hostTemplates[$id] = new HostTemplate(
            new HostTemplateId($id),
            new HostTemplateName('tpl-' . $id),
            new CheckOptions(null, macros: $macros),
            parentTemplateIds: new Collection(array_map(static fn (int $parentId): HostTemplateId => new HostTemplateId($parentId), $parentIds), HostTemplateId::class),
            snmpCommunity: $snmpCommunity !== null ? new SnmpCommunity($snmpCommunity) : null,
            notifications: Notifications::default()->with(options: $options),
            categoryIds: new Collection(array_map(static fn (int $categoryId): HostCategoryId => new HostCategoryId($categoryId), $categoryIds), HostCategoryId::class),
            linkedServiceTemplateIds: new Collection(array_map(static fn (int $serviceTemplateId): ServiceTemplateId => new ServiceTemplateId($serviceTemplateId), $linkedServiceTemplateIds), ServiceTemplateId::class),
        );
    }

    private function addCategory(int $id): void
    {
        $this->hostCategoryRepository->hostCategories[$id] = new HostCategory(new HostCategoryId($id), new HostCategoryName('category-' . $id));
    }

    private function macro(int $id, string $name, string $value): HostMacro
    {
        return new HostMacro(new HostMacroName($name), $value, false, new HostMacroId($id));
    }

    /**
     * @return list<string>
     */
    private function macroNames(HostTemplate $template): array
    {
        return array_map(static fn (HostMacro $macro): string => $macro->name->value, $template->checkOptions->macros);
    }

    /**
     * @template T of \App\Shared\Domain\Aggregate\AggregateRootId
     *
     * @param Collection<T> $ids
     *
     * @return list<int>
     */
    private function idValues(Collection $ids): array
    {
        return array_values(array_map(static fn (\App\Shared\Domain\Aggregate\AggregateRootId $id): int => $id->value, $ids->toArray()));
    }

    /**
     * @template T of \App\Shared\Domain\Aggregate\AggregateRootId
     *
     * @param Collection<T> $ids
     *
     * @return list<int>
     */
    private function sortedIdValues(Collection $ids): array
    {
        $values = $this->idValues($ids);
        sort($values);

        return $values;
    }
}

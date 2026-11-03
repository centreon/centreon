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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceSchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\Shared\Domain\Aggregate\AclScopedInterface;
use App\Shared\Domain\Aggregate\PollerScopedInterface;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Exception\MissingIdException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Double\FakeVault;

final class ServiceTest extends TestCase
{
    public function testItHoldsItsFields(): void
    {
        $service = $this->service(id: 42, hostId: 1);

        self::assertSame(42, $service->id()->value);
        self::assertSame('web-check', $service->name->value);
        self::assertSame(1, $service->hostId->value);
        self::assertSame([], $service->checkOptions->macros);
    }

    public function testItHasNoOwnValueByDefaultAndIsEnabled(): void
    {
        $service = $this->service();

        self::assertTrue($service->activated);
        self::assertNull($service->templateId);
        self::assertEquals(new ServiceCheckOptions(null), $service->checkOptions);
        self::assertEquals(new ServiceSchedulingOptions(), $service->schedulingOptions);
        self::assertNull($service->notifications);
        self::assertEquals(new ServiceDataProcessing(), $service->dataProcessing);
        self::assertNull($service->extendedInformations);
        self::assertNull($service->graphTemplateId);
        self::assertNull($service->severityId);
        self::assertSame([], $service->serviceGroupIds->toArray());
        self::assertSame([], $service->categoryIds->toArray());
        self::assertSame([], $service->trapIds->toArray());
    }

    public function testItHoldsItsWholeConfiguration(): void
    {
        $checkOptions = new ServiceCheckOptions(new CommandId(2), ['80']);
        $schedulingOptions = new ServiceSchedulingOptions(normalCheckInterval: 5, retryCheckInterval: 1);
        $notifications = new ServiceNotifications(
            TriStateEnum::True,
            new Collection([], NotificationContactId::class),
            new Collection([], ContactGroupId::class),
        );
        $dataProcessing = new ServiceDataProcessing(obsessOverService: TriStateEnum::False);
        $extendedInformations = new ServiceExtendedInformations(iconId: new MediaId(8));

        $service = new Service(
            id: new ServiceId(42),
            name: new ServiceName('web-check'),
            hostId: new HostId(1),
            hostActivated: true,
            hostPollerId: new PollerId(3),
            activated: false,
            templateId: new ServiceTemplateId(7),
            checkOptions: $checkOptions,
            schedulingOptions: $schedulingOptions,
            notifications: $notifications,
            dataProcessing: $dataProcessing,
            extendedInformations: $extendedInformations,
            graphTemplateId: new GraphTemplateId(4),
            severityId: new ServiceSeverityId(6),
            serviceGroupIds: new Collection([new ServiceGroupId(10)], ServiceGroupId::class),
            categoryIds: new Collection([new ServiceCategoryId(11)], ServiceCategoryId::class),
            trapIds: new Collection([new TrapId(12)], TrapId::class),
        );

        self::assertFalse($service->activated);
        self::assertSame(7, $service->templateId?->value);
        self::assertSame($checkOptions, $service->checkOptions);
        self::assertSame($schedulingOptions, $service->schedulingOptions);
        self::assertSame($notifications, $service->notifications);
        self::assertSame($dataProcessing, $service->dataProcessing);
        self::assertSame($extendedInformations, $service->extendedInformations);
        self::assertSame(4, $service->graphTemplateId?->value);
        self::assertSame(6, $service->severityId?->value);
        self::assertEquals([new ServiceGroupId(10)], $service->serviceGroupIds->toArray());
        self::assertEquals([new ServiceCategoryId(11)], $service->categoryIds->toArray());
        self::assertEquals([new TrapId(12)], $service->trapIds->toArray());
    }

    public function testDisableThenEnable(): void
    {
        $service = $this->service();

        $service->disable();
        self::assertFalse($service->activated);

        $service->enable();
        self::assertTrue($service->activated);
    }

    public function testItIsAclAndPollerScoped(): void
    {
        // The markers route every Service event through the generic ACL and poller handlers.
        $interfaces = class_implements(Service::class);

        self::assertIsArray($interfaces);
        self::assertArrayHasKey(AclScopedInterface::class, $interfaces);
        self::assertArrayHasKey(PollerScopedInterface::class, $interfaces);
    }

    public function testThePollerToFlagIsTheOneOfItsEnabledHost(): void
    {
        $service = $this->service(hostActivated: true, hostPollerId: 3);

        self::assertSame(3, $service->pollerToFlag()?->value);
    }

    public function testNoPollerIsFlaggedForAServiceOfADisabledHost(): void
    {
        $service = $this->service(hostActivated: false, hostPollerId: 3);

        self::assertNull($service->pollerToFlag());
    }

    public function testNoPollerIsFlaggedForAServiceWhoseHostHasNoPoller(): void
    {
        $service = $this->service(hostActivated: true, hostPollerId: null);

        self::assertNull($service->pollerToFlag());
    }

    public function testGetVaultUuidReturnsNullWhenNothingIsVaulted(): void
    {
        $service = $this->service();

        self::assertNull($service->getVaultUuid(new FakeVault()));
    }

    public function testGetVaultUuidReturnsTheFirstVaultedPasswordMacro(): void
    {
        $vault = new FakeVault();
        $vault->extractedUuids['secret::vault::monitoring/services/uuid-1::_SERVICETOKEN'] = 'uuid-1';
        $macro = new ServiceMacro(
            new ServiceMacroName('token'),
            'secret::vault::monitoring/services/uuid-1::_SERVICETOKEN',
            isPassword: true,
        );
        $service = $this->service(macros: [$macro]);

        self::assertSame('uuid-1', $service->getVaultUuid($vault));
    }

    public function testGetVaultUuidIgnoresANonPasswordMacroEvenIfItLooksLikeAVaultPath(): void
    {
        $vault = new FakeVault();
        $vault->extractedUuids['secret::vault::monitoring/services/uuid-2::_SERVICENOTASECRET'] = 'uuid-2';
        $macro = new ServiceMacro(
            new ServiceMacroName('notasecret'),
            'secret::vault::monitoring/services/uuid-2::_SERVICENOTASECRET',
            isPassword: false,
        );
        $service = $this->service(macros: [$macro]);

        self::assertNull($service->getVaultUuid($vault));
    }

    public function testWithKeepsTheIdentityAndEverythingWhenNothingIsProvided(): void
    {
        $original = $this->persistedService();

        $copy = $original->with();

        self::assertNotSame($original, $copy);
        self::assertSame(42, $copy->id()->value);
        self::assertTrue($copy->hasSameConfigurationAs($original));
        self::assertTrue($copy->hasSameRelationsAs($original));
        self::assertSame([true, true, 3], [$copy->activated, $copy->hostActivated, $copy->hostPollerId?->value]);
    }

    public function testWithReplacesTheProvidedPropertiesOnly(): void
    {
        $original = $this->persistedService();

        $changed = $original->with(
            name: new ServiceName('other-check'),
            hostId: new HostId(5),
            hostActivated: false,
            hostPollerId: new PollerId(9),
            activated: false,
            trapIds: new Collection([new TrapId(13)], TrapId::class),
        );

        self::assertSame(
            ['other-check', 5, false, 9, false, [13]],
            [
                $changed->name->value,
                $changed->hostId->value,
                $changed->hostActivated,
                $changed->hostPollerId?->value,
                $changed->activated,
                array_map(static fn (TrapId $id): int => $id->value, $changed->trapIds->toArray()),
            ],
        );
        self::assertSame($original->checkOptions, $changed->checkOptions);
        self::assertSame($original->schedulingOptions, $changed->schedulingOptions);
        self::assertSame($original->notifications, $changed->notifications);
        self::assertSame($original->dataProcessing, $changed->dataProcessing);
        self::assertSame($original->extendedInformations, $changed->extendedInformations);
        self::assertSame($original->serviceGroupIds, $changed->serviceGroupIds);
        self::assertSame($original->categoryIds, $changed->categoryIds);
        self::assertSame([7, 4, 6], [$changed->templateId?->value, $changed->graphTemplateId?->value, $changed->severityId?->value]);
        self::assertSame(['web-check', 1, true], [$original->name->value, $original->hostId->value, $original->activated]);
    }

    public function testWithClearsAnOptionalPropertySetToNull(): void
    {
        $changed = $this->persistedService()->with(
            hostPollerId: null,
            templateId: null,
            notifications: null,
            extendedInformations: null,
            graphTemplateId: null,
            severityId: null,
        );

        self::assertSame(
            [null, null, null, null, null, null],
            [
                $changed->hostPollerId,
                $changed->templateId,
                $changed->notifications,
                $changed->extendedInformations,
                $changed->graphTemplateId,
                $changed->severityId,
            ],
        );
    }

    public function testWithRequiresAPersistedService(): void
    {
        $this->expectException(MissingIdException::class);

        $this->service()->with(name: new ServiceName('other-check'));
    }

    public function testSameConfigurationIgnoresTheActivationAndTheStateOfTheHost(): void
    {
        $original = $this->persistedService();

        self::assertTrue($original->hasSameConfigurationAs(
            $original->with(activated: false, hostActivated: false, hostPollerId: new PollerId(9)),
        ));
    }

    #[DataProvider('configurationChangeProvider')]
    public function testSameConfigurationDetectsAChangeOfEachField(string $field): void
    {
        $original = $this->persistedService();

        $changed = match ($field) {
            'name' => $original->with(name: new ServiceName('other-check')),
            'host' => $original->with(hostId: new HostId(5)),
            'template' => $original->with(templateId: new ServiceTemplateId(8)),
            'no template' => $original->with(templateId: null),
            'check options' => $original->with(checkOptions: new ServiceCheckOptions(new CommandId(2), ['443'])),
            'scheduling options' => $original->with(schedulingOptions: new ServiceSchedulingOptions(normalCheckInterval: 6, retryCheckInterval: 1)),
            'notifications' => $original->with(notifications: $this->notifications(interval: 5)),
            'no notifications' => $original->with(notifications: null),
            'contacts' => $original->with(notifications: $this->notifications(contactIds: [21, 22])),
            'data processing' => $original->with(dataProcessing: new ServiceDataProcessing(obsessOverService: TriStateEnum::True)),
            'extended information' => $original->with(extendedInformations: new ServiceExtendedInformations(iconId: new MediaId(9))),
            'no extended information' => $original->with(extendedInformations: null),
            'graph template' => $original->with(graphTemplateId: new GraphTemplateId(5)),
            default => self::fail("Unknown field {$field}"),
        };

        self::assertFalse($original->hasSameConfigurationAs($changed));
        self::assertFalse($changed->hasSameConfigurationAs($original));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function configurationChangeProvider(): iterable
    {
        foreach (['name', 'host', 'template', 'no template', 'check options', 'scheduling options', 'notifications', 'no notifications', 'contacts', 'data processing', 'extended information', 'no extended information', 'graph template'] as $field) {
            yield $field => [$field];
        }
    }

    public function testSameConfigurationTreatsEqualSubObjectsAsTheSame(): void
    {
        $original = $this->persistedService();

        $copy = $original->with(
            checkOptions: new ServiceCheckOptions(new CommandId(2), ['80']),
            schedulingOptions: new ServiceSchedulingOptions(normalCheckInterval: 5, retryCheckInterval: 1),
            notifications: $this->notifications(),
            dataProcessing: new ServiceDataProcessing(obsessOverService: TriStateEnum::False),
            extendedInformations: new ServiceExtendedInformations(iconId: new MediaId(8)),
        );

        self::assertTrue($original->hasSameConfigurationAs($copy));
        self::assertTrue($original->with(notifications: null)->hasSameConfigurationAs($original->with(notifications: null)));
    }

    #[DataProvider('relationProvider')]
    public function testSameConfigurationIgnoresTheRelations(string $relation): void
    {
        $original = $this->persistedService();

        self::assertTrue($original->hasSameConfigurationAs($this->withOtherRelation($original, $relation)));
    }

    #[DataProvider('relationProvider')]
    public function testSameRelationsDetectsARelationChange(string $relation): void
    {
        $original = $this->persistedService();

        self::assertFalse($original->hasSameRelationsAs($this->withOtherRelation($original, $relation)));
        self::assertFalse($this->withOtherRelation($original, $relation)->hasSameRelationsAs($original));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function relationProvider(): iterable
    {
        foreach (['service groups', 'categories', 'severity', 'no severity', 'traps'] as $relation) {
            yield $relation => [$relation];
        }
    }

    public function testSameRelationsComparesTheSets(): void
    {
        $service = $this->persistedService()->with(
            serviceGroupIds: new Collection([new ServiceGroupId(1), new ServiceGroupId(2)], ServiceGroupId::class),
        );

        self::assertTrue($service->hasSameRelationsAs($service->with(
            serviceGroupIds: new Collection([new ServiceGroupId(2), new ServiceGroupId(1), new ServiceGroupId(2)], ServiceGroupId::class),
        )));
    }

    public function testASaveDroppingEverySecretReleasesTheVaultEntry(): void
    {
        $vault = $this->vaultKnowing('uuid-1');
        $before = $this->service(id: 42, macros: [$this->passwordMacro('TOKEN', $this->ref('uuid-1', 'TOKEN'))]);

        self::assertTrue($this->service(id: 42)->releasesVaultEntryOf($before, $vault));
    }

    public function testAnyRemainingReferenceToTheEntryKeepsIt(): void
    {
        $vault = $this->vaultKnowing('uuid-1');
        $before = $this->service(id: 42, macros: [
            $this->passwordMacro('TOKEN', $this->ref('uuid-1', 'TOKEN')),
            $this->passwordMacro('OTHER', $this->ref('uuid-1', 'OTHER')),
        ]);

        // The first macro is gone, but the second still points to the entry.
        $after = $this->service(id: 42, macros: [$this->passwordMacro('OTHER', $this->ref('uuid-1', 'OTHER'))]);

        self::assertFalse($after->releasesVaultEntryOf($before, $vault));
    }

    public function testAReferenceToAnotherEntryDoesNotKeepTheOldOne(): void
    {
        $vault = $this->vaultKnowing('uuid-1', 'uuid-2');
        $before = $this->service(id: 42, macros: [$this->passwordMacro('TOKEN', $this->ref('uuid-1', 'TOKEN'))]);
        $after = $this->service(id: 42, macros: [$this->passwordMacro('OTHER', $this->ref('uuid-2', 'OTHER'))]);

        self::assertTrue($after->releasesVaultEntryOf($before, $vault));
    }

    public function testAServiceThatHadNoEntryReleasesNothing(): void
    {
        $vault = $this->vaultKnowing('uuid-1');

        self::assertFalse($this->service(id: 42)->releasesVaultEntryOf($this->service(id: 42), $vault));
    }

    public function testANonPasswordMacroLookingLikeAReferenceDoesNotKeepTheEntry(): void
    {
        $vault = $this->vaultKnowing('uuid-1');
        $before = $this->service(id: 42, macros: [$this->passwordMacro('TOKEN', $this->ref('uuid-1', 'TOKEN'))]);
        $after = $this->service(id: 42, macros: [new ServiceMacro(new ServiceMacroName('TOKEN'), $this->ref('uuid-1', 'TOKEN'), isPassword: false)]);

        self::assertTrue($after->releasesVaultEntryOf($before, $vault));
    }

    private function withOtherRelation(Service $service, string $relation): Service
    {
        return match ($relation) {
            'service groups' => $service->with(serviceGroupIds: new Collection([new ServiceGroupId(10), new ServiceGroupId(14)], ServiceGroupId::class)),
            'categories' => $service->with(categoryIds: new Collection([], ServiceCategoryId::class)),
            'severity' => $service->with(severityId: new ServiceSeverityId(15)),
            'no severity' => $service->with(severityId: null),
            'traps' => $service->with(trapIds: new Collection([new TrapId(16)], TrapId::class)),
            default => self::fail("Unknown relation {$relation}"),
        };
    }

    private function persistedService(): Service
    {
        return new Service(
            id: new ServiceId(42),
            name: new ServiceName('web-check'),
            hostId: new HostId(1),
            hostActivated: true,
            hostPollerId: new PollerId(3),
            activated: true,
            templateId: new ServiceTemplateId(7),
            checkOptions: new ServiceCheckOptions(new CommandId(2), ['80']),
            schedulingOptions: new ServiceSchedulingOptions(normalCheckInterval: 5, retryCheckInterval: 1),
            notifications: $this->notifications(),
            dataProcessing: new ServiceDataProcessing(obsessOverService: TriStateEnum::False),
            extendedInformations: new ServiceExtendedInformations(iconId: new MediaId(8)),
            graphTemplateId: new GraphTemplateId(4),
            severityId: new ServiceSeverityId(6),
            serviceGroupIds: new Collection([new ServiceGroupId(10)], ServiceGroupId::class),
            categoryIds: new Collection([new ServiceCategoryId(11)], ServiceCategoryId::class),
            trapIds: new Collection([new TrapId(12)], TrapId::class),
        );
    }

    /**
     * @param list<int> $contactIds
     */
    private function notifications(?int $interval = null, array $contactIds = [21]): ServiceNotifications
    {
        return new ServiceNotifications(
            TriStateEnum::True,
            new Collection(array_map(static fn (int $id): NotificationContactId => new NotificationContactId($id), $contactIds), NotificationContactId::class),
            new Collection([new ContactGroupId(31)], ContactGroupId::class),
            interval: $interval,
        );
    }

    private function ref(string $uuid, string $name): string
    {
        return "secret::vault::monitoring/services/{$uuid}::_SERVICE{$name}";
    }

    private function vaultKnowing(string ...$uuids): FakeVault
    {
        $vault = new FakeVault();
        foreach ($uuids as $uuid) {
            foreach (['TOKEN', 'OTHER'] as $name) {
                $vault->extractedUuids[$this->ref($uuid, $name)] = $uuid;
            }
        }

        return $vault;
    }

    private function passwordMacro(string $name, string $value): ServiceMacro
    {
        return new ServiceMacro(new ServiceMacroName($name), $value, isPassword: true);
    }

    /**
     * @param list<ServiceMacro> $macros
     */
    private function service(
        ?int $id = null,
        int $hostId = 1,
        array $macros = [],
        bool $hostActivated = true,
        ?int $hostPollerId = 1,
    ): Service {
        return new Service(
            id: $id !== null ? new ServiceId($id) : null,
            name: new ServiceName('web-check'),
            hostId: new HostId($hostId),
            hostActivated: $hostActivated,
            hostPollerId: $hostPollerId !== null ? new PollerId($hostPollerId) : null,
            checkOptions: new ServiceCheckOptions(null, [], $macros),
        );
    }
}
